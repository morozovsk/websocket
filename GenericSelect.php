<?php

namespace morozovsk\websocket;

abstract class GenericSelect
{
    const SOCKET_BUFFER_SIZE = 1024;
    const MAX_SOCKET_BUFFER_SIZE = 10240;
    const MAX_SOCKETS = 1000;
    const SOCKET_MESSAGE_DELIMITER = "\n";
    protected $clients = array();
    protected $services = array();
    protected $_server = null;
    protected $_service = null;
    protected $_master = null;
    protected $_read = array();//read buffers
    protected $_write = array();//write buffers
    public $timer = null;

    public function start() {
        $this->onStart();

        if ($this->timer) {
            $timer = $this->_createTimer();
        }

        while (true) {
            //prepare the array of sockets that need to be processed
            $read = $this->clients + $this->services;

            if ($this->_server) {
                $read[] = $this->_server;
            }

            if ($this->_service) {
                $read[] = $this->_service;
            }

            if ($this->_master) {
                $read[] = $this->_master;
            }

            if ($this->timer) {
                $read[] = $timer;
            }

            if (!$read) {
                return;
            }

            $write = array();

            if ($this->_write) {
                foreach ($this->_write as $connectionId => $buffer) {
                    if ($buffer) {
                        $write[] = $this->getConnectionById($connectionId);
                    }
                }
            }

            $except = $read;

            stream_select($read, $write, $except, null);//update the array of sockets that can be processed

            if ($this->timer && in_array($timer, $read)) {
                unset($read[array_search($timer, $read)]);
                fread($timer, self::SOCKET_BUFFER_SIZE);
                $this->onTimer();
            }

            if ($read) {//data were obtained from the connected clients
                foreach ($read as $client) {
                    if ($this->_server == $client) { //the server socket got a request from a new client
                        if ((count($this->clients) + count($this->services) < self::MAX_SOCKETS) && ($client = @stream_socket_accept($this->_server, 0))) {
                            stream_set_blocking($client, 0);
                            $clientId = $this->getIdByConnection($client);
                            $this->clients[$clientId] = $client;
                            $this->_onOpen($clientId);
                        }
                    } elseif ($this->_service == $client) { //the local socket got a request from a new client
                        if ((count($this->clients) + count($this->services) < self::MAX_SOCKETS) && ($client = @stream_socket_accept($this->_service, 0))) {
                            stream_set_blocking($client, 0);
                            $clientId = $this->getIdByConnection($client);
                            $this->services[$clientId] = $client;
                            $this->onServiceOpen($clientId);
                        }
                    } else {
                        $connectionId = $this->getIdByConnection($client);
                        if (in_array($client, $this->services)) {
                            if (is_null($this->_read($connectionId))) { //connection has been closed or the buffer was overflow
                                $this->close($connectionId);
                                continue;
                            }

                            while ($data = $this->_readFromBuffer($connectionId)) {
                                $this->onServiceMessage($connectionId, $data); //call user handler
                            }
                        } elseif ($this->_master == $client) {
                            if (is_null($this->_read($connectionId))) { //connection has been closed or the buffer was overflow
                                $this->close($connectionId);
                                continue;
                            }

                            while ($data = $this->_readFromBuffer($connectionId)) {
                                $this->onMasterMessage($data); //call user handler
                            }
                        } else {
                            if (!$this->_read($connectionId)) { //connection has been closed or the buffer was overwhelmed
                                $this->close($connectionId);
                                continue;
                            }

                            $this->_onMessage($connectionId);
                        }
                    }
                }
            }

            if ($write) {
                foreach ($write as $client) {
                    if (is_resource($client)) {//verify that the connection is not closed during the reading
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
    }

    protected function _write($connectionId, $data, $delimiter = '') {
        @$this->_write[$connectionId] .=  $data . $delimiter;
    }

    protected function _sendBuffer($connect) {
        $connectionId = $this->getIdByConnection($connect);
        $written = fwrite($connect, $this->_write[$connectionId], self::SOCKET_BUFFER_SIZE);
        $this->_write[$connectionId] = substr($this->_write[$connectionId], $written);
    }

    protected function _readFromBuffer($connectionId) {
        $data = '';

        if (false !== ($pos = strpos($this->_read[$connectionId], self::SOCKET_MESSAGE_DELIMITER))) {
            $data = substr($this->_read[$connectionId], 0, $pos);
            $this->_read[$connectionId] = substr($this->_read[$connectionId], $pos + strlen(self::SOCKET_MESSAGE_DELIMITER));
        }

        return $data;
    }

    protected function _read($connectionId) {
        $data = fread($this->getConnectionById($connectionId), self::SOCKET_BUFFER_SIZE);

        if (!strlen($data)) return;

        @$this->_read[$connectionId] .= $data;//add the data into the read buffer
        return strlen($this->_read[$connectionId]) < self::MAX_SOCKET_BUFFER_SIZE;
    }

    protected function _createTimer() {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        $pid = pcntl_fork();//create a fork

        if ($pid == -1) {
            die("error: pcntl_fork\r\n");
        } elseif ($pid) { //parent
            fclose($pair[0]);
            return $pair[1];//one of the pair will be in the parent
        } else { //child process
            fclose($pair[1]);
            $parent = $pair[0];//second of the pair will be in the child

            while (true) {
                fwrite($parent, '1');

                usleep($this->timer * 1000000);
            }
        }
    }
}
