<?php

class WebsocketCommand extends CConsoleCommand
{
    public function actionStart()
    {
        $WebsocketServer = new WebsocketServer( (array) Yii::app()->websocket);
        $WebsocketServer->start();
    }

    public function actionStop()
    {
        $WebsocketServer = new WebsocketServer( (array) Yii::app()->websocket);
        $WebsocketServer->stop();
    }

    public function actionRestart()
    {
        $WebsocketServer = new WebsocketServer(Yii::app()->websocket);
        $WebsocketServer->start();
        $WebsocketServer->stop();
    }

    public function actionTest()
    {
        $WebsocketClient = new WebsocketTest(Yii::app()->websocket);
        $WebsocketClient->start();
    }
}