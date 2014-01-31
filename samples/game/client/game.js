// 
// Pashe programming lab! Wow! Cool! Vote for pashe!!!11
//


var w = 102;
var h = 76;
var cellsize = 10;
var canvas = document.getElementById("field");
var ctx = canvas.getContext('2d');

canvas.width = w * cellsize;
canvas.height = h * cellsize;
ctx.font = "12px Arial";

//var data = ctx.getImageData(0, 0, canvas.width, canvas.height); 

// dir:   0 - up, 1 - down, 2 - left, 3 - right
// family: 0 - dead, 1 - me, 2 - bot
// bullet type: 0 - bullet, 1 - smoke

var paused = false;
var keys = { up: false, down: false, left: false, right: false, shift: false, fire: false, superfire: false };
var tankmovediv = 2;
var tankmovecnt = 0;
var bullets = new Array(138);
var tanks = new Array(36);

for (i = 0; i < bullets.length; ++i) {
    bullets[i] = {
        act: false,
        dir: 0,
        x: 0,
        y: 0,
        spd: 1,
        letter: false,
        // type: 0 - bullet, 1 - flame
        type: 0,
        energy: 10};
}

var tankmodelsize = 3;
var tankmodel = new Array(4);
tankmodel[0] = [
    0, 1, 0,
    1, 1, 1,
    1, 0, 1];
tankmodel[1] = [
    1, 0, 1,
    1, 1, 1,
    0, 1, 0];
tankmodel[2] = [
    0, 1, 1,
    1, 1, 0,
    0, 1, 1 ];
tankmodel[3] = [
    1, 1, 0,
    0, 1, 1,
    1, 1, 0 ];


//var dateObj = new Date();
var time_ms_last_request = 0;
var time_ms_request_sent = 0;
var request_sent = false;
var chat_messages = new Array();
var my_color = randomColorLight();

function randRange(min, max) {
    return Math.round(Math.random() * (max - min) + min);
}

function randomColorLight() {
    var t = "abcdef";
    var str = "#"
    for (var i = 0; i < 6; ++i)
        str += t[ randRange(0, 5) ];
    return str
}

function Tank(x, y, dir, dirgun, family, health) {
    this.x = x;
    this.y = y;

    this.dir = dir;
    this.dirgun = dirgun;
    this.moving = false;
    this.family = family;
    this.health = health;
    this.hit = function (_x, _y) {
        return (_x >= this.x && _x < this.x + tankmodelsize && _y >= this.y && _y < this.y + tankmodelsize);
    }
}

//
// Create tanks.
//
tanks[0] = new Tank(w / 2, h / 2, 0, 0, 1, 210);
tanks[1] = new Tank(randRange(0, 100), randRange(0, 70), 0, 0, 3, 1000)
tanks[2] = new Tank(randRange(0, 100), randRange(0, 70), 0, 0, 3, 400)

for (i = 3; i < tanks.length; ++i) {
    var dir = randRange(0, 3);
    tanks[i] = new Tank(randRange(0, 100), randRange(0, 70), dir, dir, 2, 112)
}


function applyKeys(tankObj) {
    if (keys.up)
        tankObj.dir = 0;
    else if (keys.down)
        tankObj.dir = 1;
    else if (keys.left)
        tankObj.dir = 2;
    else if (keys.right)
        tankObj.dir = 3;

    // If not shift, then gun = direction.
    if (!keys.shift)
        tankObj.dirgun = tankObj.dir;

    if (keys.up || keys.down || keys.left || keys.right) {
        if (!tankObj.moving)
            tankmovecnt = 0;
        tankObj.moving = true;
    }
    else
        tankObj.moving = false;

    if (keys.fire)
        startBullet(tankObj, 0, 100, randRange(1, 2), keys.superfire);
}

function moveTank(tankObj) {
    if (tankObj.health > 0 && tankObj.moving) {
        if (0 == tankmovecnt) {
            tankmovecnt = 0;
            switch (tankObj.dir) {
                case 0:
                    tankObj.y--;
                    break;
                case 1:
                    tankObj.y++;
                    break;
                case 2:
                    tankObj.x--;
                    break;
                case 3:
                    tankObj.x++;
                    break;
            }
            if (tankObj.x < 0) {
                tankObj.x = 0;
                tankObj.moving = false;
            }
            if (tankObj.y < 0) {
                tankObj.y = 0;
                tankObj.moving = false;
            }
            if (tankObj.x > w - tankmodelsize) {
                tankObj.x = w - tankmodelsize;
                tankObj.moving = false;
            }
            if (tankObj.y > h - tankmodelsize) {
                tankObj.y = h - tankmodelsize;
                tankObj.moving = false;
            }
        }
    }
}

function randomizeTank(tankObj) {
    if (0 == tankObj.health)
        return;
    if (Math.random() > 0.1)
        return;

    tankObj.dir += randRange(0, 30);
    tankObj.dir = tankObj.dir % 4;
    tankObj.dirgun = tankObj.dir;
    //tankObj.dir = randRange(0,3);

    //tankObj.moving = randRange(0,1);

    tankObj.moving = randRange(0, 1);


    if (Math.random() > 0.9) {
        if (3 == tankObj.family)
            startBullet(tankObj, 0, 30, 2, 0);
        else
            startBullet(tankObj, 0, 90, 1, 0);
    }
}

