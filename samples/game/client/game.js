// 
// Pashe programming lab! Wow! Cool! Vote for pashe!!!11
//


var w = 100;
var h = 60;
var cellsize = 10;


// dir:   0 - up, 1 - down, 2 - left, 3 - right

var paused = true;
var keys = { up: false, down: false, left: false, right: false};

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

function applyKeys(tank) {
    if ((keys.up && !keys.left && !keys.right) || (keys.up && (keys.left || keys.right) && tank.dir != 0))
        tank.dir = 0;
    else if (keys.down && !keys.left && !keys.right  || keys.down && (keys.left || keys.right) && tank.dir != 1)
        tank.dir = 1;
    else if ((keys.left && !keys.up && !keys.down) || (keys.left && (keys.up || keys.down) && tank.dir != 2))
        tank.dir = 2;
    else if ((keys.right && !keys.up && !keys.down) || (keys.right && (keys.up || keys.down) && tank.dir != 3))
        tank.dir = 3;

    if (keys.up || keys.down || keys.left || keys.right) {
        ws.send(JSON.stringify(tank));
    }
}

//
// Drawing
//

function drawTank(tank, i) {
    if (!i) {
        ctx.fillStyle = "#3b5998";
    } else /*if (!tank.health) {
        ctx.fillStyle = "#008000";
    } else if (tank.health > 0) {
        ctx.fillStyle = "#000000";
    } else if (tank.health < 0)*/ {
        ctx.fillStyle = "#00bbbb";
    }

    for (iy = 0; iy < tankmodelsize; iy++) {
        for (ix = 0; ix < tankmodelsize; ix++) {
            if (1 == tankmodel[ tank.dir ][iy * tankmodelsize + ix]) {
                ctx.fillRect(cellsize * (tank.x + ix), cellsize * (tank.y + iy), cellsize - 1, cellsize - 1);
            }
        }
    }

    ctx.fillText(tank.name, cellsize * tank.x, cellsize * (tank.y + 4));

    if (tank.health) {
        ctx.fillStyle = "#000";
        ctx.font = "10px sans-serif";
        ctx.textBaseline = "bottom";
        ctx.fillText(tank.health, cellsize * tank.x, cellsize * tank.y - 15);
    }

    /*ctx.fillStyle = "#a5f5a5";
    ctx.fillRect(x, y, health / 3, 5);*/
}

function draw() {
    ctx.clearRect(0, 0, w * cellsize, h * cellsize);

    for (i = 0; i < tanks.length; ++i)
        drawTank(tanks[i], i);
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
    }
}
