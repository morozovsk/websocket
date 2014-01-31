<?php

//пример реализации чата
class WebsocketWorkerHandler extends WebsocketWorker
{
    protected $tanks = array();

    protected function onOpen($client) {//вызывается при соединении с новым клиентом
        //$this->write($client, $this->encode('Чтобы общаться в чате введите ник, под которым вы будете отображаться. Можно использовать английские буквы и цифры.'));
        $this->tanks[intval($client)] = array();
    }

    protected function onClose($client) {//вызывается при закрытии соединения клиентом
        unset($this->tanks[intval($client)]);
    }

    protected function onMessage($client, $data) {//вызывается при получении сообщения от клиента
        if (!strlen($data['payload'])) {
            return;
        }

        //var_export($data);
        //шлем всем сообщение, о том, что пишет один из клиентов
        //echo $data['payload'] . "\n";
        $message = ' (' . $this->pid . '): ' . str_replace(self::SOCKET_MESSAGE_DELIMITER, '', $data['payload']);
        //$this->send($message);

        echo $message . "\n";

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
}