function startParticle(x, y, dir, type, energy, speed) {
    for (i = 0; i < bullets.length; ++i) {
        if (false == bullets[i].act) {
            bullets[i].act = true;
            bullets[i].x = x;
            bullets[i].y = y;

            bullets[i].dir = dir;
            bullets[i].letter = false;
            bullets[i].spd = speed;

            bullets[i].type = type;
            bullets[i].energy = energy;
            break;
        }
    }
}

function startBullet(tankObj, type, energy, speed, superfire) {
    // mode 0...10: bullets
    // mode 11: letters
    var x = 0;
    var y = 0;
    switch (tankObj.dirgun) {
        case 0:
            x = tankObj.x + 1;
            y = tankObj.y - 1;
            break;

        case 1:
            x = tankObj.x + 1;
            y = tankObj.y + tankmodelsize;
            break;

        case 2:
            x = tankObj.x - 1;
            y = tankObj.y + 1;
            break;

        case 3:
            x = tankObj.x + tankmodelsize;
            y = tankObj.y + 1;
            break;
    }

    startParticle(x, y, tankObj.dirgun, type, energy, speed);
    if (superfire) {
        switch (tankObj.dirgun) {
            case 0:
                startParticle(x - 1, y - 1, tankObj.dirgun, type, energy, speed);
                startParticle(x + 1, y - 1, tankObj.dirgun, type, energy, speed);
                startParticle(x - 2, y - 2, tankObj.dirgun, type, energy, speed);
                startParticle(x + 2, y - 2, tankObj.dirgun, type, energy, speed);
                break;
            case 1:
                startParticle(x - 1, y + 1, tankObj.dirgun, type, energy, speed);
                startParticle(x + 1, y + 1, tankObj.dirgun, type, energy, speed);
                startParticle(x - 2, y + 2, tankObj.dirgun, type, energy, speed);
                startParticle(x + 2, y + 2, tankObj.dirgun, type, energy, speed);
                break;
            case 2:
                startParticle(x - 2, y - 2, tankObj.dirgun, type, energy, speed);
                startParticle(x - 1, y - 1, tankObj.dirgun, type, energy, speed);
                startParticle(x - 1, y + 1, tankObj.dirgun, type, energy, speed);
                startParticle(x - 2, y + 2, tankObj.dirgun, type, energy, speed);
                break;
            case 3:
                startParticle(x + 2, y - 2, tankObj.dirgun, type, energy, speed);
                startParticle(x + 1, y - 1, tankObj.dirgun, type, energy, speed);
                startParticle(x + 1, y + 1, tankObj.dirgun, type, energy, speed);
                startParticle(x + 2, y + 2, tankObj.dirgun, type, energy, speed);
                break;
        }
    }
}


function moveBullets() {
    for (i = 0; i < bullets.length; ++i) {
        if (true == bullets[i].act) {
            var spd = bullets[i].spd;
            switch (bullets[i].dir) {
                case 0:
                    bullets[i].y -= spd;
                    break;
                case 1:
                    bullets[i].y += spd;
                    break;
                case 2:
                    bullets[i].x -= spd;
                    break;
                case 3:
                    bullets[i].x += spd;
                    break;
            }

            bullets[i].energy--;

            if (0 == bullets[i].energy ||
                bullets[i].x < 0 || bullets[i].x > w ||
                bullets[i].y < 0 || bullets[i].y > h) {
                bullets[i].act = false;
            }
        }
    }
}


function findTank(x, y, minhealth) {
    for (var i = 0; i < tanks.length; ++i) {
        if (true == tanks[i].hit(x, y) && tanks[i].health >= minhealth)
            return i;
    }
    return -1;
}

function checkBullets() {
    for (var i = 0; i < bullets.length; ++i) {
        if (false == bullets[i].act || 0 != bullets[i].type)
            continue;

        // 1 - minimum health
        tnk = findTank(bullets[i].x, bullets[i].y, 0);
        if (-1 == tnk)
            continue;

        bullets[i].act = false;

        if (!tanks[tnk].health)
            continue;

        tanks[tnk].health -= 2;
        bullets[i].moving = true;


        if (0 == tanks[tnk].health) {
            tanks[tnk].family = 0;
            for (var j = 0; j < 3; ++j) {
                startParticle(tanks[tnk].x + j, tanks[tnk].y - 1, 0, 0, 10, 1 + j);
                startParticle(tanks[tnk].x + j, tanks[tnk].y + 3, 1, 0, 11, 1 + j);
                startParticle(tanks[tnk].x - 1, tanks[tnk].y + j, 2, 0, 12, 1 + j);
                startParticle(tanks[tnk].x + 3, tanks[tnk].y + j, 3, 0, 13, 1 + j);
            }
        }
    }
}


//////////////////////////////////////////////////
//
// Drawing
//


