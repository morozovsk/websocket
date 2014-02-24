<?php

//пример реализации чата
class ChatWebsocketWorkerHandler extends WebsocketWorker
{
    protected function onOpen($connectionId) {//вызывается при соединении с новым клиентом

    }

    protected function onClose($connectionId) {//вызывается при закрытии соединения клиентом

    }

    protected function onMessage($connectionId, $data, $type) {//вызывается при получении сообщения от клиента
        if (!strlen($data) || !mb_check_encoding($data, 'utf-8')) {
            return;
        }

        //var_export($data);
        //шлем всем сообщение, о том, что пишет один из клиентов
        //echo $data . "\n";
        $message = 'пользователь #' . $connectionId . ' (' . $this->pid . '): ' . strip_tags($data);
        $this->sendToMaster($message);

        foreach ($this->clients as $clientId => $client) {
            $this->sendToClient($clientId, $message);
        }
    }

    protected function onMasterMessage($data) {//вызывается при получении сообщения от мастера
        foreach ($this->clients as $clientId => $client) {
            $this->sendToClient($clientId, $data);
        }
    }
}