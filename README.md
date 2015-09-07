Simple websocket server on php.

"composer require morozovsk/websocket"

#####Features:
* server works with socket_select, pecl/event or pecl/libevent.
* you can run multiple processes (one master and several workers).
* integration with your framework.

#####Examples https://github.com/morozovsk/websocket-examples
* chat - simple chat (single daemon) (live demo: http://sharoid.ru/chat.html )
* chat2 - chat (master + worker) (live demo: http://sharoid.ru/chat2.html )
* chat3 - chat (single daemon + script who can send personal message by clientId, userId or PHPSESSID)
* game - simple game (live demo: http://sharoid.ru/game.html )
* game2 - game (live demo: http://sharoid.ru/game2.html ) (port of node.js game: https://github.com/amikstrike/wn)

Run from console:
* start: "php index.php start" or "nohup php index.php start &"
* stop: "php index.php stop"
* restart: "php index.php restart" or "nohup php index.php restart &"

#####yii2-websocket https://github.com/morozovsk/yii2-websocket

#####How it work (for russian speakers):
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
