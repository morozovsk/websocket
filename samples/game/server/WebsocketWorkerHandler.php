<?php

//пример реализации чата
class WebsocketWorkerHandler extends WebsocketWorker
{
    protected $tanks = array();
    protected $logins = array();
    protected $ips = array();

    protected $h = 60;
    protected $w = 100;
    protected $tankmodelsize = 3;

    protected function onOpen($client) {//вызывается при соединении с новым клиентом
        //$this->write($client, $this->encode('Чтобы общаться в чате введите ник, под которым вы будете отображаться. Можно использовать английские буквы и цифры.'));
        //$this->sendTanks();
    }

    protected function onClose($client) {//вызывается при закрытии соединения клиентом
        unset($this->tanks[$client]);
        if ($login = array_search($client, $this->logins)) {
            unset($this->logins[$login]);
            $this->sendTanks();
        }
    }

    protected function onMessage($client, $data) {//вызывается при получении сообщения от клиента
        if (!strlen($data['payload'])) {
            return;
        }

        if ($login = array_search($client, $this->logins)) {
            if (substr($data['payload'], 0, 1) == '{' && $tank = @json_decode($data['payload'], true)) {
                //echo $data['payload'] . "\n";
                //$this->tanks[$client] = $tank;
                //var_dump($this->tanks[$client]);
                $this->tanks[$client]['dir'] = isset($tank['dir']) ? $tank['dir'] : $this->tanks[$client]['dir'];

                switch ($this->tanks[$client]['dir']) {
                    case 0:
                        $this->tanks[$client]['y']--;
                        break;
                    case 1:
                        $this->tanks[$client]['y']++;
                        break;
                    case 2:
                        $this->tanks[$client]['x']--;
                        break;
                    case 3:
                        $this->tanks[$client]['x']++;
                        break;
                }

                if ($this->tanks[$client]['x'] < 0) {
                    $this->tanks[$client]['x'] = 0;
                }
                if ($this->tanks[$client]['y'] < 0) {
                    $this->tanks[$client]['y'] = 0;
                }
                if ($this->tanks[$client]['x'] > $this->w - $this->tankmodelsize) {
                    $this->tanks[$client]['x'] = $this->w - $this->tankmodelsize;
                }
                if ($this->tanks[$client]['y'] > $this->h - $this->tankmodelsize) {
                    $this->tanks[$client]['y'] = $this->h - $this->tankmodelsize;
                }

                foreach ($this->tanks as $tankId => $tank) {
                    if ($tankId != $client && $this->tanks[$client]['dir'] == $tank['dir'] &&
                        ($this->tanks[$client]['x'] == $tank['x']
                            && ($this->tanks[$client]['y'] - 2 == $tank['y'] && $tank['dir'] == 0
                                || $this->tanks[$client]['y'] + 2 == $tank['y'] && $tank['dir'] == 1)
                            || $this->tanks[$client]['y'] == $tank['y']
                            && ($this->tanks[$client]['x'] - 2 == $tank['x'] && $tank['dir'] == 2
                                || $this->tanks[$client]['x'] + 2 == $tank['x'] && $tank['dir'] == 3)
                        )) {
                        $this->tanks[$client]['health']++;
                        //$this->tanks[$tankId]['health']--;
                        break;
                    }
                }

                $this->sendTanks();
            } else {
                //антифлуд:
                $source = explode(':', stream_socket_get_name($this->clients[$client], true));
                $ip = $source[0];
                $time = time();
                if (isset($this->ips[$ip]) && $this->ips[$ip] == $time) {
                    return;
                } else {
                    $this->ips[$ip] = $time;
                }

                $message = $login . ': ' . strip_tags($data['payload']);
                $this->sendToClients('message', $message);
            }

        } else {
            if (preg_match('/^[a-zA-Z0-9]{1,10}$/', $data['payload'], $match)) {
                if (isset($this->logins[$match[0]])) {
                    $this->sendToClient($client, 'message', 'Система: выбранное вами имя занято, попробуйте другое.');
                } else {
                    $this->logins[$match[0]] = $client;
                    $this->sendToClient($client, 'message', 'Система: вы вошли в игру под именем ' . $match[0] . '. Для управления танком воспользуйтесь клавишами: вверх, вниз, вправо, влево или w s a d.');
                    $this->tanks[$client] = array('name' => $match[0], 'x' => rand(1, $this->w - 2), 'y' => rand(1, $this->h - 2), 'dir' => 0, 'health' => 0);
                    $this->sendTanks();
                }
            } else {
                $this->sendToClient($client, 'message', 'Система: ошибка при выборе имени. В имени можно использовать английские буквы и цифры. Имя не должно превышать 10 символов.');
            }
        }
    }

    protected function onSend($data) {//вызывается при получении сообщения от мастера

    }

    protected function sendToMaster($cmd, $data) {//отправляем сообщение на мастер, чтобы он разослал его на все воркеры
        $this->write($this->master, $this->pack($cmd, $data), self::SOCKET_MESSAGE_DELIMITER);
    }

    private function sendToClients($cmd, $data) {
        $data = $this->encode($this->pack($cmd, $data));
        foreach ($this->clients as $client) {
            $this->write($client, $data);
        }
    }

    private function sendToClient($client, $cmd, $data) {
        $this->write($client, $this->encode($this->pack($cmd, $data)));
    }

    public function pack($cmd, $data) {
        return json_encode(array('cmd' => $cmd, 'data' => $data));
    }

    protected function sendTanks() {
        foreach ($this->tanks as $client => $tank) {
            $tanks = array($this->tanks[$client]);
            foreach ($this->tanks as $tankId => $tank) {
                if ($tankId != $client) {
                    $tanks[] = $tank;
                }
            }

            $this->sendToClient($client, 'tanks', $tanks);
        }
    }
}