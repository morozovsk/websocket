var paused = true;
var keys = {up: false, down: false, left: false, right: false};
w = h = 52;//416/52=8

function mainLoop(tank) {
    if (!paused) {
        applyKeys(tank);
    }
    setTimeout(function() {mainLoop(tank)}, 1000 / 10);
}

function applyKeys(tank) {
    if ((keys.up && !keys.left && !keys.right) || (keys.up && (keys.left || keys.right) && tank.dir != 'up'))
        tank.dir = 'up';
    else if (keys.down && !keys.left && !keys.right  || keys.down && (keys.left || keys.right) && tank.dir != 'down')
        tank.dir = 'down';
    else if ((keys.left && !keys.up && !keys.down) || (keys.left && (keys.up || keys.down) && tank.dir != 'left'))
        tank.dir = 'left';
    else if ((keys.right && !keys.up && !keys.down) || (keys.right && (keys.up || keys.down) && tank.dir != 'right'))
        tank.dir = 'right';

    tank.fire = keys.fire;

    if (keys.up || keys.down || keys.left || keys.right || keys.fire) {
        ws.send(JSON.stringify(tank));
        paused = true;
    }
}

function drawTank(tank, i) {
    if (!i) {
        context.fillStyle = "#3b5998";
    } else /*if (!tank.health) {
        context.fillStyle = "#008000";
    } else if (tank.health > 0) {
        context.fillStyle = "#000000";
    } else if (tank.health < 0)*/ {
        context.fillStyle = "#00bbbb";
    }

    context.drawImage(tankmodel[tank.dir + (((tank.dir == 'left' || tank.dir == 'right') && tank.x % 2 || (tank.dir == 'up' || tank.dir == 'down') && tank.y % 2) ? '' : '1')], (i ? tank.x : w/2 - 1) * cellsize, (i ? tank.y : h/2 - 1) * cellsize, 4 * cellsize, 4 * cellsize);

    context.fillText(tank.name, (i ? tank.x : w/2) * cellsize, ((i ? tank.y : h/2) + 4) * cellsize);

    if (tank.health) {
        context.fillStyle = "#000";
        context.font = "10px sans-serif";
        context.textBaseline = "bottom";
        context.fillText(tank.health, (i ? tank.x : w/2) * cellsize, ((i ? tank.y : h/2) - 2) * cellsize);
    }

    /*context.fillStyle = "#a5f5a5";
    context.fillRect(x, y, health / 3, 5);*/
}

function draw() {
    context.clearRect(0, 0, w * cellsize, h * cellsize);
    context.fillStyle = "#000000";
    if (tanks[0].x < w/2) {
        context.fillRect(0, 0, (w/2 - tanks[0].x - 1) * cellsize, h * cellsize);
    }

    if (tanks[0].y < h/2) {
        context.fillRect(0, 0, w * cellsize, (h/2 - tanks[0].y - 1) * cellsize);
    }

    if (tanks[0].w - tanks[0].x < w/2) {
        context.fillRect((w/2 - tanks[0].x + tanks[0].w) * cellsize, 0, w * cellsize, h * cellsize);
    }

    if (tanks[0].h - tanks[0].y < h/2) {
        context.fillRect(0, (h/2 - tanks[0].y + tanks[0].h) * cellsize, w * cellsize, h * cellsize);
    }

    for (i = 0; i < tanks.length; ++i)
        drawTank(tanks[i], i);
}

function drawMinimap(tank) {
    minimap.clearRect(0, 0, 200, 200);
    minimap.fillStyle = "#3b5998";
    minimap.fillRect(Math.floor((tank.x-w/2) * 200 / tank.w), Math.floor((tank.y-h/2) * 200 / tank.h), Math.floor(w * 200 / tank.w), Math.floor(h * 200 / tank.h));
}

