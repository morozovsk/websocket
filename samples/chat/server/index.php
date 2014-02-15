#!/usr/bin/env php
<?php

if (empty($argv[1]) || !in_array($argv[1], array('start', 'stop', 'restart'))) {
    die("не указан параметр (start|stop|restart)\r\n");
}

$config = array(
    'master' => array(
        'class' => 'ChatWebsocketMasterHandler',
        //'socket' => 'tcp://127.0.0.1:8001',// unix:///tmp/mysock
        'workers' => 1,
        'pid' => '/tmp/websocket.pid',

    ),
    'worker' => array(
        'socket' => 'tcp://127.0.0.1:8000',
        'class' => 'ChatWebsocketWorkerHandler',
    ),
);

require_once('../../../WebsocketGeneric.php');
require_once('../../../WebsocketMaster.php');
require_once('../../../WebsocketWorker.php');
require_once('../../../WebsocketServer.php');

require_once('ChatWebsocketWorkerHandler.php');
require_once('ChatWebsocketMasterHandler.php');

$WebsocketServer = new WebsocketServer($config);
call_user_func(array($WebsocketServer, $argv[1]));