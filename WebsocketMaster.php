<?php

abstract class WebsocketMaster extends WebsocketGeneric
{
    protected $workers = array();

    public function __construct($workers, $localsocket) {
        $this->clients = $this->workers = $workers;
        $this->localsocket = $localsocket;
    }

    public function start() {
        /*pcntl_signal(SIGTERM, array($this, 'stop'));
        pcntl_signal_dispatch();*/

        //создаём сокет для обработки сообщений от скриптов
        if ($this->localsocket) {
            $service = stream_socket_server($this->localsocket, $errorNumber, $errorString);

            if (!$service) {
                die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
            }
        }

        while (true) {
            //подготавливаем массив всех сокетов, которые нужно обработать
            $read = $this->clients;
            if ($this->localsocket) {
                $read[] = $service;
            }

            $write = array();

            if ($this->write) {
                foreach ($this->write as $clientId => $buffer) {
                    if ($buffer) {
                        $write[] = $this->clients[$clientId];
                    }
                }
            }

            stream_select($read, $write, $except = null, null);//обновляем массив сокетов, которые можно обработать

            if ($this->localsocket && in_array($service, $read)) { //на мастер пришёл запрос от нового клиента
                if ($client = stream_socket_accept($service, -1)) { //подключаемся к нему
                    $this->clients[intval($client)] = $client;
                }

                //удаляем мастера из массива, чтобы не обработать его в этом цикле ещё раз
                unset($read[array_search($service, $read)]);
            }

            if ($read) {//пришли данные от подключенных клиентов
                foreach ($read as $client) {
                    $data = fread($client, self::SOCKET_BUFFER_SIZE);

                    if (!strlen($data)) { //соединение было закрыто
                        unset($this->clients[intval($client)]);
                        unset($this->read[intval($client)]);
                        unset($this->write[intval($client)]);
                        @fclose($client);
                        continue;
                    }

                    $this->addToRead($client, $data);

                    while ($data = $this->read($client)) {
                        $this->onMessage($client, $data);
                    }
                }
            }

            if ($write) {
                foreach ($write as $client) {
                    $this->sendBuffer($client);
                    continue;
                }
            }
        }
    }

    public function stop() {
        /*foreach ($this->workers as $pid => $worker) {
            posix_kill($pid, SIGTERM);
        }*/

        //exit();
    }

    abstract protected function onMessage($client, $data);
}