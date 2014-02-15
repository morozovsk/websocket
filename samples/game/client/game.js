var paused = true;
var keys = { up: false, down: false, left: false, right: false};

function applyKeys(tank) {
    if ((keys.up && !keys.left && !keys.right) || (keys.up && (keys.left || keys.right) && tank.dir != 'up'))
        tank.dir = 'up';
    else if (keys.down && !keys.left && !keys.right  || keys.down && (keys.left || keys.right) && tank.dir != 'down')
        tank.dir = 'down';
    else if ((keys.left && !keys.up && !keys.down) || (keys.left && (keys.up || keys.down) && tank.dir != 'left'))
        tank.dir = 'left';
    else if ((keys.right && !keys.up && !keys.down) || (keys.right && (keys.up || keys.down) && tank.dir != 'right'))
        tank.dir = 'right';

    if (keys.up || keys.down || keys.left || keys.right) {
        ws.send(JSON.stringify(tank));
        paused = true;
    }
}

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

    ctx.drawImage(tankmodel[tank.dir + (((tank.dir == 'left' || tank.dir == 'right') && tank.x % 2 || (tank.dir == 'up' || tank.dir == 'down') && tank.y % 2) ? '' : '1')], (i ? tank.x : w/2 - 1) * cellsize, (i ? tank.y : h/2 - 1) * cellsize, 4 * cellsize, 4 * cellsize);

    ctx.fillText(tank.name, (i ? tank.x : w/2) * cellsize, ((i ? tank.y : h/2) + 4) * cellsize);

    if (tank.health) {
        ctx.fillStyle = "#000";
        ctx.font = "10px sans-serif";
        ctx.textBaseline = "bottom";
        ctx.fillText(tank.health, (i ? tank.x : w/2) * cellsize, ((i ? tank.y : h/2) - 2) * cellsize);
    }

    /*ctx.fillStyle = "#a5f5a5";
    ctx.fillRect(x, y, health / 3, 5);*/
}

function draw() {
    ctx.clearRect(0, 0, w * cellsize, h * cellsize);
    ctx.fillStyle = "#000000";
    if (tanks[0].x < w/2) {
        ctx.fillRect(0, 0, (w/2 - tanks[0].x - 1) * cellsize, h * cellsize);
    }

    if (tanks[0].y < h/2) {
        ctx.fillRect(0, 0, w * cellsize, (h/2 - tanks[0].y - 1) * cellsize);
    }

    if (tanks[0].w - tanks[0].x < w/2) {
        ctx.fillRect((w/2 - tanks[0].x + tanks[0].w) * cellsize, 0, w * cellsize, h * cellsize);
    }

    if (tanks[0].h - tanks[0].y < h/2) {
        ctx.fillRect(0, (h/2 - tanks[0].y + tanks[0].h) * cellsize, w * cellsize, h * cellsize);
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
