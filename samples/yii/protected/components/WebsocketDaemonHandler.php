<?php

//пример реализации чата
class WebsocketDaemonHandler extends WebsocketDaemon
{
    protected function onOpen($connectionId) {//вызывается при соединении с новым клиентом

    }

    protected function onClose($connectionId) {//вызывается при закрытии соединения клиентом

    }

    protected function onMessage($connectionId, $data, $type) {//вызывается при получении сообщения от клиента
        if (!strlen($data)) {
            return;
        }

        $message = 'пользователь #' . $connectionId . ' (' . $this->pid . '): ' . strip_tags($data);

        foreach ($this->clients as $clientId => $client) {
            $this->sendToClient($clientId, $message);
        }
    }

    protected function onServiceMessage($connectionId, $data) {//вызывается при получении сообщения от скриптов
        foreach ($this->clients as $clientId => $client) {
            $this->sendToClient($clientId, $data);
        }
    }
}