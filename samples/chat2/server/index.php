#!/usr/bin/env php
<?php

if (empty($argv[1]) || !in_array($argv[1], array('start', 'stop', 'restart'))) {
    die("не указан параметр (start|stop|restart)\r\n");
}

$config = array(
    'websocket' => 'tcp://127.0.0.1:8001',
    'workers' => 1,
    'pid' => '/tmp/websocket1.pid',
    'master' => 'Chat2WebsocketMasterHandler',
    'worker' => 'Chat2WebsocketWorkerHandler'
);

require_once('../../../WebsocketGeneric.php');
require_once('../../../WebsocketMaster.php');
require_once('../../../WebsocketWorker.php');
require_once('../../../WebsocketServer.php');

require_once('Chat2WebsocketWorkerHandler.php');
require_once('Chat2WebsocketMasterHandler.php');

$WebsocketServer = new WebsocketServer($config);
call_user_func(array($WebsocketServer, $argv[1]));