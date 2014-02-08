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

    protected function _onMessage($connectionId) {
        while ($data = $this->readFromBuffer($connectionId)) {
            $this->onMessage($connectionId, $data);
        }
    }

    protected function _onService($connectionId, $data) {
        $this->onWorkerMessage($connectionId, $data);
    }

    protected function _onOpen($connectionId) {

    }

    protected function close($connectionId) {
        if (isset($this->workers[$connectionId])) {
            unset($this->workers[$connectionId]);
        }
        //$this->onClose($connectionId);//вызываем пользовательский сценарий
    }

    public function sendToClient($connectionId, $data) {
        parent::write($connectionId, $data, self::SOCKET_MESSAGE_DELIMITER);
    }

    public function sendToWorker($connectionId, $data) {
        parent::write($connectionId, $data, self::SOCKET_MESSAGE_DELIMITER);
    }

    abstract protected function onMessage($connectionId, $data);

    //abstract protected function onClose($client);

    abstract protected function onWorkerMessage($connectionId, $data);
}