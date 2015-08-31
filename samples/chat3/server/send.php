#!/usr/bin/env php
<?php
//можно вызывать из своих скриптов
//отправляет данные на вебсокет-сервер, который перенаправляет их на все клиенты

$localsocket = 'tcp://127.0.0.1:8010';
$message = 'test';

$instance = stream_socket_client ($localsocket, $errno, $errstr);//соединямся с вебсокет-сервером

fwrite($instance, json_encode(['message' => $message, 'userId' => 5204])  . "\n");//отправляем сообщение
//fwrite($instance, json_encode(['message' => $message, 'clientId' => 12])  . "\n");//отправляем сообщение
//fwrite($instance, json_encode(['message' => $message, 'PHPSESSID' => '4sk3sgqf1lqbjC2litl75db142'])  . "\n");//отправляем сообщение