<?php

//пример реализации чата
class GameWebsocketWorkerHandler extends WebsocketWorker
{
    protected $tanks = array();
    protected $bullets = array();
    protected $logins = array();
    protected $ips = array();

    protected $h = 100;
    protected $w = 100;
    protected $tankmodelsize = 4;
    protected $radius = 26;

    protected function onTimer() {
        foreach ($this->bullets as $tankId => $bullet) {
            $this->moveObject($this->bullets[$tankId]);
            if (!$this->checkBullet($tankId)) {
                unset($this->bullets[$tankId]);
            }
        }
        $this->sendData();
    }

    protected function onOpen($connectionId) {//вызывается при соединении с новым клиентом
        
    }

    protected function onClose($connectionId) {//вызывается при закрытии соединения клиентом
        unset($this->tanks[$connectionId]);
        unset($this->bullets[$connectionId]);
        if ($login = array_search($connectionId, $this->logins)) {
            unset($this->logins[$login]);
            $this->sendData();
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
                if (isset($tank['dir']) && empty($tank['fire'])) {
                    $this->tanks[$connectionId]['dir'] = $tank['dir'];

                    $this->moveObject($this->tanks[$connectionId], $this->tanks[$connectionId]['dir'], 1);

                    if ($this->tanks[$connectionId]['x'] < $this->tankmodelsize/2) {
                        $this->tanks[$connectionId]['x'] = $this->tankmodelsize/2;
                    }
                    if ($this->tanks[$connectionId]['y'] < $this->tankmodelsize/2) {
                        $this->tanks[$connectionId]['y'] = $this->tankmodelsize/2;
                    }
                    if ($this->tanks[$connectionId]['x'] > $this->w - $this->tankmodelsize/2) {
                        $this->tanks[$connectionId]['x'] = $this->w - $this->tankmodelsize/2;
                    }
                    if ($this->tanks[$connectionId]['y'] > $this->h - $this->tankmodelsize/2) {
                        $this->tanks[$connectionId]['y'] = $this->h - $this->tankmodelsize/2;
                    }

                    foreach ($this->tanks as $tankId => $tank) {
                        if ($tankId != $connectionId && $this->tanks[$connectionId]['dir'] == $tank['dir'] &&
                            abs($this->tanks[$connectionId]['x'] - $tank['x']) <= 1
                            && abs($this->tanks[$connectionId]['y'] - $tank['y']) <= 1) {
                            $this->tanks[$connectionId]['health']++;
                            $this->tanks[$tankId]['health']--;
                            break;
                        }
                    }
                }

                if (isset($tank['fire']) && !isset($this->bullets[$connectionId])) {
                    $this->bullets[$connectionId] = $this->tanks[$connectionId];
                    $this->moveObject($this->bullets[$connectionId], $this->bullets[$connectionId]['dir'], 1);
                    //$this->checkBullet($connectionId);
                }

                $this->sendData();
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
                    $this->tanks[$connectionId] = array('name' => $match[0], 'x' => rand(5, $this->w - 5), 'y' => rand(5, $this->h - 5), 'dir' => 'up', 'health' => 0);
                    $this->sendData();
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
        $data = $this->pack($cmd, $data);
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

    protected function sendData() {
        foreach ($this->tanks as $connectionId => $tank) {
            $current = $this->tanks[$connectionId];
            //$current['x'] = 50;
            //$current['y'] = 50;
            $current['h'] = $this->h;
            $current['w'] = $this->w;
            $tanks = array($current);
            foreach ($this->tanks as $tankId => $tank) {
                if ($tankId != $connectionId) {
                    $tank['x'] = $tank['x'] - $current['x'] + $this->radius;
                    $tank['y'] = $tank['y'] - $current['y'] + $this->radius;
                    if ($tank['x'] >= 0 && $tank['x'] <= $this->radius * 2 && $tank['y'] >= 0 && $tank['y'] <= $this->radius * 2) {
                        $tanks[] = $tank;
                    }
                }
            }

            $bullets = array();
            foreach ($this->bullets as $tankId => $bullet) {
                $bullet['x'] = $bullet['x'] - $current['x'] + $this->radius;
                $bullet['y'] = $bullet['y'] - $current['y'] + $this->radius;
                if ($bullet['x'] >= 0 && $bullet['x'] <= $this->radius * 2 && $bullet['y'] >= 0 && $bullet['y'] <= $this->radius * 2) {
                    $bullets[] = $bullet;
                }
            }

            $this->sendPacketToClient($connectionId, 'data', array('tanks' => $tanks, 'bullets' => $bullets));
        }
    }

    protected function moveObject(&$object, $dir = null, $interval = 1) {
        if (!$dir) {
            $dir = $object['dir'];
        }
        switch ($dir) {
            case 'up':
                $object['y'] -= $interval;
                break;
            case 'down':
                $object['y'] += $interval;
                break;
            case 'left':
                $object['x'] -= $interval;
                break;
            case 'right':
                $object['x'] += $interval;
                break;
        }
    }

    protected function checkBullet($bulletId) {
        if ($this->bullets[$bulletId]['x'] < 0 || $this->bullets[$bulletId]['y'] < 0
            || $this->bullets[$bulletId]['x'] > $this->w || $this->bullets[$bulletId]['y'] > $this->h) {
            return false;
        }

        foreach ($this->tanks as $tankId => $tank) {
            if ($bulletId != $tankId && abs($this->bullets[$bulletId]['x'] - $tank['x']) <= 1 && abs($this->bullets[$bulletId]['y'] - $tank['y']) <= 1) {
                $this->tanks[$bulletId]['health']++;
                $this->tanks[$tankId]['health']--;
                return false;
            }
        }

        return true;
    }
}