<?php

namespace morozovsk\websocket;

abstract class GenericLibevent
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
    protected $_read = array();//буферы чтения
    protected $_write = array();//буферы заииси
    private $base = null;
    private $event = null;
    private $service_event = null;
    private $master_event = null;
    private $buffers = array();//буферы событий
    public $timer = null;

    public function start() {
        $this->onStart();

        $this->base = event_base_new();

        if ($this->_server) {
            $this->event = event_new();
            event_set($this->event, $this->_server, EV_READ | EV_PERSIST, array($this, 'accept'), $this->base);
            event_base_set($this->event, $this->base);
            event_add($this->event);
        }

        if ($this->_service) {
            $this->service_event = event_new();
            event_set($this->service_event, $this->_service, EV_READ | EV_PERSIST, array($this, 'service'), $this->base);
            event_base_set($this->service_event, $this->base);
            event_add($this->service_event);
        }

        if ($this->_master) {
            $this->master_event = event_new();
            event_set($this->master_event, $this->_master, EV_READ | EV_PERSIST | EV_WRITE, array($this, 'master'), $this->base);
            event_base_set($this->master_event, $this->base);
            event_add($this->master_event);
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

    private function service($socket, $flag, $base) {
        $connection = @stream_socket_accept($socket, 0);
        $connectionId = $this->getIdByConnection($connection);
        stream_set_blocking($connection, 0);
        $buffer = event_buffer_new($connection, array($this, 'onRead'), array($this, 'onWrite'), array($this, 'onError'), $connectionId);
        event_buffer_base_set($buffer, $this->base);
        event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
        event_buffer_priority_set($buffer, 10);
        event_buffer_enable($buffer, EV_READ | EV_WRITE | EV_PERSIST);
        $this->services[$connectionId] = $connection;
        $this->buffers[$connectionId] = $buffer;

        $this->onServiceOpen($connectionId);
    }

    private function master($connection, $flag, $base) {
        $connectionId = $this->getIdByConnection($connection);
        $buffer = event_buffer_new($connection, array($this, 'onRead'), array($this, 'onWrite'), array($this, 'onError'), $connectionId);
        event_buffer_base_set($buffer, $this->base);
        event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
        event_buffer_priority_set($buffer, 10);
        event_buffer_enable($buffer, EV_READ | EV_WRITE | EV_PERSIST);
        $this->buffers[$connectionId] = $buffer;
        event_del($this->master_event);
        event_free($this->master_event);
        unset($this->master_event);
    }

    private function onRead($buffer, $connectionId) {
        if (isset($this->services[$connectionId])) {
            if (is_null($this->_read($connectionId))) { //соединение было закрыто или превышен размер буфера
                $this->close($connectionId);
                return;
            } else {
                while ($data = $this->_readFromBuffer($connectionId)) {
                    $this->onServiceMessage($connectionId, $data); //вызываем пользовательский сценарий
                }
            }
        } elseif ($this->getIdByConnection($this->_master) == $connectionId) {
            if (is_null($this->_read($connectionId))) { //соединение было закрыто или превышен размер буфера
                $this->close($connectionId);
                return;
            } else {
                while ($data = $this->_readFromBuffer($connectionId)) {
                    $this->onMasterMessage($data); //вызываем пользовательский сценарий
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
        @fclose($this->getConnectionById($connectionId));

        event_buffer_disable($this->buffers[$connectionId], EV_READ | EV_WRITE | EV_PERSIST);
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

        if (!strlen($data)) return;

        @$this->_read[$connectionId] .= $data;//добавляем полученные данные в буфер чтения
        return strlen($this->_read[$connectionId]) < self::MAX_SOCKET_BUFFER_SIZE;
    }
}