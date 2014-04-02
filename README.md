Simple websocket server on php.

###Features:
* server works with socket_select and does not require libevent.
* you can run multiple processes on single port (one master and several workers).
* integration with your framework.

Examples directory:
* chat - simple chat (live demo: http://sharoid.ru/chat.html )
* yii - sample of use yii with websockets: Yii::app()->websocket->send('Hello world');
* game - simple game (live demo: http://sharoid.ru/game.html )

Run from console:
* start: "php index.php start" or "nohup php index.php start &"
* stop: "php index.php stop"
* restart: "php index.php restart" or "nohup php index.php restart &"

About:
* http://habrahabr.ru/company/ifree/blog/209864/
* http://habrahabr.ru/company/ifree/blog/210228/

###Live demos:

###License

(The MIT License)

Copyright (c) 2014 Vladimir Goncharov

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the 'Software'), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
