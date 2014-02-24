<?php

//пример реализации чата
class Chat2WebsocketWorkerHandler extends WebsocketWorker
{
    protected $ips;

    protected $logins = array();

    protected function onOpen($connectionId) {//вызывается при соединении с новым клиентом
        if ($this->logins) {
            $this->sendPacketToClient($connectionId, 'logins', array_keys($this->logins));
        }
    }

    protected function onClose($connectionId) {//вызывается при закрытии соединения клиентом
        if ($login = array_search($connectionId, $this->logins)) {
            unset($this->logins[$login]);
            $this->sendPacketToMaster('logout', array('login' => $login, 'clientId' => $connectionId));
            $this->sendPacketToClients('logout', $login);
        }
    }

    protected function onMessage($connectionId, $data, $type) {//вызывается при получении сообщения от клиента
        if (!strlen($data) || !mb_check_encoding($data, 'utf-8')) {
            return;
        }

        //антифлуд:
        $source = explode(':', stream_socket_get_name($this->clients[$connectionId], true));
        $ip = $source[0];
        $time = time();
        if (isset($this->ips[$ip]) && $this->ips[$ip] == $time) {
            return;
        } else {
            $this->ips[$ip] = $time;
        }

        if ($login = array_search($connectionId, $this->logins)) {
            $message = $login . ': ' . strip_tags($data);
            $this->sendPacketToMaster('message', $message);
            $this->sendPacketToClients('message', $message);
        } else {
            if (preg_match('/^[a-zA-Z0-9]{1,10}$/', $data, $match)) {
                if (isset($this->logins[$match[0]])) {
                    $this->sendPacketToClient($connectionId, 'message', 'Система: выбранное вами имя занято, попробуйте другое.');
                } else {
                    $this->logins[$match[0]] = -1;
                    $this->sendPacketToMaster('login', array('login' => $match[0], 'clientId' => $connectionId));
                }
            } else {
                $this->sendPacketToClient($connectionId, 'message', 'Система: ошибка при выборе имени. В имени можно использовать английские буквы и цифры. Имя не должно превышать 10 символов.');
            }
        }
        //var_export($data);
        //шлем всем сообщение, о том, что пишет один из клиентов
        //echo $data . "\n";
    }

    protected function onMasterMessage($packet) {//вызывается при получении сообщения от мастера
        $packet = $this->unpack($packet);
        if ($packet['cmd'] == 'message') {
            $this->sendPacketToClients('message', $packet['data']);
        } elseif ($packet['cmd'] == 'login') {
            if ($packet['data']['result']) {
                $this->logins[ $packet['data']['login'] ] = $packet['data']['clientId'];
                $this->sendPacketToClients('login', $packet['data']['login']);
                if (isset($this->clients[ $packet['data']['clientId'] ])) {
                    $this->sendPacketToClient($this->clients[ $packet['data']['clientId'] ], 'message', 'Система: вы вошли в чат под именем ' . $packet['data']['login']);
                }
            } else {
                $this->sendPacketToClient($this->clients[ $packet['data']['clientId'] ], 'message', 'Система: выбранное вами имя занято, попробуйте другое.');
            }
        } elseif ($packet['cmd'] == 'logout') {
            unset($this->logins[$packet['data']['login']]);
            $this->sendPacketToClients('logout', $packet['data']['login']);
        }
    }

    protected function sendPacketToMaster($cmd, $data) {//отправляем сообщение на мастер, чтобы он разослал его на все воркеры
        $this->sendToMaster($this->pack($cmd, $data));
    }

    private function sendPacketToClients($cmd, $data) {
        $data = $this->pack($cmd, $data);
        foreach ($this->clients as $clientId => $client) {
            $this->sendToClient($clientId, $data);
        }
    }

    protected function sendPacketToClient($connectionId, $cmd, $data) {
        $this->sendToClient($connectionId, $this->pack($cmd, $data));
    }

    public function pack($cmd, $data) {
        return json_encode(array('cmd' => $cmd, 'data' => $data));
    }

    public function unpack($data) {
        return json_decode($data, true);
    }
}