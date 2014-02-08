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

    //abstract protected function onMessage($client, $data);
}