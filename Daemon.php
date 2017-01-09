<?php

namespace morozovsk\websocket;

abstract class Daemon extends Generic
{
    protected $pid;
    private $_handshakes = array();

    public function __construct($server, $service, $master) {
        $this->_server = $server;
        $this->_service = $service;
        $this->_master = $master;
        $this->pid = posix_getpid();
    }

    protected function _onOpen($connectionId) {
        $this->_handshakes[$connectionId] = '';//mark the connection that it needs a handshake
    }

    protected function _onMessage($connectionId) {
        if (isset($this->_handshakes[$connectionId])) {
            if ($this->_handshakes[$connectionId]) {//if the client has already made a handshake
                return;//then there does not need to read before sending the response from the server
            }

            if (!$this->_handshake($connectionId)) {
                $this->close($connectionId);
            }
        } else {
            while (($data = $this->_decode($connectionId)) && mb_check_encoding($data['payload'], 'utf-8')) {//decode buffer (there may be multiple messages)
                $this->onMessage($connectionId, $data['payload'], $data['type']);//call user handler
            }
        }
    }

    protected function close($connectionId) {
        if (isset($this->_handshakes[$connectionId])) {
            unset($this->_handshakes[$connectionId]);
        } elseif (isset($this->clients[$connectionId])) {
            $this->onClose($connectionId);//call user handler
        } elseif (isset($this->services[$connectionId])) {
            $this->onServiceClose($connectionId);//call user handler
        } elseif ($this->getIdByConnection($this->_master) == $connectionId) {
            $this->onMasterClose($connectionId);//call user handler
        }

        parent::close($connectionId);

        if (isset($this->clients[$connectionId])) {
            unset($this->clients[$connectionId]);
        } elseif (isset($this->services[$connectionId])) {
            unset($this->services[$connectionId]);
        } elseif ($this->getIdByConnection($this->_server) == $connectionId) {
            $this->_server = null;
        } elseif ($this->getIdByConnection($this->_service) == $connectionId) {
            $this->_service = null;
        } elseif ($this->getIdByConnection($this->_master) == $connectionId) {
            $this->_master = null;
        }

        unset($this->_write[$connectionId]);
        unset($this->_read[$connectionId]);
    }

    protected function sendToClient($connectionId, $data, $type = 'text') {
        if (!isset($this->_handshakes[$connectionId]) && isset($this->clients[$connectionId])) {
            $this->_write($connectionId, $this->_encode($data, $type));
        }
    }

    protected function sendToMaster($data) {
        $this->_write($this->getIdByConnection($this->_master), $data, self::SOCKET_MESSAGE_DELIMITER);
    }

    protected function sendToService($connectionId, $data) {
        $this->_write($connectionId, $data, self::SOCKET_MESSAGE_DELIMITER);
    }

    protected function _handshake($connectionId) {
        //read the headers from the connection
        if (!strpos($this->_read[$connectionId], "\r\n\r\n")) {
            return true;
        }

        preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $this->_read[$connectionId], $match);

        if (empty($match[1])) {
            return false;
        }

        $headers = explode("\r\n", $this->_read[$connectionId]);
        $info = array();

        foreach ($headers as $header) {
            if (($explode = explode(':', $header)) && isset($explode[1])) {
                $info[trim($explode[0])] = trim($explode[1]);
            } elseif (($explode = explode(' ', $header)) && isset($explode[1])) {
                $info[$explode[0]] = $explode[1];
            }
        }

        /*$source = explode(':', stream_socket_get_name($this->clients[$connectionId], true));
        $info['Ip'] = $source[0];*/

        $this->_read[$connectionId] = '';

        //send a header according to the protocol websocket
        $SecWebSocketAccept = base64_encode(pack('H*', sha1($match[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: {$SecWebSocketAccept}\r\n\r\n";

        $this->_write($connectionId, $upgrade);
        unset($this->_handshakes[$connectionId]);

        $this->onOpen($connectionId, $info);

        return true;
    }

    protected function _encode($payload, $type = 'text')
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

        if ($payloadLength > 65535) {
            $ext = pack('NN', 0, $payloadLength);
            $secondByte = 127;
        } elseif ($payloadLength > 125) {
            $ext = pack('n', $payloadLength);
            $secondByte = 126;
        } else {
            $ext = '';
            $secondByte = $payloadLength;
        }

        return $data  = chr($frameHead[0]) . chr($secondByte) . $ext . $payload;
    }

    protected function _decode($connectionId)
    {
        $data = $this->_read[$connectionId];

        if (strlen($data) < 2) return false;

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
            if (strlen($data) < 4) return false;
            $payloadOffset = 8;
            $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        } elseif ($payloadLength === 127) {
            if (strlen($data) < 10) return false;
            $payloadOffset = 14;
            for ($tmp = '', $i = 0; $i < 8; $i++) {
                $tmp .= sprintf('%08b', ord($data[$i + 2]));
            }
            $dataLength = bindec($tmp) + $payloadOffset;
        } else {
            $payloadOffset = 6;
            $dataLength = $payloadLength + $payloadOffset;
        }

        if (strlen($data) < $dataLength) {
            return false;
        } else {
            $this->_read[$connectionId] = substr($data, $dataLength);
        }

        if ($isMasked) {
            if ($payloadLength === 126) {
                $mask = substr($data, 4, 4);
            } elseif ($payloadLength === 127) {
                $mask = substr($data, 10, 4);
            } else {
                $mask = substr($data, 2, 4);
            }

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

    protected function getConnectionById($connectionId) {
        if (isset($this->clients[$connectionId])) {
            return $this->clients[$connectionId];
        } elseif (isset($this->services[$connectionId])) {
            return $this->services[$connectionId];
        } elseif ($this->getIdByConnection($this->_server) == $connectionId) {
            return $this->_server;
        } elseif ($this->getIdByConnection($this->_service) == $connectionId) {
            return $this->_service;
        } elseif ($this->getIdByConnection($this->_master) == $connectionId) {
            return $this->_master;
        }
    }

    protected function getIdByConnection($connection) {
        return intval($connection);
    }

    protected function onOpen($connectionId, $info) {}
    protected function onClose($connectionId) {}
    protected function onMessage($connectionId, $packet, $type) {}

    protected function onServiceMessage($connectionId, $data) {}
    protected function onServiceOpen($connectionId) {}
    protected function onServiceClose($connectionId) {}

    protected function onMasterMessage($data) {}
    protected function onMasterClose($connectionId) {}

    protected function onStart() {}
}
