<?php

class Websocket extends CApplicationComponent
{
    public $websocket = 'tcp://127.0.0.1:8000';
    public $localsocket = 'tcp://127.0.0.1:8001'; // unix:///tmp/websocket.sock
    public $workers = 1;
    public $pid = '/tmp/websocket.pid';
    public $master = 'WebsocketMasterHandler';
    public $worker = 'WebsocketWorkerHandler';

    protected $instance = null;

    public function getInstance() {
        if (!$this->instance) {
            $this->instance = stream_socket_client ($this->getOwner()->localsocket, $errno, $errstr);//соединямся с мастер-процессом:
        }
        return $this->instance;
    }

    //можно вызывать из своих скриптов
    //отправляет данные в мастер, который перенаправляет их во все воркеры, которые пересылают клиентам
    public function send($message) {
        return fwrite($this->getInstance(), $message);
    }
}