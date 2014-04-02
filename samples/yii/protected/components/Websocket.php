<?php

class Websocket extends CApplicationComponent
{
    public $class = 'ChatWebsocketDaemonHandler';
    public $pid = '/tmp/websocket_chat.pid';
    public $websocket = 'tcp://127.0.0.1:8000';
    public $localsocket = 'tcp://127.0.0.1:8010';
    public $master = '';//tcp://127.0.0.1:8020
    public $eventDriver = 'event';

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
        return fwrite($this->getInstance(), $message . "\n");
    }
}