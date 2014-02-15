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
        $this->base = event_base_new();

        if ($this->_server) {
            $this->event = event_new();
            event_set($this->event, $this->_server, EV_READ | EV_PERSIST, array($this, 'accept'), $this->base);
            event_base_set($this->event, $this->base);
            event_add($this->event);
        }

        foreach ($this->_services as $serviceId => $service) {
            $event = event_new();
            event_set($event, $service, EV_READ | EV_PERSIST | EV_WRITE, array($this, 'service'), $this->base);
            event_base_set($event, $this->base);
            event_add($event);
            $this->events[$serviceId] = $event;
        }

        if ($this->timer) {
            $timer = event_timer_new();
            event_timer_set($timer, array($this, '_onTimer'), $timer);
            event_base_set($timer, $this->base);
            //event_timer_pending($timer, $this->timer * 1000000);
            event_timer_add($timer, $this->timer * 1000000);
        }


        event_base_loop($this->base);
    }

    private function _onTimer($connection, $flag, $timer) {
        event_timer_add($timer, $this->timer * 1000000);
        $this->onTimer();
    }

    private function service($connection, $flag, $base) {
        $connectionId = $this->getIdByConnection($connection);
        $buffer = event_buffer_new($connection, array($this, 'onRead'), array($this, 'onWrite'), array($this, 'onError'), $connectionId);
        event_buffer_base_set($buffer, $this->base);
        event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
        event_buffer_priority_set($buffer, 10);
        event_buffer_enable($buffer, EV_READ | EV_WRITE | EV_PERSIST);
        $this->buffers[$connectionId] = $buffer;
        event_del($this->events[$connectionId]);
        event_free($this->events[$connectionId]);
        unset($this->events[$connectionId]);
    }

    private function accept($socket, $flag, $base) {
        $connection = @stream_socket_accept($socket, 0);
        $connectionId = $this->getIdByConnection($connection);
        stream_set_blocking($connection, 0);
        $buffer = event_buffer_new($connection, array($this, 'onRead'), array($this, 'onWrite'), array($this, 'onError'), $connectionId);
        event_buffer_base_set($buffer, $this->base);
        event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
        event_buffer_priority_set($buffer, 10);
        event_buffer_enable($buffer, EV_READ | EV_WRITE | EV_PERSIST);
        $this->clients[$connectionId] = $connection;
        $this->buffers[$connectionId] = $buffer;

        $this->_onOpen($connectionId);
    }

    private function onRead($buffer, $connectionId) {
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

    private function onWrite($buffer, $connectionId) {

    }

    private function onError($buffer, $error, $connectionId) {
        //echo "Connection closed: $connectionId\n";
        //var_dump($error);
        $this->close($connectionId);
    }

    protected function close($connectionId) {
        fclose($this->getConnectionById($connectionId));

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

        event_buffer_disable($this->buffers[$connectionId], EV_READ | EV_WRITE);
        event_buffer_free($this->buffers[$connectionId]);
        unset($this->buffers[$connectionId]);
    }

    protected function _write($connectionId, $data, $delimiter = '') {
        event_buffer_write($this->buffers[$connectionId], $data . $delimiter);
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
        $data = event_buffer_read($this->buffers[$connectionId], self::SOCKET_BUFFER_SIZE);

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