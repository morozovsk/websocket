<?php

class WebsocketMasterHandler extends WebsocketMaster
{
    protected $logins = array();

    protected function onMessage($client, $packet) //вызывается при получении сообщения от скриптов
    {

    }

    protected function onWorkerMessage($client, $packet) //вызывается при получении сообщения от воркера
    {
        $packet = $this->unpack($packet);

        if ($packet['cmd'] == 'message') {
            $this->sendToOtherWorkers($client, 'message', $packet['data']);
        } elseif ($packet['cmd'] == 'login') {
            $login = $packet['data']['login'];
            if (in_array($login, $this->logins)) {
                $packet['data']['result'] = false;
                $this->sendToClient($client, 'login', $packet['data']);
            } else {
                $this->logins[] = $login;
                $packet['data']['result'] = true;
                $this->sendToClient($client, 'login', $packet['data']);
                $packet['data']['clientId'] = -1;
                $this->sendToOtherWorkers($client, 'login', $packet['data']);
            }
        } elseif ($packet['cmd'] == 'logout') {
            $login = $packet['data']['login'];
            unset($this->logins[array_search($login, $this->logins)]);
            $this->sendToOtherWorkers($client, 'logout', $packet['data']);
        }
    }

    public function pack($cmd, $data) {
        return json_encode(array('cmd' => $cmd, 'data' => $data));
    }

    public function unpack($data) {
        return json_decode($data, true);
    }

    public  function sendToClient($client, $cmd, $data) {
        $this->write($client, $this->pack($cmd, $data), self::SOCKET_MESSAGE_DELIMITER);
    }

    public function sendToOtherWorkers($client, $cmd, $data) {
        $data = $this->pack($cmd, $data);
        foreach ($this->workers as $worker) { //пересылаем данные во все воркеры
            if ($worker !== $client) {
                $this->write($worker, $data, self::SOCKET_MESSAGE_DELIMITER);
            }
        }
    }
}