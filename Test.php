<?php

namespace morozovsk\websocket;

class Test
{
    public $clients = array();
    public function __construct($config) {
        $this->config = $config;
    }

    public function start() {
        for ($i=0; $i < $this->config['workers']-1; $i++) {
            $pid = pcntl_fork();//create the fork
            if ($pid == -1) {
                die("error: pcntl_fork\r\n");
            } elseif (!$pid) {//worker
                break;
            }
        }

        for ($i=0, $j=0; $i<=$this->config['clients']; $i++) {
            $client = @stream_socket_client($this->config['websocket'], $errorNumber, $errorString, 1);

            if ($client) {
                fwrite($client, "GET / HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: tQXaRIOk4sOhgoq7SBs43g==\r\nSec-WebSocket-Version: 13\r\n\r\n");

                $this->clients[$i] = $client;
                if ($i && $i % 100 == 0) echo "success: $i, failure: $j\r\n";
            } else {
                $i--;
                $j++;
                if ($j && $j % 100 == 0) echo "success: $i, failure: $j\r\n";
                if ($j == $this->config['clients']) break;
            }
        }

        while (true) {
            //prepare an array of sockets that need to be processed
            $read = array_slice($this->clients, 0, 1000);

            stream_select($read, $write, $except, null);//update the array of sockets that can be processed

            if ($read) {//obtained data from connected clients
                foreach ($read as $client) {
                    $data = fread($client, 100000);

                    if (!$data) { //соединение было закрыто
                        unset($this->clients[array_search($client, $this->clients)]);
                        @fclose($client);
                        continue;
                    }

                    //echo $data . "\n";

                    /*if (!rand(0, 10000)) {
                        fwrite($this->clients[rand(0, 1000)], $data);
                    }*/
                }
            } else {
                break;
            }
        }
    }
}

if (!empty($argv[1]) && $argv[1] == 'start' &&
    !empty($argv[2]) &&
    !empty($argv[3]) && $argv[3] >= 1 &&
    !empty($argv[4]) && $argv[4] >= 1
) {
    $config = [
        'websocket' => $argv[2],
        'clients' => intval($argv[3]),
        'workers' => intval($argv[4]),
    ];
    (new Test($config))->start();
}
