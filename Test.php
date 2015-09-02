<?php

namespace morozovsk\websocket;

class Test
{
    public $clients = array();
    public function __construct($config) {
        $this->config = $config;
    }

    public function start() {
        for ($i=0, $j=0; $i<=1500; $i++) {
            $client = @stream_socket_client($this->config['websocket'], $errorNumber, $errorString, 1);

            if ($client) {
                fwrite($client, "GET / HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: tQXaRIOk4sOhgoq7SBs43g==\r\nSec-WebSocket-Version: 13\r\n\r\n");

                $this->clients[$i] = $client;
                if ($i && $i % 100 == 0) echo "success: $i, failure: $j\r\n";
            } else {
                $i--;
                $j++;
                if ($j && $j % 100 == 0) echo "success: $i, failure: $j\r\n";
            }
        }

        while (true) {
            //подготавливаем массив всех сокетов, которые нужно обработать
            $read = $this->clients;
            //$read[] = $service;

            stream_select($read, $write, $except, null);//обновляем массив сокетов, которые можно обработать

            if ($read) {//пришли данные от подключенных клиентов
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
            }
        }
    }
}