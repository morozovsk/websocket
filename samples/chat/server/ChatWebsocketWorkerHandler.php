<?php

//пример реализации чата
class ChatWebsocketWorkerHandler extends WebsocketWorker
{
    protected function onOpen($connectionId) {//вызывается при соединении с новым клиентом
        //$this->write($connectionId, $this->encode('Чтобы общаться в чате введите ник, под которым вы будете отображаться. Можно использовать английские буквы и цифры.'));
    }

    protected function onClose($connectionId) {//вызывается при закрытии соединения клиентом

    }

    protected function onMessage($connectionId, $data) {//вызывается при получении сообщения от клиента
        if (!strlen($data['payload']) || !mb_check_encoding($data['payload'], 'utf-8')) {
            return;
        }

        //var_export($data);
        //шлем всем сообщение, о том, что пишет один из клиентов
        //echo $data['payload'] . "\n";
        $message = 'пользователь #' . $connectionId . ' (' . $this->pid . '): ' . str_replace(self::SOCKET_MESSAGE_DELIMITER, '', strip_tags($data['payload']));
        $this->sendToMaster($message);

        foreach ($this->clients as $clientId => $client) {
            $this->sendToClient($clientId, $data);
        }
    }

    protected function onMasterMessage($data) {//вызывается при получении сообщения от мастера
        foreach ($this->clients as $clientId => $client) {
            $this->sendToClient($clientId, $data);
        }
    }
}