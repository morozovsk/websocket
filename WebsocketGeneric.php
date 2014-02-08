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
                foreach ($this->write as $connectionId => $buffer) {
                    if ($buffer) {
                        $write[] = $this->getConnectionById($connectionId);
                    }
                }
            }

            $except = $read;

            stream_select($read, $write, $except, null);//обновляем массив сокетов, которые можно обработать

            if ($this->server && in_array($this->server, $read)) { //на серверный сокет пришёл запрос от нового клиента
                if ((count($this->clients) < self::MAX_SOCKETS) && ($client = @stream_socket_accept($this->server, 0))) {
                    stream_set_blocking($client, 0);
                    $clientId = $this->getIdByConnection($client);
                    $this->clients[$clientId] = $client;
                    $this->_onOpen($clientId);
                }

                //удаляем сервеный сокет из массива, чтобы не обработать его в этом цикле ещё раз
                unset($read[array_search($this->server, $read)]);
            }

            if ($services = array_intersect($this->services, $read)) {
                foreach ($services as $service) {
                    //удаляем сервис из массива, чтобы не обработать его в этом цикле ещё раз
                    unset($read[array_search($service, $read)]);

                    $connectionId = $this->getIdByConnection($service);

                    if (!$this->_read($connectionId)) { //соединение было закрыто или превышен размер буфера
                        $this->close($connectionId);
                        continue;
                    }

                    while ($data = $this->readFromBuffer($connectionId)) {
                        $this->_onService($connectionId, $data);//вызываем пользовательский сценарий
                    }
                }
            }

            if ($read) {//пришли данные от подключенных клиентов
                foreach ($read as $client) {
                    $clientId = $this->getIdByConnection($client);

                    if (!$this->_read($clientId)) { //соединение было закрыто или превышен размер буфера
                        $this->close($clientId);
                        continue;
                    }

                    $this->_onMessage($clientId);
                }
            }

            if ($write) {
                foreach ($write as $client) {
                    if (is_resource($client)) {//проверяем, что мы его ещё не закрыли во время чтения
                        $this->_sendBuffer($client);
                    }
                }
            }

            if ($except) {
                foreach ($except as $client) {
                    $this->_onError($this->getIdByConnection($client));
                }
            }
        }
    }

    protected function _onError($connectionId) {
        echo "An error has occurred: $connectionId\n";
    }

    protected function close($connectionId) {
        @fclose($this->getConnectionById($connectionId));

        if (isset($this->clients[$connectionId])) {
            unset($this->clients[$connectionId]);
        } elseif (isset($this->services[$connectionId])) {
            unset($this->services[$connectionId]);
        } elseif($this->getConnectionById($connectionId) == $this->server) {
            unset($this->server);
        }

        unset($this->write[$connectionId]);
        unset($this->read[$connectionId]);
    }

    protected function write($connectionId, $data, $delimiter = '') {
        @$this->write[$connectionId] .=  $data . $delimiter;
    }

    protected function _sendBuffer($connect) {
        $connectionId = $this->getIdByConnection($connect);
        $written = fwrite($connect, $this->write[$connectionId], self::SOCKET_BUFFER_SIZE);
        $this->write[$connectionId] = substr($this->write[$connectionId], $written);
    }

    protected function readFromBuffer($connectionId) {
        $data = '';

        if (false !== ($pos = strpos($this->read[$connectionId], self::SOCKET_MESSAGE_DELIMITER))) {
            $data = substr($this->read[$connectionId], 0, $pos);
            $this->read[$connectionId] = substr($this->read[$connectionId], $pos + strlen(self::SOCKET_MESSAGE_DELIMITER));
        }

        return $data;
    }

    protected function _read($connectionId) {
        $data = fread($this->getConnectionById($connectionId), self::SOCKET_BUFFER_SIZE);

        if (!strlen($data)) return false;

        @$this->read[$connectionId] .= $data;//добавляем полученные данные в буфер чтения
        return strlen($this->read[$connectionId]) < self::MAX_SOCKET_BUFFER_SIZE;
    }

    protected function getConnectionById($connectionId) {
        return isset($this->clients[$connectionId]) ? $this->clients[$connectionId] :
            (isset($this->services[$connectionId]) ? $this->services[$connectionId] : $this->server);
    }

    protected function getIdByConnection($connection) {
        return intval($connection);
    }

    abstract protected function _onMessage($connectionId);

    abstract protected function _onService($connectionId, $data);

    abstract protected function _onOpen($connectionId);
}