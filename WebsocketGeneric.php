<?php

abstract class WebsocketGeneric
{
    const SOCKET_BUFFER_SIZE = 1024;
    const MAX_SOCKET_BUFFER_SIZE = 10240;
    const MAX_SOCKETS = 1000;
    const SOCKET_MESSAGE_DELIMITER = "\n";
    protected $clients = array();
    protected $read = array();
    protected $write = array();

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
}