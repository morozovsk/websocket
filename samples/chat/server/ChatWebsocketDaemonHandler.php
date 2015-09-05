<?php

namespace morozovsk\websocket\samples;

//пример реализации чата
class ChatWebsocketDaemonHandler extends \morozovsk\websocket\Daemon
{
    protected function onOpen($connectionId, $info) {//вызывается при соединении с новым клиентом

    }

    protected function onClose($connectionId) {//вызывается при закрытии соединения с существующим клиентом

    }

    protected function onMessage($connectionId, $data, $type) {//вызывается при получении сообщения от клиента
        if (!strlen($data)) {
            return;
        }

        //var_export($data);
        //шлем всем сообщение, о том, что пишет один из клиентов
        //echo $data . "\n";
        $message = 'пользователь #' . $connectionId . ' (' . $this->pid . '): ' . strip_tags($data);

        foreach ($this->clients as $clientId => $client) {
            $this->sendToClient($clientId, $message);
        }
    }
}