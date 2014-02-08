<?php

abstract class WebsocketMaster extends WebsocketGeneric
{
    protected $workers = array();

    public function __construct($service, $workers) {
        $this->services = $this->workers = $workers;
        $this->server = $service;
    }

    public function stop() {
        /*foreach ($this->workers as $pid => $worker) {
            posix_kill($pid, SIGTERM);
        }*/

        //exit();
    }

    protected function _onMessage($client) {
        while ($data = $this->read($client)) {
            $this->onMessage($client, $data);
        }
    }

    protected function _onService($client, $data) {
        $this->onWorkerMessage($client, $data);
    }

    protected function close($client) {
        if (isset($this->workers[$client])) {
            unset($this->workers[$client]);
        }
        //$this->onClose($client);//вызываем пользовательский сценарий
    }

    abstract protected function onMessage($client, $data);

    //abstract protected function onClose($client);

    abstract protected function onWorkerMessage($client, $data);
}