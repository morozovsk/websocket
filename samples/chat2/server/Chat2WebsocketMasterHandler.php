<?php

namespace morozovsk\websocket\samples;

class Chat2WebsocketMasterHandler extends \morozovsk\websocket\Daemon
{
    protected $logins = array();

    protected function onServiceMessage($connectionId, $packet) {//вызывается при получении сообщения от воркера
        $packet = $this->unpack($packet);

        if ($packet['cmd'] == 'message') {
            $this->sendPacketToOtherWorkers($connectionId, 'message', $packet['data']);
        } elseif ($packet['cmd'] == 'login') {
            $login = $packet['data']['login'];
            if (in_array($login, $this->logins)) {
                $packet['data']['result'] = false;
                $this->sendPacketToWorker($connectionId, 'login', $packet['data']);
            } else {
                $this->logins[] = $login;
                $packet['data']['result'] = true;
                $this->sendPacketToWorker($connectionId, 'login', $packet['data']);
                $packet['data']['clientId'] = -1;
                $this->sendPacketToOtherWorkers($connectionId, 'login', $packet['data']);
            }
        } elseif ($packet['cmd'] == 'logout') {
            $login = $packet['data']['login'];
            unset($this->logins[array_search($login, $this->logins)]);
            $this->sendPacketToOtherWorkers($connectionId, 'logout', $packet['data']);
        }
    }

    public function pack($cmd, $data) {
        return json_encode(array('cmd' => $cmd, 'data' => $data));
    }

    public function unpack($data) {
        return json_decode($data, true);
    }

    public function sendPacketToWorker($connectionId, $cmd, $data) {
        $this->sendToService($connectionId, $this->pack($cmd, $data));
    }

    public function sendPacketToOtherWorkers($connectionId, $cmd, $data) {
        $data = $this->pack($cmd, $data);
        foreach ($this->services as $workerId => $worker) { //пересылаем данные во все воркеры
            if ($workerId !== $connectionId) {
                $this->sendToService($workerId, $data);
            }
        }
    }
}