function drawTank(tankObj) {
    if (tankObj.health > 0)
        drawHealth(cellsize * tankObj.x, cellsize * tankObj.y - 15, tankObj.health);

    var name = false;

    switch (tankObj.family) {
        case 0:
            ctx.fillStyle = "#550000";
            name = "x_x";
            break;
        case 1:
            ctx.fillStyle = "#3b5998";
            name = "Пашэ";
            break;
        case 2:
            ctx.fillStyle = "#95b070";
            name = "Деда";
            break;
        case 3:
            ctx.fillStyle = "#00bbbb";
            name = "locky";
            break;
    }

    for (iy = 0; iy < tankmodelsize; iy++)
        for (ix = 0; ix < tankmodelsize; ix++) {
            if (1 == tankmodel[ tankObj.dirgun ][iy * tankmodelsize + ix]) {
                ctx.fillRect(cellsize * (tankObj.x + ix), cellsize * (tankObj.y + iy), cellsize - 1, cellsize - 1);
            }
        }

    if (false != name)
        ctx.fillText(name, cellsize * tankObj.x, cellsize * (tankObj.y + 4));
}

function drawHealth(x, y, health) {
    ctx.fillStyle = "#a5f5a5";
    ctx.fillRect(x, y, health / 3, 5);

    ctx.fillStyle = "#000";
    ctx.font = " 10px sans-serif";
    ctx.textBaseline = "bottom";
    ctx.fillText(health, x, y);
}

function drawBullets() {

    for (i = 0; i < bullets.length; ++i) {
        if (true == bullets[i].act) {
            if (0 == bullets[i].type)
                ctx.fillStyle = "#ff0000";
            if (1 == bullets[i].type)
                ctx.fillStyle = "#dddddd";

            ctx.fillRect(cellsize * bullets[i].x, cellsize * bullets[i].y, cellsize - 1, cellsize - 1);
        }
    }
}

function draw() {
    ctx.clearRect(0, 0, w * cellsize, h * cellsize);

    for (i = 0; i < tanks.length; ++i)
        drawTank(tanks[i]);

    drawBullets();
}


function mainLoop() {
    if (paused) {
        setTimeout(mainLoop, 1000 / 10);
        return;
    }

    draw();
    applyKeys(tanks[0]);
    ws.send(JSON.stringify(tanks[0]));
    //$("#chat").append("<p>" + JSON.stringify(tanks[0]) + "</p>");
    //alert(JSON.stringify(tanks[0]));//{"x":51,"y":38,"dir":0,"dirgun":0,"moving":false,"family":1,"health":210}

    /*tankmovecnt++;
    //tankmovecnt = tankmovecnt % tankmovediv;
    if (tankmovecnt >= tankmovediv)
        tankmovecnt = 0;

    moveTank(tanks[0]);
    for (i = 1; i < tanks.length; ++i) {
        //moveTank(tanks[i]);
        //randomizeTank(tanks[i]);
    }

    moveBullets();
    checkBullets();*/
    setTimeout(mainLoop, 1000 / 10);
}


function keyDown(e) {
    switch (e.keyCode) {
        //default: break;

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
        // 'L'
        case 76:
            keys.shift = true;
            break;

        // 'shift'
        case 16:
            keys.superfire = true;
            break;

        // fire:
        case 32:
        case 17:
            keys.fire = true;
            break;



        /*case 80:
         paused = ! paused;
         return;*/
    }
}

function keyUp(e) {
    switch (e.keyCode) {
        //default: break;

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

        // 'L'
        case 76:
            keys.shift = false;
            break;

        // 'shift'
        case 16:
            keys.superfire = false;
            break;

        // fire:
        case 32:
        case 17:
            keys.fire = false;
            break;
    }
}

function chatMsgKeyUp(e) {
    if (13 == e.keyCode) {
        chat_messages.push(document.getElementById("input").value);
        document.getElementById("input").value = "";
    }
}

function updateField(name, text) {
    var elem = document.getElementById(name);
    while (elem.childNodes.length >= 1) {
        elem.removeChild(elem.firstChild);
    }
    elem.appendChild(elem.ownerDocument.createTextNode(text));
}

function clearNode(id) {
    var elem = document.getElementById(id);
    while (elem.childNodes.length >= 1) {
        elem.removeChild(elem.firstChild);
    }
}

function addToNode(id, message) {
    var elem = document.getElementById(id);
    var div = document.createElement('div');
    div.setAttribute('style', 'padding-left: 10px; background-color: ' + message[0] + ';');

    //var marker = document.createElement('div');
    //marker.setAttribute('style', 'background-color: #0a0; width: 5px; height:5px; float: left; ');
    //div.appendChild( marker );

    div.appendChild(div.ownerDocument.createTextNode(message[1]));

    elem.appendChild(div);
}

paused = false;
window.addEventListener("keydown", keyDown, false)
window.addEventListener("keyup", keyUp, false)
canvas.focus();

document.getElementById("input").addEventListener("keyup", chatMsgKeyUp, false);

//canvas.addEventListener('mousemove', mouseMoveF)
