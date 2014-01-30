<?php

abstract class WebsocketWorker extends WebsocketGeneric
{
    protected $server;
    protected $master;
    protected $pid;
    protected $handshakes = array();

    public function __construct($server, $master) {
        $this->server = $server;
        $this->master = $master;
        $this->pid = posix_getpid();
    }

    public function start() {
        while (true) {
            //подготавливаем массив всех сокетов, которые нужно обработать
            $read = $this->clients;
            $read[] = $this->server;
            $read[] = $this->master;

            $write = array();

            if ($this->write) {
                foreach ($this->write as $clientId => $buffer) {
                    if ($buffer) {
                        $write[] = $clientId == intval($this->master) ? $this->master : $this->clients[$clientId];
                    }
                }
            }

            stream_select($read, $write, $except = null, null);//обновляем массив сокетов, которые можно обработать

            if (in_array($this->server, $read)) { //на серверный сокет пришёл запрос от нового клиента
                //подключаемся к нему и делаем рукопожатие, согласно протоколу вебсокета
                if ((count($this->clients) < self::MAX_SOCKETS) && ($client = @stream_socket_accept($this->server, 0))) {
                    stream_set_blocking($client, 0);
                    $this->clients[intval($client)] = $client;
                    $this->handshakes[intval($client)] = '';//отмечаем, что нужно сделать рукопожатие
                }

                //удаляем сервеный сокет из массива, чтобы не обработать его в этом цикле ещё раз
                unset($read[array_search($this->server, $read)]);
            }

            if (in_array($this->master, $read)) { //пришли данные от мастера
                $data = fread($this->master, self::SOCKET_BUFFER_SIZE);
                $this->addToRead($this->master, $data);

                while ($data = $this->read($this->master)) {
                    $this->onSend($data);//вызываем пользовательский сценарий
                }

                //удаляем мастера из массива, чтобы не обработать его в этом цикле ещё раз
                unset($read[array_search($this->master, $read)]);
            }

            if ($read) {//пришли данные от подключенных клиентов
                foreach ($read as $client) {
                    if (isset($this->handshakes[intval($client)])) {
                        if ($this->handshakes[intval($client)]) {//если уже было получено рукопожатие от клиента
                            continue;//то до отправки ответа от сервера читать здесь пока ничего не надо
                        }

                        if (!$this->handshake($client)) {
                            $this->close($client);
                        }
                    } else {
                        $data = fread($client, self::SOCKET_BUFFER_SIZE);

                        if (!strlen($data) || !$this->addToRead($client, $data)) { //соединение было закрыто или превышен размер буфера
                            $this->close($client);
                            continue;
                        }

                        while (($data = $this->decode($client)) && mb_check_encoding($data['payload'], 'utf-8')) {//декодируем буфер (в нём может быть несколько сообщений)
                            $this->onMessage($client, $data);//вызываем пользовательский сценарий
                        }
                    }
                }
            }

            if ($write) {
                foreach ($write as $client) {
                    if (is_resource($client)) {//проверяем, что мы его ещё не закрыли во время чтения
                        $this->sendBuffer($client);
                    }
                }
            }
        }
    }

    protected function handshake($client) {
        //считываем загаловки из соединения
        $data = fread($client, self::SOCKET_BUFFER_SIZE);

        if (!$this->addToRead($client, $data)) {
            return false;
        }

        if (!strpos($this->read[intval($client)], "\r\n\r\n")) {
            return true;
        }

        preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $this->read[intval($client)], $match);

        if (empty($match[1])) {
            return false;
        }

        $this->read[intval($client)] = '';

        //отправляем заголовок согласно протоколу вебсокета
        $SecWebSocketAccept = base64_encode(pack('H*', sha1($match[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept:$SecWebSocketAccept\r\n\r\n";

        $this->write($client, $upgrade);
        unset($this->handshakes[intval($client)]);

        $this->onOpen($client);

        return true;
    }

    protected function close($client) {
        unset($this->clients[intval($client)]);
        unset($this->handshakes[intval($client)]);
        unset($this->write[intval($client)]);
        unset($this->read[intval($client)]);
        @fclose($client);
        $this->onClose($client);//вызываем пользовательский сценарий
    }

    protected function encode($payload, $type = 'text', $masked = false)
    {
        $frameHead = array();
        $payloadLength = strlen($payload);

        switch ($type) {
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = $masked ? 255 : 127;
            for ($i = 0; $i < 8; $i++) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = $masked ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        } else {
            $frameHead[1] = $masked ? $payloadLength + 128 : $payloadLength;
        }

        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if ($masked) {
            // generate a random mask:
            $mask = array();
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);

        // append payload to frame:
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= $masked ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

    protected function decode($connect)
    {
        $data = $this->read[intval($connect)];

        $unmaskedPayload = '';
        $decodedData = array();

        // estimate frame type:
        $firstByteBinary = sprintf('%08b', ord($data[0]));
        $secondByteBinary = sprintf('%08b', ord($data[1]));
        $opcode = bindec(substr($firstByteBinary, 4, 4));
        $isMasked = $secondByteBinary[0] == '1';
        $payloadLength = ord($data[1]) & 127;

        switch ($opcode) {
            // text frame:
            case 1:
                $decodedData['type'] = 'text';
                break;

            case 2:
                $decodedData['type'] = 'binary';
                break;

            // connection close frame:
            case 8:
                $decodedData['type'] = 'close';
                break;

            // ping frame:
            case 9:
                $decodedData['type'] = 'ping';
                break;

            // pong frame:
            case 10:
                $decodedData['type'] = 'pong';
                break;

            default:
                $decodedData['type'] = '';
        }

        if ($payloadLength === 126) {
            $mask = substr($data, 4, 4);
            $payloadOffset = 8;
            $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        } elseif ($payloadLength === 127) {
            $mask = substr($data, 10, 4);
            $payloadOffset = 14;
            for ($tmp = '', $i = 0; $i < 8; $i++) {
                $tmp .= sprintf('%08b', ord($data[$i + 2]));
            }
            $dataLength = bindec($tmp) + $payloadOffset;
        } else {
            $mask = substr($data, 2, 4);
            $payloadOffset = 6;
            $dataLength = $payloadLength + $payloadOffset;
        }

        if (strlen($data) < $dataLength) {
            return false;
        } else {
            $this->read[intval($connect)] = substr($data, $dataLength);
        }

        if ($isMasked) {
            for ($i = $payloadOffset; $i < $dataLength; $i++) {
                $j = $i - $payloadOffset;
                if (isset($data[$i])) {
                    $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
                }
            }
            $decodedData['payload'] = $unmaskedPayload;
        } else {
            $payloadOffset = $payloadOffset - 4;
            $decodedData['payload'] = substr($data, $payloadOffset, $dataLength - $payloadOffset);
        }

        return $decodedData;
    }

    abstract protected function onOpen($client);

    abstract protected function onClose($client);

    abstract protected function onMessage($client, $data);

    abstract protected function onSend($data);
}