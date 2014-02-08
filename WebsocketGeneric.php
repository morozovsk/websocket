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
        /*pcntl_signal(SIGTERM, array($this, 'stop'));
        pcntl_signal_dispatch();*/

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
                        $write[] = isset($this->clients[$clientId]) ? $this->clients[$clientId] :
                            (isset($this->services[$clientId]) ? $this->services[$clientId] : $this->server);
                    }
                }
            }

            stream_select($read, $write, $except = null, null);//обновляем массив сокетов, которые можно обработать

            if ($this->server && in_array($this->server, $read)) { //на мастер пришёл запрос от нового клиента
                if ($client = stream_socket_accept($this->server, -1)) { //подключаемся к нему
                    $this->clients[intval($client)] = $client;
                }

                //удаляем мастера из массива, чтобы не обработать его в этом цикле ещё раз
                unset($read[array_search($this->server, $read)]);
            }

            if ($read) {//пришли данные от подключенных клиентов
                foreach ($read as $client) {
                    $data = fread($client, self::SOCKET_BUFFER_SIZE);

                    if (!strlen($data)) { //соединение было закрыто
                        $clientId = intval($client);

                        if (isset($this->clients[$clientId])) {
                            unset($this->clients[$clientId]);
                        } elseif (isset($this->services[$clientId])) {
                            unset($this->services[$clientId]);
                        } elseif($client == $this->server) {
                            unset($this->server);
                        }

                        unset($this->read[$clientId]);
                        unset($this->write[$clientId]);

                        @fclose($client);
                        continue;
                    }

                    $this->addToRead($client, $data);

                    while ($data = $this->read($client)) {
                        $this->onMessage($client, $data);
                    }
                }
            }

            if ($write) {
                foreach ($write as $client) {
                    $this->sendBuffer($client);
                    continue;
                }
            }
        }
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

    abstract protected function onMessage($client, $data);
}