#!/usr/bin/env php
<?php

$config = array(
    'websocket' => 'tcp://127.0.0.1:8001',
);

require_once('../../../WebsocketTest.php');

$WebsocketClient = new WebsocketTest($config);
$WebsocketClient->start();