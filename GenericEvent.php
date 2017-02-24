<?php

namespace morozovsk\websocket;

abstract class GenericEvent
{
    const SOCKET_BUFFER_SIZE = 1024;
    const MAX_SOCKET_BUFFER_SIZE = 10240;
    const MAX_SOCKETS = 1000;
    const SOCKET_MESSAGE_DELIMITER = "\n";
    protected $clients = array();
    protected $services = array();
    protected $_server = null;
    protected $_service = null;
    protected $_master = null;
    protected $_read = array();//read buffers
    protected $_write = array();//write buffers
    private $base = NULL;
    private $event = NULL;
    private $service_event = NULL;
    private $buffers = array();//event buffers
    public $timer = null;

    public function start() {
        $this->onStart();

        $this->base = new \EventBase();

        if ($this->_server) {
            $this->event = new \EventListener($this->base, array($this, "accept"), $this->base, \EventListener::OPT_CLOSE_ON_FREE | \EventListener::OPT_REUSEABLE, -1, $this->_server);//EventListener($this->base, array($this, "accept"), null, \EventListener::OPT_CLOSE_ON_FREE | \EventListener::OPT_REUSEABLE, -1, $this->_server);
        }

        if ($this->_service) {
            $this->service_event = new \EventListener($this->base, array($this, "service"), $this->base, \EventListener::OPT_CLOSE_ON_FREE | \EventListener::OPT_REUSEABLE, -1, $this->_service);//EventListener($this->base, array($this, "accept"), null, \EventListener::OPT_CLOSE_ON_FREE | \EventListener::OPT_REUSEABLE, -1, $this->_server);
        }

        if ($this->_master) {
            $connectionId = $this->getIdByConnection($this->_master);
            $buffer = new \EventBufferEvent($this->base, $this->_master, \EventBufferEvent::OPT_CLOSE_ON_FREE);
            $buffer->setCallbacks(array($this, "onRead"), array($this, "onWrite"), array($this, "onError"), $connectionId);
            $buffer->enable(\Event::READ | \Event::WRITE | \Event::PERSIST);
            $this->buffers[$connectionId] = $buffer;
        }

        if ($this->timer) {
            $timer = \Event::timer($this->base, function() use (&$timer) {$timer->addTimer($this->timer);$this->onTimer();});
            $timer->addTimer($this->timer);
        }

        $this->base->dispatch();
    }

    public function accept($listener, $connectionId, $address, $id) {
        $buffer = new \EventBufferEvent($this->base, $connectionId, \EventBufferEvent::OPT_CLOSE_ON_FREE);
        $buffer->setCallbacks(array($this, "onRead"), array($this, "onWrite"), array($this, "onError"), $connectionId);
        $buffer->enable(\Event::READ | \Event::WRITE | \Event::PERSIST);
        $this->clients[$connectionId] = $connectionId;
        $this->buffers[$connectionId] = $buffer;

        $this->_onOpen($connectionId);
    }

    public function service($listener, $connectionId, $address, $id) {
        $buffer = new \EventBufferEvent($this->base, $connectionId, \EventBufferEvent::OPT_CLOSE_ON_FREE);
        $buffer->setCallbacks(array($this, "onRead"), array($this, "onWrite"), array($this, "onError"), $connectionId);
        $buffer->enable(\Event::READ | \Event::WRITE | \Event::PERSIST);
        $this->services[$connectionId] = $connectionId;
        $this->buffers[$connectionId] = $buffer;

        $this->onServiceOpen($connectionId);
    }

    public function onRead($buffer, $connectionId) {
        if (isset($this->services[$connectionId])) {
            if (is_null($this->_read($connectionId))) { //connection has been closed
                $this->close($connectionId);
                return;
            } else {
                while ($data = $this->_readFromBuffer($connectionId)) {
                    $this->onServiceMessage($connectionId, $data); //call user handler
                }
            }
        } elseif ($this->getIdByConnection($this->_master) == $connectionId) {
            if (is_null($this->_read($connectionId))) { //connection has been closed or the buffer was overwhelmed
                $this->close($connectionId);
                return;
            } else {
                while ($data = $this->_readFromBuffer($connectionId)) {
                    $this->onMasterMessage($data); //call user handler
                }
            }
        } else {
            if (!$this->_read($connectionId)) { //connection has been closed or the buffer was overwhelmed
                $this->close($connectionId);
            } else {
                $this->_onMessage($connectionId);
            }
        }
    }

    public function onWrite($buffer, $connectionId) {

    }

    public function onError($buffer, $error, $connectionId) {
        //echo "Connection closed: $connectionId\n";
        $this->close($connectionId);
    }

    protected function close($connectionId) {
        //fclose($this->getConnectionById($connectionId));

        $this->buffers[$connectionId]->disable(\Event::READ | \Event::WRITE);
        unset($this->buffers[$connectionId]);
    }

    protected function _write($connectionId, $data, $delimiter = '') {
        $this->buffers[$connectionId]->write($data . $delimiter);
    }

    protected function _readFromBuffer($connectionId) {
        $data = '';

        if (false !== ($pos = strpos($this->_read[$connectionId], self::SOCKET_MESSAGE_DELIMITER))) {
            $data = substr($this->_read[$connectionId], 0, $pos);
            $this->_read[$connectionId] = substr($this->_read[$connectionId], $pos + strlen(self::SOCKET_MESSAGE_DELIMITER));
        }

        return $data;
    }

    protected function _read($connectionId) {
        $length = 0;
        while ($data = $this->buffers[$connectionId]->read(self::SOCKET_BUFFER_SIZE)) {//add the data into the read buffer
            @$this->_read[$connectionId] .= $data;
            $length += strlen($data);
        }
        return $length > 0 && strlen($this->_read[$connectionId]) < self::MAX_SOCKET_BUFFER_SIZE;
    }
}
