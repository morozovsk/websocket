<?php

class WebsocketTest
{
    public $clients = array();
    public function __construct($config) {
        $this->config = $config;
    }

    public function start() {
        for ($i=0; $i<=10000; $i++) {
            $client = stream_socket_client ($this->config['websocket'], $errorNumber, $errorString, -1);

            if (!$client) {
                die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
            }

            fwrite($client, "GET ws://yii.local:8000/ HTTP/1.1\r\nPragma: no-cache\r\nOrigin: http://yii.local\r\nHost: yii.local:8000\r\nSec-WebSocket-Key: tQXaRIOk4sOhgoq7SBs43g==\r\nUser-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/30.0.1599.114 Chrome/30.0.1599.114 Safari/537.36\r\nUpgrade: websocket\r\nSec-WebSocket-Extensions: x-webkit-deflate-frame\r\nCache-Control: no-cache\r\nConnection: Upgrade\r\nSec-WebSocket-Version: 13\r\n\r\n");

            $this->clients[$i] = $client;
            if ($i && $i % 100 == 0) echo "$i\r\n";
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

    public function stop() {

    }
}