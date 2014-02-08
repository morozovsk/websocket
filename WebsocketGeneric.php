<?php

abstract class WebsocketGeneric
{
    const SOCKET_BUFFER_SIZE = 1024;
    const MAX_SOCKET_BUFFER_SIZE = 10240;
    const MAX_SOCKETS = 1000;
    const SOCKET_MESSAGE_DELIMITER = "\n";
    protected $clients = array();
    protected $server = null;
    protected $services = array();
    protected $read = array();
    protected $write = array();

    public function start() {
        while (true) {
            //подготавливаем массив всех сокетов, которые нужно обработать
            $read = array_merge($this->services, $this->clients);

            if ($this->server) {
                $read[] = $this->server;
            }

            if (!$read) {
                return;
            }

            $write = array();

            if ($this->write) {
                foreach ($this->write as $clientId => $buffer) {
                    if ($buffer) {
                        $write[] = $this->getConnectById($clientId);
                    }
                }
            }

            $except = $read;

            stream_select($read, $write, $except, null);//обновляем массив сокетов, которые можно обработать

            if ($this->server && in_array($this->server, $read)) { //на серверный сокет пришёл запрос от нового клиента
                if ((count($this->clients) < self::MAX_SOCKETS) && ($client = @stream_socket_accept($this->server, 0))) {
                    stream_set_blocking($client, 0);
                    $this->clients[intval($client)] = $client;
                    $this->_onOpen($client);
                }

                //удаляем сервеный сокет из массива, чтобы не обработать его в этом цикле ещё раз
                unset($read[array_search($this->server, $read)]);
            }

            if ($services = array_intersect($this->services, $read)) {
                foreach ($services as $service) {
                    $data = fread($service, self::SOCKET_BUFFER_SIZE);
                    $this->addToRead($service, $data);

                    while ($data = $this->read($service)) {
                        $this->_onService($service, $data);//вызываем пользовательский сценарий
                    }

                    //удаляем сервис из массива, чтобы не обработать его в этом цикле ещё раз
                    unset($read[array_search($service, $read)]);
                }
            }

            if ($read) {//пришли данные от подключенных клиентов
                foreach ($read as $client) {
                    $data = fread($client, self::SOCKET_BUFFER_SIZE);

                    if (!strlen($data) || !$this->addToRead($client, $data)) { //соединение было закрыто или превышен размер буфера
                        $this->close(intval($client));
                        continue;
                    }

                    $this->_onMessage($client, $data);
                }
            }

            if ($write) {
                foreach ($write as $client) {
                    if (is_resource($client)) {//проверяем, что мы его ещё не закрыли во время чтения
                        $this->sendBuffer($client);
                    }
                }
            }

            if ($except) {
                foreach ($except as $client) {
                    $this->_onError(intval($client));
                }
            }
        }
    }

    protected function _onError($connect) {
        echo "An error has occurred: $connect\n";
    }

    protected function close($connect) {
        @fclose($this->getConnectById($connect));

        $connectId = intval($connect);

        if (isset($this->clients[$connectId])) {
            unset($this->clients[$connectId]);
        } elseif (isset($this->services[$connectId])) {
            unset($this->services[$connectId]);
        } elseif($connect == $this->server) {
            unset($this->server);
        }

        unset($this->write[$connectId]);
        unset($this->read[$connectId]);
    }

    protected function write($connect, $data, $delimiter = '') {
        @$this->write[intval($connect)] .=  $data . $delimiter;
    }

    protected function sendBuffer($connect) {
        $written = fwrite($connect, $this->write[intval($connect)], self::SOCKET_BUFFER_SIZE);
        $this->write[intval($connect)] = substr($this->write[intval($connect)], $written);
    }

    protected function read($connect) {
        $data = '';

        if (false !== ($pos = strpos($this->read[intval($connect)], self::SOCKET_MESSAGE_DELIMITER))) {
            $data = substr($this->read[intval($connect)], 0, $pos);
            $this->read[intval($connect)] = substr($this->read[intval($connect)], $pos + strlen(self::SOCKET_MESSAGE_DELIMITER));
        }

        return $data;
    }

    protected function addToRead($connect, $data) {
        @$this->read[intval($connect)] .= $data;//добавляем полученные данные в буфер чтения
        return strlen($this->read[intval($connect)]) < self::MAX_SOCKET_BUFFER_SIZE;
    }

    protected function getConnectById($socketId) {
        return isset($this->clients[$socketId]) ? $this->clients[$socketId] :
            (isset($this->services[$socketId]) ? $this->services[$socketId] : $this->server);
    }

    abstract protected function _onMessage($client);

    abstract protected function _onService($client, $data);
}