function keyDown(e) {
    switch (e.keyCode) {
        // up:
        case 38:
        case 87:
            keys.up = true;
            break;
        // down:
        case 40:
        case 83:
            keys.down = true;
            break;
        // left:
        case 37:
        case 65:
            keys.left = true;
            break;
        // right:
        case 39:
        case 68:
            keys.right = true;
            break;
        // fire:
        case 8:
            keys.fire = true;
            break;
    }
}

function keyUp(e) {
    switch (e.keyCode) {
        // up:
        case 38:
        case 87:
            keys.up = false;
            break;
        // down:
        case 40:
        case 83:
            keys.down = false;
            break;
        // left:
        case 37:
        case 65:
            keys.left = false;
            break;
        // right:
        case 39:
        case 68:
            keys.right = false;
            break;
        // fire:
        case 8:
            keys.fire = false;
            break;
    }
}

$(function () {
    var chat = $("#chat"), canvasdiv = $("#canvasdiv"), canvas = $("#canvas");

    function resize() {
        $("#rightdiv").css('height', $(window).height() - 70);
        chat.css('height', $(window).height() - $("#divinput").height() - $("#minimap").height() - 70);
        canvasdiv.css('width', $(window).width() - 350);
        canvasdiv.css('height', $(window).height() - 70);
        cellsize = Math.min(Math.floor(canvasdiv.height() / h), Math.floor(canvasdiv.width() / w));
        canvas.attr('width', w * cellsize);
        canvas.attr('height', h * cellsize);
        padding = (canvasdiv.width() > canvas.width()) ? (canvasdiv.width() - canvas.width()) / 2 : 0;
        canvasdiv.css('paddingLeft', padding);
        canvasdiv.css('width', canvasdiv.width() - padding);
    }
    
    resize();

    $(window).resize(function() {
        resize();
    });

    context = canvas[0].getContext('2d');
    context.font = "12px Arial";

    minimap = $("#minimap")[0].getContext('2d');

    tankmodel = {
        up: document.getElementById('tank1up'),
        down: document.getElementById('tank1down'),
        left: document.getElementById('tank1left'),
        right: document.getElementById('tank1right'),
        up1: document.getElementById('tank1up1'),
        down1: document.getElementById('tank1down1'),
        left1: document.getElementById('tank1left1'),
        right1: document.getElementById('tank1right1')
    }

    window.addEventListener("keydown", keyDown, false);
    window.addEventListener("keyup", keyUp, false);

    function wsStart() {
        ws = new WebSocket("ws://127.0.0.1:8002/");
        ws.onopen = function () {
            chat.append("<p>Система: соединение открыто. Чтобы начать играть введите имя, под которым вы будете отображаться. В имени можно использовать английские буквы и цифры. Имя не должно превышать 10 символов.</p>");
            chat.scrollTop($('#chat')[0].scrollHeight);
            paused = true;
            mainLoop({w: w, h: h});
        };
        ws.onclose = function () {
            chat.append("<p>система: соединение закрыто, пытаюсь переподключиться</p>");
            chat.scrollTop($('#chat')[0].scrollHeight);
            paused = true;
            setTimeout(wsStart, 1000);
        };
        ws.onmessage = function (evt) {
            var pack = JSON.parse(evt.data);
            if (pack.cmd == 'message') {
                chat.append("<p>"+pack.data+"</p>");
                chat.scrollTop($('#chat')[0].scrollHeight);
            } else if (pack.cmd == 'tanks') {
                tanks = pack.data;
                //chat.append("<p>" + tanks[0].x + "," + tanks[0].y + "</p>");
                chat.scrollTop($('#chat')[0].scrollHeight);
                //console.log(tanks[0].x, tanks[0].y, tanks[0].w, tanks[0].h)
                drawMinimap(tanks[0]);
                canvas.css('backgroundPosition', -tanks[0].x * cellsize + 'px -' + tanks[0].y * cellsize + 'px');

                draw();

                if (paused) {
                    paused = false;
                }
            }
        };
    }

    wsStart();

    $('#input').focus();
});