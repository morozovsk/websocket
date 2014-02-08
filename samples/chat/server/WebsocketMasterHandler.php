<?php

class WebsocketMasterHandler extends WebsocketMaster
{
    protected function onMessage($client, $data) //вызывается при получении сообщения от скриптов
    {

    }

    protected function onWorkerMessage($client, $data) //вызывается при получении сообщения от воркера
    {
        foreach ($this->workers as $worker) { //пересылаем данные во все воркеры
            if ($worker !== $client) {
                $this->write($worker, $data, self::SOCKET_MESSAGE_DELIMITER);
            }
        }
    }
}