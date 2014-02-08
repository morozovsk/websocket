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
        stream_set_blocking($server, 0);

        if (!$server) {
            die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
        }

        $service = null;
        if (!empty($this->config['localsocket'])) {
            //создаём сокет для обработки сообщений от скриптов
            $service = stream_socket_server($this->config['localsocket'], $errorNumber, $errorString);
            stream_set_blocking($service, 0);

            if (!$service) {
                die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
            }
        }

        list($pid, $master, $workers) = $this->spawnWorkers();//создаём дочерние процессы

        if ($pid) {//мастер
            file_put_contents($this->config['pid'], $pid);
            fclose($server);//мастер не будет обрабатывать входящие соединения на основном сокете
            $master = new $this->config['master'] ($service, $workers);//мастер будет обрабатывать сообщения от скриптов и пересылать их в воркеры
            $master->start();
        } else {//воркер
            $worker = new $this->config['worker'] ($server, array($master));
            $worker->start();
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
                $workers[intval($pair[1])] = $pair[1];//один из пары будет в мастере
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
            /*sleep(1);
            posix_kill($pid, SIGKILL);
            sleep(1);
            if ($websocket = @stream_socket_client ($this->config['websocket'], $errno, $errstr)) {
                stream_socket_shutdown($websocket, STREAM_SHUT_RDWR);
            }

            if (!empty($this->config['localsocket'])) {
                if ($localsocket = stream_socket_client ($this->config['localsocket'], $errno, $errstr)) {
                    stream_socket_shutdown($localsocket, STREAM_SHUT_RDWR);
                }
            }*/
        } else {
            die("already stopped\r\n");
        }
    }

    public function restart() {
        $pid = @file_get_contents($this->config['pid']);
        if ($pid) {
            $this->stop();
        }

        $this->start();
    }
}