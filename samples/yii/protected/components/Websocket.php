<?php

class Websocket extends CApplicationComponent
{
    public $master =
        array(
            'class' => 'GameWebsocketMasterHandler',
            'socket' => 'tcp://127.0.0.1:8001',// unix:///tmp/mysock
            'workers' => 1,
            'pid' => '/tmp/websocket2.pid',
        );

    public $worker = array(
            'socket' => 'tcp://127.0.0.1:8002',
            'class' => 'GameWebsocketWorkerHandler',
            'timer' => 0.1
        );

    protected $instance = null;

    public function getInstance() {
        if (!$this->instance) {
            $this->instance = stream_socket_client ($this->getOwner()->master['socket'], $errno, $errstr);//соединямся с мастер-процессом:
        }
        return $this->instance;
    }

    //можно вызывать из своих скриптов
    //отправляет данные в мастер, который перенаправляет их во все воркеры, которые пересылают клиентам
    public function send($message) {
        return fwrite($this->getInstance(), $message . "\n");
    }
}