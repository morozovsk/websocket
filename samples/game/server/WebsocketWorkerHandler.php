<?php

//пример реализации чата
class WebsocketWorkerHandler extends WebsocketWorker
{
    protected $tanks = array();

    protected $h = 60;
    protected $w = 100;
    protected $tankmodelsize = 3;

    protected function onOpen($client) {//вызывается при соединении с новым клиентом
        //$this->write($client, $this->encode('Чтобы общаться в чате введите ник, под которым вы будете отображаться. Можно использовать английские буквы и цифры.'));
        $this->tanks[intval($client)] = array(
            'x' => rand(1, $this->w - 2),
            'y' => rand(1, $this->h - 2),
            'dir' => 0,
            'name' => 'qwe',
            'health' => rand(10, 100)
        );

        $this->sendTanks();
    }

    protected function onClose($client) {//вызывается при закрытии соединения клиентом
        unset($this->tanks[intval($client)]);
    }

    protected function onMessage($client, $data) {//вызывается при получении сообщения от клиента
        if (!strlen($data['payload'])) {
            return;
        }

        $clientId = intval($client);
        $tank = json_decode($data['payload'], true);
        //echo $data['payload'] . "\n";
        //$this->tanks[$clientId] = $tank;
        //var_dump($this->tanks[$clientId]);
        $this->tanks[$clientId]['dir'] = isset($tank['dir']) ? $tank['dir'] : $this->tanks[$clientId]['dir'];

        if (true || $this->tanks[$clientId]['moving']) {
            switch ($this->tanks[$clientId]['dir']) {
                case 0:
                    $this->tanks[$clientId]['y']--;
                    break;
                case 1:
                    $this->tanks[$clientId]['y']++;
                    break;
                case 2:
                    $this->tanks[$clientId]['x']--;
                    break;
                case 3:
                    $this->tanks[$clientId]['x']++;
                    break;
            }

            if ($this->tanks[$clientId]['x'] < 0) {
                $this->tanks[$clientId]['x'] = 0;
                //$this->tanks[$clientId]['moving'] = false;
            }
            if ($this->tanks[$clientId]['y'] < 0) {
                $this->tanks[$clientId]['y'] = 0;
                //$this->tanks[$clientId]['moving'] = false;
            }
            if ($this->tanks[$clientId]['x'] > $this->w - $this->tankmodelsize) {
                $this->tanks[$clientId]['x'] = $this->w - $this->tankmodelsize;
                //$this->tanks[$clientId]['moving'] = false;
            }
            if ($this->tanks[$clientId]['y'] > $this->h - $this->tankmodelsize) {
                $this->tanks[$clientId]['y'] = $this->h - $this->tankmodelsize;
                //$this->tanks[$clientId]['moving'] = false;
            }
        }

        $this->sendTanks();
        //var_export($data);
        //шлем всем сообщение, о том, что пишет один из клиентов
        //echo $data['payload'] . "\n";
        //$message = ' (' . $this->pid . '): ' . str_replace(self::SOCKET_MESSAGE_DELIMITER, '', $data['payload']);
        //$this->send($message);



        //$this->sendHelper($message);
    }

    protected function onSend($data) {//вызывается при получении сообщения от мастера
        $this->sendHelper($data);
    }

    protected function send($message) {//отправляем сообщение на мастер, чтобы он разослал его на все воркеры
        $this->write($this->master, $message, self::SOCKET_MESSAGE_DELIMITER);
    }

    private function sendHelper($data) {
        foreach ($this->clients as $client) {
            $this->write($client, $this->encode($data));
        }
    }

    protected function sendTanks() {
        foreach ($this->clients as $client) {
            $clientId = intval($client);
            $tanks = array($this->tanks[$clientId]);
            foreach ($this->tanks as $tankId => $tank) {
                if ($tankId != $clientId) {
                    $tanks[] = $tank;
                }
            }

            $this->write($client, $this->encode(json_encode($tanks)));
        }
    }
}