<?php

class WebsocketMasterHandler extends WebsocketMaster
{
    protected function onWorkerMessage($connectionId, $data) //вызывается при получении сообщения от воркера или скриптов
    {
        foreach ($this->workers as $workerId => $worker) { //пересылаем данные во все воркеры
            if ($workerId !== $connectionId) {
                $this->sendToWorker($worker, $data);
            }
        }
    }

    protected function onMessage($connectionId, $data) {

    }
}