<?php

return array(
//...
    // application components
    'components'=>array(
        //...
        'websocket' => array(
            'class' => 'Websocket',
            //'websocket' => 'tcp://127.0.0.1:8000',
            //'localsocket' => 'tcp://127.0.0.1:8001',// unix:///tmp/mysock
            //'workers' => 1
        ),
    ),
);