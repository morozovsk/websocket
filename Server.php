<?php

namespace morozovsk\websocket;

class Server
{
    public function __construct($config) {
        $this->config = $config;
    }

    public function start() {
        $pid = @file_get_contents($this->config['pid']);
        if ($pid) {
            if (posix_getpgid($pid)) {
                die("already started\r\n");
            } else {
                unlink($this->config['pid']);
            }
        }

        if (empty($this->config['websocket']) && empty($this->config['localsocket']) && empty($this->config['master'])) {
            die("error: config: !websocket && !localsocket && !master\r\n");
        }

        $server = $service = $master = null;

        if (!empty($this->config['websocket'])) {
            //открываем серверный сокет
            $server = stream_socket_server($this->config['websocket'], $errorNumber, $errorString);
            stream_set_blocking($server, 0);

            if (!$server) {
                die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
            }
        }

        if (!empty($this->config['localsocket'])) {
            //создаём сокет для обработки сообщений от скриптов
            $service = stream_socket_server($this->config['localsocket'], $errorNumber, $errorString);
            stream_set_blocking($service, 0);

            if (!$service) {
                die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
            }
        }

        if (!empty($this->config['master'])) {
            //создаём сокет для обработки сообщений от скриптов
            $master = stream_socket_client($this->config['master'], $errorNumber, $errorString);
            stream_set_blocking($master, 0);

            if (!$master) {
                die("error: stream_socket_client: $errorString ($errorNumber)\r\n");
            }
        }

        if (!empty($this->config['eventDriver']) && $this->config['eventDriver'] == 'libevent') {
            class_alias('morozovsk\websocket\GenericLibevent', 'morozovsk\websocket\Generic');
        } elseif (!empty($this->config['eventDriver']) && $this->config['eventDriver'] == 'event') {
            class_alias('morozovsk\websocket\GenericEvent', 'morozovsk\websocket\Generic');
        } else {
            class_alias('morozovsk\websocket\GenericSelect', 'morozovsk\websocket\Generic');
        }

        file_put_contents($this->config['pid'], posix_getpid());

        //list($pid, $master, $workers) = $this->spawnWorkers();//создаём дочерние процессы

        /*if ($pid) {//мастер
            file_put_contents($this->config['pid'], $pid);
            fclose($server);//мастер не будет обрабатывать входящие соединения на основном сокете
            $masterClass = $this->config['master']['class'];
            $master = new $masterClass ($service, $workers);//мастер будет обрабатывать сообщения от скриптов и пересылать их в воркеры
            if (!empty($this->config['master']['timer'])) {
                $master->timer = $this->config['worker']['timer'];
            }
            $master->start();
        } else {//воркер*/
            $workerClass = $this->config['class'];
            $worker = new $workerClass ($server, $service, $master);
            if (!empty($this->config['timer'])) {
                $worker->timer = $this->config['timer'];
            }
            $worker->start();
        //}
    }

    /*protected function spawnWorkers() {
        $master = null;
        $workers = array();

        for ($i=0; $i<$this->config['master']['workers']; $i++) {
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
    }*/

    public function stop() {
        $pid = @file_get_contents($this->config['pid']);
        if ($pid) {
            posix_kill($pid, SIGTERM);
            for ($i=0;$i=10;$i++) {
                sleep(1);

                if (!posix_getpgid($pid)) {
                    unlink($this->config['pid']);
                    return;
                }
            }

            die("don't stopped\r\n");
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