<?php

return array(
//...
    // autoloading model and component classes
    'import'=>array(
        'ext.websocket.*',
    ),
//...
    // application components
    'components'=>array(
        //...
        'websocket' => array(
            'class' => 'Websocket',
            /*'master' => array(
                'class' => 'WebsocketMasterHandler',
                'socket' => 'tcp://127.0.0.1:8001',// unix:///tmp/mysock
                'workers' => 1,
                'pid' => '/tmp/websocket2.pid',
            ),*/
            /*'worker' => array(
                'socket' => 'tcp://127.0.0.1:8000',
                'class' => 'WebsocketWorkerHandler'
            )*/
        ),
    ),
);