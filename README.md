Simple websocket server on php.
Запуск происходит из консоли.

###Features:
* server works with socket_select and does not require libevent.
* you can run multiple processes on single port (one master and several workers).
* integration with your framework.

Examples directory:
* chat - simple chat (live demo: http://sharoid.ru/chat.html )
* yii - sample of use yii with websockets: Yii::app()->websocket->send('Hello world');

Run from console:
* start: "php index.php start" or "nohup php index.php start &"
* stop: "php index.php stop"
* restart: "php index.php restart" or "nohup php index.php restart &"

About:
* http://habrahabr.ru/company/ifree/blog/209864/
* http://habrahabr.ru/company/ifree/blog/210228/
