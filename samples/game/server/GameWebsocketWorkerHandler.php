<?php

//пример реализации чата
class GameWebsocketWorkerHandler extends WebsocketWorker
{
    protected $tanks = array();
    protected $logins = array();
    protected $ips = array();

    protected $h = 60;
    protected $w = 100;
    protected $tankmodelsize = 3;

    protected function onOpen($connectionId) {//вызывается при соединении с новым клиентом
        
    }

    protected function onClose($connectionId) {//вызывается при закрытии соединения клиентом
        unset($this->tanks[$connectionId]);
        if ($login = array_search($connectionId, $this->logins)) {
            unset($this->logins[$login]);
            $this->sendTanks();
        }
    }

    protected function onMessage($connectionId, $data) {//вызывается при получении сообщения от клиента
        if (!strlen($data['payload'])) {
            return;
        }

        if ($login = array_search($connectionId, $this->logins)) {
            if (substr($data['payload'], 0, 1) == '{' && $tank = @json_decode($data['payload'], true)) {
                //echo $data['payload'] . "\n";
                //$this->tanks[$connectionId] = $tank;
                //var_dump($this->tanks[$connectionId]);
                $this->tanks[$connectionId]['dir'] = isset($tank['dir']) ? $tank['dir'] : $this->tanks[$connectionId]['dir'];

                switch ($this->tanks[$connectionId]['dir']) {
                    case 0:
                        $this->tanks[$connectionId]['y']--;
                        break;
                    case 1:
                        $this->tanks[$connectionId]['y']++;
                        break;
                    case 2:
                        $this->tanks[$connectionId]['x']--;
                        break;
                    case 3:
                        $this->tanks[$connectionId]['x']++;
                        break;
                }

                if ($this->tanks[$connectionId]['x'] < 0) {
                    $this->tanks[$connectionId]['x'] = 0;
                }
                if ($this->tanks[$connectionId]['y'] < 0) {
                    $this->tanks[$connectionId]['y'] = 0;
                }
                if ($this->tanks[$connectionId]['x'] > $this->w - $this->tankmodelsize) {
                    $this->tanks[$connectionId]['x'] = $this->w - $this->tankmodelsize;
                }
                if ($this->tanks[$connectionId]['y'] > $this->h - $this->tankmodelsize) {
                    $this->tanks[$connectionId]['y'] = $this->h - $this->tankmodelsize;
                }

                foreach ($this->tanks as $tankId => $tank) {
                    if ($tankId != $connectionId && $this->tanks[$connectionId]['dir'] == $tank['dir'] &&
                        ($this->tanks[$connectionId]['x'] == $tank['x']
                            && ($this->tanks[$connectionId]['y'] - 2 == $tank['y'] && $tank['dir'] == 0
                                || $this->tanks[$connectionId]['y'] + 2 == $tank['y'] && $tank['dir'] == 1)
                            || $this->tanks[$connectionId]['y'] == $tank['y']
                            && ($this->tanks[$connectionId]['x'] - 2 == $tank['x'] && $tank['dir'] == 2
                                || $this->tanks[$connectionId]['x'] + 2 == $tank['x'] && $tank['dir'] == 3)
                        )) {
                        $this->tanks[$connectionId]['health']++;
                        //$this->tanks[$tankId]['health']--;
                        break;
                    }
                }

                $this->sendTanks();
            } else {
                //антифлуд:
                $source = explode(':', stream_socket_get_name($this->getConnectionById($connectionId), true));
                $ip = $source[0];
                $time = time();
                if (isset($this->ips[$ip]) && $this->ips[$ip] == $time) {
                    return;
                } else {
                    $this->ips[$ip] = $time;
                }

                $message = $login . ': ' . strip_tags($data['payload']);
                $this->sendPacketToClients('message', $message);
            }

        } else {
            if (preg_match('/^[a-zA-Z0-9]{1,10}$/', $data['payload'], $match)) {
                if (isset($this->logins[$match[0]])) {
                    $this->sendPacketToClient($connectionId, 'message', 'Система: выбранное вами имя занято, попробуйте другое.');
                } else {
                    $this->logins[$match[0]] = $connectionId;
                    $this->sendPacketToClient($connectionId, 'message', 'Система: вы вошли в игру под именем ' . $match[0] . '. Для управления танком воспользуйтесь клавишами: вверх, вниз, вправо, влево или w s a d.');
                    $this->tanks[$connectionId] = array('name' => $match[0], 'x' => rand(1, $this->w - 2), 'y' => rand(1, $this->h - 2), 'dir' => 0, 'health' => 0);
                    $this->sendTanks();
                }
            } else {
                $this->sendPacketToClient($connectionId, 'message', 'Система: ошибка при выборе имени. В имени можно использовать английские буквы и цифры. Имя не должно превышать 10 символов.');
            }
        }
    }

    protected function onMasterMessage($data) {//вызывается при получении сообщения от мастера

    }

    protected function sendPacketToMaster($cmd, $data) {//отправляем сообщение на мастер, чтобы он разослал его на все воркеры
        $this->sendToMaster($this->pack($cmd, $data));
    }

    private function sendPacketToClients($cmd, $data) {
        $data = $this->encode($this->pack($cmd, $data));
        foreach ($this->clients as $connectionId) {
            $this->sendToClient($connectionId, $data);
        }
    }

    protected function sendPacketToClient($connectionId, $cmd, $data) {
        $this->sendToClient($connectionId, $this->pack($cmd, $data));
    }

    public function pack($cmd, $data) {
        return json_encode(array('cmd' => $cmd, 'data' => $data));
    }

    protected function sendTanks() {
        foreach ($this->tanks as $connectionId => $tank) {
            $tanks = array($this->tanks[$connectionId]);
            foreach ($this->tanks as $tankId => $tank) {
                if ($tankId != $connectionId) {
                    $tanks[] = $tank;
                }
            }

            $this->sendPacketToClient($connectionId, 'tanks', $tanks);
        }
    }
}