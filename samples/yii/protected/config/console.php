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
        ),
    ),
);