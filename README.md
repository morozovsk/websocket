Simple websocket server on php that can handle 100000 connections or more.

"composer require morozovsk/websocket"

###This library works, but I will not develop and support it because I will use https://github.com/walkor/Workerman

#####Features:
* server works with socket_select, pecl/event or pecl/libevent.
* you can run multiple processes (one master and several workers or microservices architecture).
* integration with your framework.

#####Documentation https://github.com/morozovsk/websocket/wiki

#####Examples https://github.com/morozovsk/websocket-examples
* chat - simple chat (single daemon) (live demo: http://sharoid.ru/chat.html )
* chat2 - chat (master + worker) (live demo: http://sharoid.ru/chat2.html )
* chat3 - chat (single daemon + script who can send personal message by clientId, userId or PHPSESSID)
* game - simple real time game (live demo: http://sharoid.ru/game.html )
* game2 - real time game (<strike>live demo: http://sharoid.ru/game2.html</strike> ) (port of node.js game: https://github.com/amikstrike/wn)

Run from console:
* start: "php index.php start" or "nohup php index.php start &"
* stop: "php index.php stop"
* restart: "php index.php restart" or "nohup php index.php restart &"

#####yii2-websocket https://github.com/morozovsk/yii2-websocket

#####How it works (for russian speakers):
* http://habrahabr.ru/company/ifree/blog/209864/
* http://habrahabr.ru/company/ifree/blog/210228/
* http://habrahabr.ru/company/ifree/blog/211504/

#####Fork for Laravel 4:
* https://github.com/Cherry-Pie/websocket

#####License

(The MIT License)

Copyright (c) 2014 Vladimir Goncharov

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the 'Software'), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
