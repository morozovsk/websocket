<?php

class GameWebsocketMasterHandler extends WebsocketMaster
{
    protected function onMessage($connectionId, $data) //вызывается при получении сообщения от скриптов
    {

    }

    protected function onWorkerMessage($connectionId, $data) //вызывается при получении сообщения от воркера
    {
        /*while (true) {
            foreach ($this->workers as $workerId => $worker) { //пересылаем данные во все воркеры
                $this->sendToWorker($workerId, $data);
                $this->_sendBuffer($this->getConnectionById($workerId));
            }
            usleep(50000);
        }*/
    }
}