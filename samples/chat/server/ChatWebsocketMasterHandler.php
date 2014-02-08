<?php

class ChatWebsocketMasterHandler extends WebsocketMaster
{
    protected function onMessage($connectionId, $data) //вызывается при получении сообщения от скриптов
    {

    }

    protected function onWorkerMessage($connectionId, $data) //вызывается при получении сообщения от воркера
    {
        foreach ($this->workers as $workerId => $worker) { //пересылаем данные во все воркеры
            if ($workerId !== $connectionId) {
                $this->sendToWorker($workerId, $data);
            }
        }
    }
}