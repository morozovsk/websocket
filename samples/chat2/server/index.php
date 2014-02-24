#!/usr/bin/env php
<?php

if (empty($argv[1]) || !in_array($argv[1], array('start', 'stop', 'restart'))) {
    die("не указан параметр (start|stop|restart)\r\n");
}

$config = array(
    'master' => array(
        'class' => 'Chat2WebsocketMasterHandler',
        //'socket' => 'unix:///tmp/chat2_socket',
        'workers' => 1,
        'pid' => '/tmp/websocket_chat2.pid',
    ),
    'worker' => array(
        'socket' => 'tcp://127.0.0.1:8001',
        'class' => 'Chat2WebsocketWorkerHandler',
    ),
);

set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../../../'));

spl_autoload_register(function ($class) { include $class . '.php'; });

$WebsocketServer = new WebsocketServer($config);
call_user_func(array($WebsocketServer, $argv[1]));