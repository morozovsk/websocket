<?php

abstract class WebsocketGeneric
{
    const SOCKET_BUFFER_SIZE = 1024;
    const MAX_SOCKET_BUFFER_SIZE = 10240;
    const MAX_SOCKETS = 1000;
    const SOCKET_MESSAGE_DELIMITER = "\n";
    protected $clients = array();
    protected $_server = null;
    protected $_services = array();
    protected $_read = array();//буферы чтения
    protected $_write = array();//буферы заииси
    private $base = NULL;
    private $event = NULL;
    private $buffers = array();//буферы событий
    private $events = array();
    public $timer = null;

    public function start() {
        $this->onStart();

        $this->base = new EventBase();

        if ($this->_server) {
            $this->event = new EventListener($this->base, array($this, "accept"), $this->base, EventListener::OPT_CLOSE_ON_FREE | EventListener::OPT_REUSEABLE, -1, $this->_server);//EventListener($this->base, array($this, "accept"), null, EventListener::OPT_CLOSE_ON_FREE | EventListener::OPT_REUSEABLE, -1, $this->_server);
        }

        foreach ($this->_services as $serviceId => $service) {
            $buffer = new EventBufferEvent($this->base, $service, EventBufferEvent::OPT_CLOSE_ON_FREE);
            $buffer->setCallbacks(array($this, "onRead"), array($this, "onWrite"), array($this, "onError"), $serviceId);
            $buffer->enable(Event::READ | Event::WRITE | Event::PERSIST);
            $this->clients[$serviceId] = $service;
            $this->buffers[$serviceId] = $buffer;
        }

        if ($this->timer) {
            $timer = Event::timer($this->base, function() use (&$timer) {$timer->addTimer($this->timer);$this->onTimer();});
            $timer->addTimer($this->timer);
        }

        $this->base->dispatch();
    }

    public function accept($listener, $connection, $address, $id) {
        $connectionId = $this->getIdByConnection($connection);
        $buffer = new EventBufferEvent($this->base, $connection, EventBufferEvent::OPT_CLOSE_ON_FREE);
        $buffer->setCallbacks(array($this, "onRead"), array($this, "onWrite"), array($this, "onError"), $connectionId);
        $buffer->enable(Event::READ | Event::WRITE | Event::PERSIST);
        $this->clients[$connectionId] = $connection;//var_dump($connection);
        $this->buffers[$connectionId] = $buffer;

        $this->_onOpen($connectionId);
    }

    public function onRead($buffer, $connectionId) {
        if (in_array($connectionId, $this->_services)) {
            if (!$this->_read($connectionId)) { //соединение было закрыто или превышен размер буфера
                $this->close($connectionId);
                return;
            } else {
                while ($data = $this->_readFromBuffer($connectionId)) {
                    $this->_onService($connectionId, $data); //вызываем пользовательский сценарий
                }
            }
        } else {
            if (!$this->_read($connectionId)) { //соединение было закрыто или превышен размер буфера
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

        if (isset($this->clients[$connectionId])) {
            unset($this->clients[$connectionId]);
        } elseif (isset($this->_services[$connectionId])) {
            unset($this->_services[$connectionId]);
            if (!$this->_services) {
                exit();
            }
        } elseif($this->getConnectionById($connectionId) == $this->_server) {
            /*unset($this->_server);
            event_del($this->event);
            event_free($this->event);
            exit();*/
        }

        unset($this->_write[$connectionId]);
        unset($this->_read[$connectionId]);

        $this->buffers[$connectionId]->disable(Event::READ | Event::WRITE);
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
        $data = $this->buffers[$connectionId]->read(self::SOCKET_BUFFER_SIZE);

        if (!strlen($data)) return false;

        @$this->_read[$connectionId] .= $data;//добавляем полученные данные в буфер чтения
        return strlen($this->_read[$connectionId]) < self::MAX_SOCKET_BUFFER_SIZE;
    }

    protected function getConnectionById($connectionId) {
        return isset($this->clients[$connectionId]) ? $this->clients[$connectionId] :
            (isset($this->_services[$connectionId]) ? $this->_services[$connectionId] : $this->_server);
    }

    protected function getIdByConnection($connection) {
        return intval($connection);
    }

    abstract protected function _onMessage($connectionId);

    abstract protected function _onService($connectionId, $data);

    abstract protected function _onOpen($connectionId);
}