<?php

class WebsocketServer
{
    public function __construct($config) {
        $this->config = $config;
    }

    public function start() {
        $pid = @file_get_contents($this->config['pid']);
        if ($pid) {
            die("already started\r\n");
        }
        //открываем серверный сокет
        $server = stream_socket_server($this->config['websocket'], $errorNumber, $errorString);

        if (!$server) {
            die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
        }

        list($pid, $master, $workers) = $this->spawnWorkers();//создаём дочерние процессы

        if ($pid) {//мастер
            file_put_contents($this->config['pid'], $pid);
            fclose($server);//мастер не будет обрабатывать входящие соединения на основном сокете
            $WebsocketMaster = new WebsocketMasterHandler($workers, $this->config['localsocket']);//мастер будет обрабатывать сообщения от скриптов и пересылать их в воркеры
            $WebsocketMaster->start();
        } else {//воркер
            $WebsocketHandler = new WebsocketWorkerHandler($server, $master);
            $WebsocketHandler->start();
        }
    }

    protected function spawnWorkers() {
        $master = null;
        $workers = array();

        for ($i=0; $i<$this->config['workers']; $i++) {
            //создаём парные сокеты, через них будут связываться мастер и воркер
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

            $pid = pcntl_fork();//создаём форк
            if ($pid == -1) {
                die("error: pcntl_fork\r\n");
            } elseif ($pid) { //мастер
                fclose($pair[0]);
                $workers[$pid] = $pair[1];//один из пары будет в мастере
            } else { //воркер
                fclose($pair[1]);
                $master = $pair[0];//второй в воркере
                break;
            }
        }

        return array($pid, $master, $workers);
    }

    public function stop() {
        $pid = @file_get_contents($this->config['pid']);
        if ($pid) {
            posix_kill($pid, SIGTERM);
            unlink($this->config['pid']);
        } else {
            die("already stopped\r\n");
        }
    }
}