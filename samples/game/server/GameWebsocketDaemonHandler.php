<?php

namespace morozovsk\websocket\samples;

//пример реализации чата
class GameWebsocketDaemonHandler extends \morozovsk\websocket\Daemon
{
    protected $tanks = array();
    protected $bullets = array();
    protected $logins = array();
    protected $ips = array();

    protected $radius = 26;
    protected $h = 52;
    protected $w = 52;
    protected $tankmodelsize = 4;

    protected function onTimer() {
        $tmp = 2 * $this->radius + count($this->tanks);
        if ($this->w < $tmp) {
            $this->w = $this->h = $tmp;
        }

        foreach ($this->tanks as $tankId => &$tank) {
            if (!empty($tank['move'])) {
                unset($tank['move']);
                $this->moveObject($tank, $tank['dir'], 1);
                $this->checkTank($tank);
            }

            if (!empty($tank['fire'])) {
                unset($tank['fire']);
                $this->bullets[$tankId] = array('dir' => $tank['dir'], 'x' => $tank['x'], 'y' => $tank['y'], 'tankId' => $tankId);
            }
        }

        foreach ($this->bullets as $bulletId => &$bullet) {
            $this->moveObject($bullet, null, 2);
            if (!$this->checkBullet($bulletId)) {
                unset($this->bullets[$bulletId]);
            }
        }

        $this->sendData();
    }

    protected function onOpen($connectionId, $info) {//вызывается при соединении с новым клиентом
        /*static $startTimer = 0;
        if (!$startTimer) {
            $startTimer = 1;
            $this->sendToMaster('qwe');
        }*/
    }

    protected function onClose($connectionId) {//вызывается при закрытии соединения клиентом
        if (isset($this->tanks[$connectionId])) {
            unset($this->tanks[$connectionId]);
        }

        if ($login = array_search($connectionId, $this->logins)) {
            unset($this->logins[$login]);
        }
    }

    protected function onMessage($connectionId, $data, $type) {//вызывается при получении сообщения от клиента
        if (!strlen($data)) {
            return;
        }

        if ($login = array_search($connectionId, $this->logins)) {
            if (($tank = @json_decode($data, true)) && is_array($tank)) {
                //var_export($tank) . "\n";
                //$this->tanks[$connectionId] = $tank;
                //var_dump($this->tanks[$connectionId]);
                if (!empty($tank['move'])) {
                    $this->tanks[$connectionId]['dir'] = $tank['dir'];
                    $this->tanks[$connectionId]['move'] = true;
                }

                if (!empty($tank['fire']) && !isset($this->bullets[$connectionId])) {
                    $this->tanks[$connectionId]['fire'] = true;
                }
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

                $message = $login . ': ' . strip_tags($data);
                $this->sendPacketToClients('message', $message);
            }
        } else {
            if (preg_match('/^[a-zA-Z0-9]{1,10}$/', $data, $match)) {
                if (isset($this->logins[$match[0]])) {
                    $this->sendPacketToClient($connectionId, 'message', 'Система: выбранное вами имя занято, попробуйте другое.');
                } else {
                    $this->logins[$match[0]] = $connectionId;
                    $this->sendPacketToClient($connectionId, 'message', 'Система: вы вошли в игру под именем ' . $match[0] . '. Для управления танком воспользуйтесь клавишами: вверх, вниз, вправо, влево или w s a d.');
                    $this->tanks[$connectionId] = array('name' => $match[0], 'x' => rand($this->tankmodelsize/2, $this->w - $this->tankmodelsize/2), 'y' => rand($this->tankmodelsize/2, $this->h - $this->tankmodelsize/2), 'dir' => 'up', 'health' => 0);
                }
            } else {
                $this->sendPacketToClient($connectionId, 'message', 'Система: ошибка при выборе имени. В имени можно использовать английские буквы и цифры. Имя не должно превышать 10 символов.');
            }
        }
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

    protected function sendData($target = null) {
        foreach ($this->tanks as $connectionId => $tank) {
            if ($target && (abs($target['x'] - $tank['x']) > $this->radius + $this->tankmodelsize/2 || abs($target['y'] - $tank['y']) > $this->radius + $this->tankmodelsize/2)) {
                continue;
            }

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
            foreach ($this->bullets as $bullet) {
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
            if ($this->bullets[$bulletId]['tankId'] != $tankId && abs($this->bullets[$bulletId]['x'] - $tank['x']) <= 1 && abs($this->bullets[$bulletId]['y'] - $tank['y']) <= 1) {
                if (isset($this->tanks[$this->bullets[$bulletId]['tankId']])) {
                    $this->tanks[$this->bullets[$bulletId]['tankId']]['health']++;
                }
                $this->tanks[$tankId]['health']--;
                return false;
            }
        }

        return true;
    }

    protected function checkTank(&$tank) {
        if ($tank['x'] < $this->tankmodelsize/2) {
            $tank['x'] = $this->tankmodelsize/2;
        }
        if ($tank['y'] < $this->tankmodelsize/2) {
            $tank['y'] = $this->tankmodelsize/2;
        }
        if ($tank['x'] > $this->w - $this->tankmodelsize/2) {
            $tank['x'] = $this->w - $this->tankmodelsize/2;
        }
        if ($tank['y'] > $this->h - $this->tankmodelsize/2) {
            $tank['y'] = $this->h - $this->tankmodelsize/2;
        }

        foreach ($this->tanks as &$target) {
            if ($tank != $target && $tank['dir'] == $target['dir'] &&
                abs($tank['x'] - $target['x']) <= 1
                && abs($tank['y'] - $target['y']) <= 1) {
                $tank['health']++;
                $target['health']--;
                break;
            }
        }
    }
}