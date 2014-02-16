#!/usr/bin/env php
<?php

if (empty($argv[1]) || !in_array($argv[1], array('start', 'stop', 'restart'))) {
    die("не указан параметр (start|stop|restart)\r\n");
}

$config = array(
    'master' => array(
        'class' => 'GameWebsocketMasterHandler',
        //'socket' => 'tcp://127.0.0.1:8001',// unix:///tmp/mysock
        'workers' => 1,
        'pid' => '/tmp/websocket2.pid',
    ),
    'worker' => array(
        'socket' => 'tcp://127.0.0.1:8002',
        'class' => 'GameWebsocketWorkerHandler',
        'timer' => 0.05
    ),
);

require_once('../../../WebsocketGenericLibevent.php');//Libevent
require_once('../../../WebsocketMaster.php');
require_once('../../../WebsocketWorker.php');
require_once('../../../WebsocketServer.php');

require_once('GameWebsocketWorkerHandler.php');
require_once('GameWebsocketMasterHandler.php');

$WebsocketServer = new WebsocketServer($config);
call_user_func(array($WebsocketServer, $argv[1]));