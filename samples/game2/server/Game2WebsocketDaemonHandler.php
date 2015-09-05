<?php

namespace morozovsk\websocket\samples;

//пример реализации чата
class Game2WebsocketDaemonHandler extends \morozovsk\websocket\Daemon
{
    protected $units = []; //массив юнитов
    protected $items = []; //массив обьектов
    protected $effects = []; //массив эффектов
    protected $skills = []; //массив скилов
    protected $all_objects = []; //массив всех отрисовуемых обьектов (текущих)
    //protected $users = 0; //кол-во игроков
    protected $item;
    protected $effect;
    protected $effect_code = 0;
    protected $skill_code = 0;
    protected $score = ['blue' => 0, 'red' => 0]; //счет
    protected $win_score = 20; //макс колво убийтв для победы
    protected $map_width = 2940;
    protected $map_height = 1932;
    protected $map_arr = []; //массив карты
    protected $unit_arr = [];
    protected $images = []; //картинки
    protected $map = [ //атрибуты карты
        /*'img' => 'tiles/bg2.png',
        'red_spawn_x' =>800,
        'red_spawn_y' => 200,
        'blue_spawn_x' => 500,
        'blue_spawn_y' => 200*/
    ];

    protected $user_intervals = [];

    protected $interval_send_data = 50; //интервал отправки данных
    protected $unit_resurrection_time = 5000; //время возрождения

    protected $action_code = [
        'stop' => 0,
        'run' => 1,
        'hit' => 2,
        'block' => 2,
        'dead' => 3
    ];

    protected $unit_direction
        = [ //направление
            'right' => 0,
            'left' => 1,
            'up' => 2,
            'down' => 3,
            'right_up' => 4,
            'right_down' => 5,
            'left_up' => 6,
            'left_down' => 7
        ];

    protected $arr_mouse_x
        = [ //напр. мышки
            '1' => 'right',
            '-1' => 'left'
        ];

    protected $arr_mouse_y
        = [ //напр. мышки
            '1' => 'up',
            '-1' => 'down'
        ];

    protected $direction_block
        = [ //какое направление блочит другое направление
            0 => 1,
            1 => 0,
            2 => 3,
            3 => 2,
            4 => 7,
            5 => 6,
            6 => 5,
            7 => 4
        ];

    protected $me_ready = [];
    protected $packet_ready = [];

    protected function onStart() {
        /*for ($i = 0; $i < $this->map_width * $this->map_height; $i++) {
            $this->map_arr[$i] = '0';
        }*/

        //карта местности
        $map = json_decode(file_get_contents('map.json'), true);
        $this->map['img'] = $map['tile'];
        $this->map['red_spawn_x'] = $map['spawn']['red_spawn_x'];
        $this->map['red_spawn_y'] = $map['spawn']['red_spawn_y'];
        $this->map['blue_spawn_x'] = $map['spawn']['blue_spawn_x'];
        $this->map['blue_spawn_y'] = $map['spawn']['blue_spawn_y'];

        foreach ($map['items'] as $i => $item) {
            $item['id'] = 'item_' . $i;
            $item['img_id'] = '';

            if (!in_array($item['img'], $this->images)) {
                $this->images[] = $item['img'];
            }
            $this->items[] = $item;
            $this->all_objects[] = $item;

            $this->set_item_to_map($item);
        }

        //парсим файл юнита
        $units = json_decode(file_get_contents('units.json'), true);
        foreach ($units as $unit) {
            $this->unit_arr[] = $unit;
            $this->images[] = $unit['img'];
        }

        //парсим эффекты
        $effects = json_decode(file_get_contents('effects.json'), true);
        foreach ($effects as $i => $effect) {
            $item = [
                'id' => 'effect_' . $i,
                'code' => $this->effect_code,
                'type' => 'effect',
                'cover' => $effect['cover'],
                'x' => 0,
                'y' => 0,
                'damage' => $effect['damage'],
                'img' => $effect['img'],
                'name' => $effect['name'],
                'animation' => 0,
                'direction' => 0,
                'img_id' => '',
                'action_speed' => $effect['action_speed'],
                'frames' => $effect['frames'],
                'width' => $effect['width'],
                'height' => $effect['height']
            ];
            if (!in_array($item['img'], $this->images)) {
                $this->images[] = $item['img'];
            }
            $this->effects[] = $item;
        }

        //парсим скилы
        $skills = json_decode(file_get_contents('skills.json'), true);
        foreach ($skills as $i => $skill) {
            $item = [
                'id' => 'skill_' . $i,
                'code' => $this->skill_code,
                'type' => 'skill',
                'x' => 0,
                'y' => 0,
                'damage' => $skill['damage'],
                'speed' => $skill['speed'],
                'effect' => $skill['effect'],
                'name' => $skill['name'],
                'animation' => 0,
                'direction' => 0
            ];
            $this->skill_code++;
            $this->skills[] = $item;
        }
    }

    protected function onTimer()//$this->interval_send_data
    {
        foreach ($this->units as $connectionId => &$unit) {
            if ($this->me_ready[$connectionId]) {
                $this->regular_res();
                $this->sendToClient($connectionId, json_encode(['event' => 'main_packet', 'units' => $this->all_objects, 'my_unit' => $unit, 'score' => $this->score]));
            }
        }
    }

    protected function onOpen($connectionId, $info) { //вызывается при соединении с новым клиентом
        $time = date('H:i:s');
        //$this->users++;
        //$this->units[$connectionId] = [];
        //$action_interval;
        $this->packet_ready[$connectionId] = false;
        $this->me_ready[$connectionId] = false;
        // Посылаем клиенту сообщение о том, что он успешно подключился и его имя
        $this->sendToClient($connectionId, json_encode(['event' => 'connected', 'name' => (string) $connectionId, 'time' => $time]));

        // Посылаем всем остальным пользователям, что подключился новый клиент и его имя
        $this->sendToClients(json_encode(['event' => 'userJoined', 'name' => $connectionId, 'time' => $time]), $connectionId);
    }

    protected function onClose($connectionId) { //вызывается при закрытии соединения клиентом

        if ($this->me_ready[$connectionId]/* && $this->user_intervals[$connectionId] !== 'kicked'*/) {
            $time = date('Y-m-d H:i:s');

            $this->sendToClients(json_encode(['event' => 'userSplit', 'name' => $connectionId, 'time' => $time]), $connectionId);

            $this->clear_space($this->units[$connectionId]);
            //clearInterval($action_interval);
            /*setTimeout(
                function () {*/
                    //$this->clear_space($this->units[$connectionId]);
                /*}, $this->interval_send_data * 2
            );*/
            unset($this->units[$connectionId]); //удаляем игрока из массива игроков.
            unset($this->all_objects[$this->get_object($connectionId)]); //удаляем игрока из массива игроков.

            unset($this->packet_ready[$connectionId]);
            unset($this->me_ready[$connectionId]);
        }
    }

    protected function onMessage($connectionId, $data, $type) { //вызывается при получении сообщения от клиента
        //var_export($data);
        $data = json_decode($data, 1);
        switch (@$data['event']) {
            case 'res_packet':
                if ($this->me_ready[$connectionId]) {
                    $this->packet_ready[$connectionId] = true;
                }

                if ($this->me_ready[$connectionId] && $this->packet_ready[$connectionId]) {
                    $unit = &$this->units[$connectionId];
                    $this->set_atributes($unit, $data); //устанавливаем атрибуты текущего положения
                    $this->under_buff($unit);
                    $this->set_buffs($unit);
                    $this->unit_action($data['key_x'], $data['key_y'], $data['mouse_pos'], $data['mouse_key'], $data['action_key'], $unit); //обрабатываем присланное действие
                    $this->animate_effects();
                }
                break;
            case 'choosed_side':
                $unit = [
                    'id' => (string) $connectionId,
                    'x' => $this->map[$data['team'] . '_spawn_x'],
                    'y' => $this->map[$data['team'] . '_spawn_y'],
                    'img' => $this->unit_arr[$data['unit_type']]['img'],
                    'img_id' => '',
                    'screen_size' => ['x' => 0, 'y' => 0],
                    'screen_delta' => ['x' => 0, 'y' => 0],
                    'type' => 'unit',
                    'team' => $data['team'],
                    'buffs' => [],
                    'width' => $this->unit_arr[$data['unit_type']]['width'],
                    'height' => $this->unit_arr[$data['unit_type']]['height'],
                    'toll_x' => $this->unit_arr[$data['unit_type']]['toll_x'],
                    'toll_y' => $this->unit_arr[$data['unit_type']]['toll_y'],
                    'hit_length' => $this->unit_arr[$data['unit_type']]['hit_length'],
                    'name' => (string) $this->unit_arr[$data['unit_type']]['name'],
                    'damage' => $this->unit_arr[$data['unit_type']]['damage'],
                    'animation' => 0,
                    'delayed' => 0,
                    'live' => true,
                    'action_speed' => $this->unit_arr[$data['unit_type']]['action_speed'],
                    'delay' => 0,
                    'speed' => $this->unit_arr[$data['unit_type']]['speed'],
                    'max_speed' => $this->unit_arr[$data['unit_type']]['speed'],
                    'hp' => $this->unit_arr[$data['unit_type']]['hp'],
                    'tired' => $this->unit_arr[$data['unit_type']]['tired'],
                    'max_hp' => $this->unit_arr[$data['unit_type']]['hp'],
                    'max_tired' => $this->unit_arr[$data['unit_type']]['tired'],
                    'action' => $this->action_code['stop'], // 0 - stop, 1 - run
                    'direction' => 0
                ];
                $this->units[$connectionId] = &$unit;
                $this->all_objects[] = &$unit;
                $this->me_ready[$connectionId] = true;
                //отсылаем сведения о карте
                $this->sendToClient($connectionId, json_encode(['event' => 'first_packet', 'my_unit' => $unit, 'map' => $this->map, 'images' => $this->images]));

                /*$action_interval = setInterval(
                    function () { //цикл обновления параметров типка*/
                        /*if ($this->me_ready[$connectionId] && $this->packet_ready[$connectionId]) {
                            $this->set_atributes($unit, $data); //устанавливаем атрибуты текущего положения
                            $this->under_buff($unit);
                            $this->set_buffs($unit);
                            $this->unit_action($data['key_x'], $data['key_y'], $data['mouse_pos'], $data['mouse_key'], $data['action_key'], $unit); //обрабатываем присланное действие
                            $this->animate_effects();
                        }*/
                    /*}, $this->interval_send_data
                );*/
                //$this->user_intervals[$connectionId] = $action_interval;
                break;


            default: //если чат
                $time = date('Y-m-d H:i:s');
                // Уведомляем клиента, что его сообщение успешно дошло до сервера
                $this->sendToClient(
                    $connectionId, json_encode(['event' => 'messageSent', 'name' => (string) $connectionId, 'text' => $data, 'time' => $time])
                );

                // Отсылаем сообщение остальным участникам чата
                $this->sendToClients(
                    json_encode(['event' => 'messageReceived', 'name' => (string) $connectionId, 'text' => $data, 'time' => $time]), $connectionId
                );
                break;
        }
    }

    private function sendToClients($data, $exclude = null)
    {
        $data = json_encode($data);
        foreach ($this->clients as $connectionId) {
            if ($connectionId != $exclude) {
                $this->sendToClient($connectionId, $data);
            }
        }
    }

    public function set_item_to_map($item)
    {
        $start_pos_x = $item['x'] + $item['width'] / 2 - $item['toll_x'] / 2;
        $end_pos_x = $item['x'] + $item['width'] / 2 + $item['toll_x'] / 2;
        $start_pos_y = $item['y'] + $item['height'] - $item['toll_y'];
        $end_pos_y = $item['y'] + $item['height'];
        for ($i = $start_pos_x; $i < $end_pos_x; $i++) {
            for ($j = $start_pos_y; $j < $end_pos_y; $j++) {
                if ($i == $start_pos_x || $j == $start_pos_y || $i == $end_pos_x - 1 || $j == $end_pos_y - 1) {
                    $this->map_arr[$j * $this->map_width + $i] = 'item';
                }
            }
        }
    }

    public function regular_res()
    {
        usort(
            $this->all_objects, function ($unit_1, $unit_2) //сортируем, для отрисовки сначала дальние потом ближние
            {
                return ($unit_1['y'] + $unit_1['height']) - ($unit_2['y'] + $unit_2['height']);
            }
        );
        //all_objects.sort($sort_effects);
    }

    public function sort_effects($unit_1)
    {
        return ($unit_1['type'] == "effect" && $unit_1['cover'] == "true");
    }


    public function unit_action($key_x, $key_y, $mouse_pos, $mouse_key, $action_key, &$unit)
    {
        if ($unit['hp'] > 0) //проверка не убит ли юнит
        {
            if ($action_key != '') { //проверяем, юзает ли игрок скилл

                //console.log($action_key);
                //console.log($mouse_pos.x_map+' '+mouse_pos.y_map);

                $unit['action'] = $this->action_code['dead'];
                if ($unit['animation'] == 8) {
                    //create_arrow($unit, mouse_pos);
                }
                $this->set_unit_direction($unit, $mouse_pos); //устанавливаем его направление
                $this->unit_do_animation($unit); //производим анимацию

            } else {
                if ($mouse_key == '') { //проверяем, не совершает ли других действий игрок

                    $unit['action'] = $this->action_code['run'];

                    $this->set_direction($unit, $key_x, $key_y);
                    if ($this->check_space($unit, $key_x, $key_y)) {
                        $this->clear_space($unit); //очищаем область под юнитом
                        $this->set_unit_new_position($unit, $key_x, $key_y); //продвигаем юнита на его скорость вперед
                        $this->set_space($unit); //записываем ID юнита в область нахождения
                    }

                    $this->unit_do_animation($unit); //выполняем анимацию для юнита

                } else { //игрок совершает действие мышкой ($приоритетное!)

                    $this->set_unit_direction($unit, $mouse_pos); //устанавливаем его направление

                    if ($mouse_key === 'left') {
                        $unit['action'] = $this->action_code['hit'];
                        $this->unit_do_animation_hit($unit, $mouse_pos, $key_x, $key_y); //совешаем удар

                    } else {
                        if ($mouse_key === 'right') {
                            $unit['action'] = $this->action_code['block'];
                        }
                    }
                }
            }
        } else { //юнит таки убит
            if ($unit['live'] == true) //момент смерти юнита
            {
                $unit['animation'] = 0;
                $unit['live'] = false;
                $unit['action'] = $this->action_code['dead'];
                if ($unit['team'] == 'blue') {
                    $this->score['red'] += 1;
                } else {
                    if ($unit['team'] == 'red') {
                        $this->score['blue'] += 1;
                    }
                }
                $this->check_win();
                $this->clear_space($unit);
                $this->unit_resurrect($unit);
            }
            if ($unit['animation'] < 9) {
                $unit['animation'] += 1;
            }
        }
    }

    public function check_space(&$unit, $key_x, $key_y)
    {
        //проверить какая кнопка приходит, и в соответствии с етим прибавить к текущей координате скорость.
        $go_move_x = 0;
        $go_move_y = 0;

        if ($key_x != '' && $key_y != '') {
            if ($key_y == 'up') {
                $go_move_y = -intval($unit['speed'] / 1.5);
            } else {
                if ($key_y == 'down') {
                    $go_move_y = intval($unit['speed'] / 1.5);
                }
            }
            if ($key_x == 'left') {
                $go_move_x = -intval($unit['speed'] / 1.5);
            } else {
                if ($key_x == 'right') {
                    $go_move_x = intval($unit['speed'] / 1.5);
                }
            }
        } else {
            if ($key_x != '') {
                if ($key_x == 'left') {
                    $go_move_x = -$unit['speed'];
                } else {
                    if ($key_x == 'right') {
                        $go_move_x = $unit['speed'];
                    }
                }
            } else {
                if ($key_y != '') {
                    if ($key_y == 'up') {
                        $go_move_y = -$unit['speed'];
                    } else {
                        if ($key_y == 'down') {
                            $go_move_y = $unit['speed'];
                        }
                    }
                }
            }
        }
        if (($unit['x'] > $this->map['blue_spawn_x'] - 50 && $unit['x'] < $this->map['blue_spawn_x'] + 150)
            && ($unit['y'] > $this->map['blue_spawn_y'] - 50 && $unit['y'] < $this->map['blue_spawn_y'] + 150)
        ) {
            return true;
        }
        if (($unit['x'] > $this->map['red_spawn_x'] - 50 && $unit['x'] < $this->map['red_spawn_x'] + 150)
            && ($unit['y'] > $this->map['red_spawn_y'] - 50 && $unit['y'] < $this->map['red_spawn_y'] + 150)
        ) {
            return true;
        }
        for (
            $i = $unit['x'] + intval($unit['width'] - $unit['toll_x']) / 2 + $go_move_x;
            $i < $unit['x'] + $unit['width'] / 2 + $unit['toll_x'] / 2 + $go_move_x; $i++
        ) {
            for ($j = $unit['y'] + $unit['height'] - $unit['toll_y'] + $go_move_y; $j < $unit['y'] + $unit['height'] + $go_move_y; $j++) {
                if ((isset($this->map_arr[$j * $this->map_width + $i]) && $this->map_arr[$j * $this->map_width + $i] !== $unit['id'])
                    || $i > $this->map_width
                    || $i < 1
                ) {
                    return false;
                }
            }
        }
        return true;
    }

    public function set_space(&$unit)
    {
        $start_pos_x = intval($unit['x'] + intval($unit['width'] - $unit['toll_x']) / 2);
        $end_pos_x = intval($unit['x'] + $unit['width'] / 2 + $unit['toll_x'] / 2);
        $start_pos_y = intval($unit['y'] + $unit['height'] - $unit['toll_y']);
        $end_pos_y = intval($unit['y'] + $unit['height']);
        for ($i = $start_pos_x; $i < $end_pos_x; $i++) {
            for ($j = $start_pos_y; $j < $end_pos_y; $j++) {
                $this->map_arr[$j * $this->map_width + $i] = $unit['id'];
            }
        }
    }

    public function clear_space(&$unit)
    {
        $start_pos_x = intval($unit['x'] + intval(($unit['width']) - $unit['toll_x']) / 2);
        $end_pos_x = intval($unit['x'] + $unit['width'] / 2 + $unit['toll_x'] / 2);
        $start_pos_y = intval($unit['y'] + $unit['height'] - $unit['toll_y']);
        $end_pos_y = intval($unit['y'] + $unit['height']);

        for ($i = $start_pos_x; $i < $end_pos_x; $i++) {
            for ($j = $start_pos_y; $j < $end_pos_y; $j++) {
                if (isset($this->map_arr[$j * $this->map_width + $i])) {
                    unset($this->map_arr[$j * $this->map_width + $i]);
                }
            }
        }
    }


    public function hit(&$unit, $mouse_pos)
    { //удар и блок работает
        if ($mouse_pos['x'] == 1) {
            $start_point_x = $unit['x'] + $unit['width'] / 2 + $unit['toll_x'] / 2;
            $end_point_x = $start_point_x + $unit['hit_length'];
        } else {
            if ($mouse_pos['x'] == -1) {
                $start_point_x = $unit['x'] + $unit['width'] / 2 - $unit['toll_x'] / 2 - $unit['hit_length'];
                $end_point_x = $start_point_x + $unit['hit_length'];
            } else {
                if ($mouse_pos['x'] == 0) {
                    $start_point_x = $unit['x'] + $unit['width'] / 2 - $unit['toll_x'] / 2;
                    $end_point_x = $start_point_x + $unit['toll_x'];
                }
            }
        }
        if ($mouse_pos['y'] == 1) {
            $start_point_y = $unit['y'] + $unit['height'] - $unit['toll_y'] - $unit['hit_length'];
            $end_point_y = $start_point_y + $unit['hit_length'];
        } else {
            if ($mouse_pos['y'] == -1) {
                $start_point_y = $unit['y'] + $unit['height'];
                $end_point_y = $start_point_y + $unit['hit_length'];
            } else {
                if ($mouse_pos['y'] == 0) {
                    $start_point_y = $unit['y'];
                    $end_point_y = $start_point_y + $unit['height'];
                }
            }
        }
        $start_point_x = intval($start_point_x);
        $start_point_y = intval($start_point_y);
        $end_point_x = intval($end_point_x);
        $end_point_y = intval($end_point_y);
        $effect_x = intval($start_point_x + $end_point_x) / 2;
        $effect_y = intval($start_point_y + $end_point_y) / 2;

        $this->use_effect('hit_effect', $effect_x, $effect_y, $unit['direction']);

        for ($i = $start_point_x; $i < $end_point_x; $i++) {
            for ($j = $start_point_y; $j < $end_point_y; $j++) {
                if (isset($this->map_arr[$j * $this->map_width + $i]) && $this->map_arr[$j * $this->map_width + $i] != $unit['id']) {
                    $curunit = &$this->units[$this->map_arr[$j * $this->map_width + $i]];
                    if ($curunit['live'] == true && $curunit['team'] != $unit['team']) {
                        if ($curunit['action'] != $this->action_code['block']) //если не блочим - бьем
                        {
                            $effect_on_unit_x = $curunit['x'] + $unit['width'] / 2;
                            $effect_on_unit_y = $curunit['y'] + $unit['height'] / 2;
                            $this->use_effect('purifection', $effect_on_unit_x, $effect_on_unit_y, $unit['direction']);
                            $curunit['hp'] = $curunit['hp'] - $unit['damage'];
                        }  elseif ($this->direction_block[$unit['direction']] != $curunit['direction']) {//если блочим - проверяем на направление блока
                            $curunit['hp'] = $curunit['hp'] - $unit['damage'];
                            $effect_on_unit_x = $curunit['x'] + $unit['width'] / 2;
                            $effect_on_unit_y = $curunit['y'] + $unit['height'] / 2;
                            $this->use_effect('purifection', $effect_on_unit_x, $effect_on_unit_y, $unit['direction']);
                            $curunit['hp'] = $curunit['hp'] - $unit['damage'];
                        }
                    }
                }
            }
        }
    }

    public function set_direction(&$unit, $key_x, $key_y)
    {
        if ($key_x != '' && $key_y != '') {
            $unit['direction'] = $this->unit_direction[$key_x . '_' . $key_y];
        } else {
            if ($key_x != '') {
                $unit['direction'] = $this->unit_direction[$key_x];
            } else {
                if ($key_y != '') {
                    $unit['direction'] = $this->unit_direction[$key_y];
                }
            }
        }
    }

    public function get_object($id)
    {
        foreach ($this->all_objects as $i => $curunit) {
            if ($curunit['id'] == $id) {
                return $i;
            }
        }
        return 0;
    }

    public function get_effect($code)
    {
        foreach ($this->all_objects as $i => $effect) {
            //$effect = $this->all_objects[$i];
            if (isset($effect['code']) && $effect['code'] == $code) {
                return $i;
            }
        }
        return 0;
    }

    public function set_atributes(&$unit, $msg)
    {
        $unit['screen_size']['x'] = $msg['screen_size']['x'];
        $unit['screen_size']['y'] = $msg['screen_size']['y'];
        $this->update_screen_delta($unit);
    }

    public function update_screen_delta(&$unit)
    { //центрируем юнита на карте
        if (($unit['x'] >= $unit['screen_size']['x'] / 2 - $unit['width'] / 2) && ($unit['x'] + $unit['screen_size']['x'] / 2 <= $this->map_width)) {
            $unit['screen_delta']['x'] = $unit['x'] - $unit['screen_size']['x'] / 2 + $unit['width'] / 2;
    } else {
            if ($unit['x'] < $unit['screen_size']['x'] / 2 - $unit['width'] / 2) {
                $unit['screen_delta']['x'] = 0;
            } else {
                if ($unit['x'] + $unit['screen_size']['x'] / 2 > $this->map_width) {
                    $unit['screen_delta']['x'] = $this->map_width - $unit['screen_size']['x'] + $unit['width'] / 2;
                }
            }
        }
        if (($unit['y'] >= $unit['screen_size']['y'] / 2 - $unit['height'] / 2 - 70)
            && ($unit['y'] + $unit['screen_size']['y'] / 2 <= $this->map_height)
        ) {
            $unit['screen_delta']['y'] = $unit['y'] - $unit['screen_size']['y'] / 2 + $unit['height'] / 2 + 70;
        } else {
            if ($unit['y'] < $unit['screen_size']['y'] / 2 - $unit['height'] / 2 - 70) {
                $unit['screen_delta']['y'] = 0;
            }
        }
    }

    public function set_images()
    {
        foreach ($this->units as &$unit) {
            foreach ($this->images as $src => $image) {
                if ($unit['img'] == $image['imo_path']) {
                    $unit['img_id'] = $src;
                }
            }
        }
    }

    public function unit_resurrect(&$unit)
    {
        /*setTimeout(
            function () {*/
                $this->clear_space($unit);
                $unit['live'] = true;
                $unit['action'] = $this->action_code['stop'];
                $unit['hp'] = $unit['max_hp'];
                $unit['x'] = $this->map[$unit['team'] . '_spawn_x'];
                $unit['y'] = $this->map[$unit['team'] . '_spawn_y'];

            /*}, $this->unit_resurrection_time
        );*/

    }

    public function check_win()
    {
        if ($this->score['red'] >= $this->win_score) {
            $this->sendToClients(json_encode(['event' => 'battle_ends', 'reason' => 'red_team_wins']));
            $this->score = ['red' => 0, 'blue' => 0];
            foreach ($this->units as $curunit) {
                $this->unit_resurrect($curunit);
            }
        } else {
            if ($this->score['blue'] >= $this->win_score) {
                $this->sendToClients(json_encode(['event' => 'battle_ends', 'reason' => 'blue_team_wins']));

                $this->score = ['red' => 0, 'blue' => 0];
                foreach ($this->units as $curunit) {
                    $this->unit_resurrect($curunit);
                }
            }
        }
    }

    public function under_buff(&$unit)
    {
        $unit['speed'] = $unit['max_speed'];
        $unit['delay'] = 0;
        $unit['buffs'] = [];
    }

    public function set_buffs(&$unit)
    {
        $buffs_array = [
            'tired' => function ($unit) {
                    $unit['speed'] = intval($unit['max_speed'] / 2);
                    $unit['delay'] = 1;
                },
            'run' => function ($unit) {
                    $unit['tired'] = $unit['tired'] - 1;
                    if ($unit['tired'] < 0) {
                        $unit['tired'] = 0;
                    }
                },
            'hit' => function ($unit) {
                    $unit['tired'] = $unit['tired'] - 2;
                    if ($unit['tired'] < 0) {
                        $unit['tired'] = 0;
                    }
                },
            'restore' => function ($unit) {
                    $unit['tired'] = $unit['tired'] + 5;
                    if ($unit['tired'] > $unit['max_tired']) {
                        $unit['tired'] = $unit['max_tired'];
                    }
                }
        ];

        if ($unit['action'] == $this->action_code['run']) //бег
        {
            $unit['buffs'][] = 'run';
        } else {
            if ($unit['action'] == $this->action_code['hit']) {
                $unit['buffs'][] = 'hit';
            } else {
                if ($unit['tired'] < $unit['max_tired']) {
                    $unit['buffs'][] = 'restore';
                }
            }
        }

        if ($unit['tired'] <= 1) //усталость
        {
            $unit['buffs'][] = 'tired';
        }

        foreach ($unit['buffs'] as $buff) {
            $buffs_array[$buff]($unit);
        }
    }

    public function use_effect($effect, $effect_x, $effect_y, $direction)
    {
        foreach ($this->effects as &$item) {
            if ($item['name'] == $effect) {
                $effect_used = &$item;
            }
        }
        $effect_used['code'] = $this->effect_code;
        $effect_used['direction'] = $direction;
        $effect_used['x'] = $effect_x - $effect_used['width'] / 2;
        $effect_used['y'] = $effect_y - $effect_used['height'] / 2;
        $this->effect_code++;
        $this->all_objects[] = $effect_used;

        /*setTimeout(
            function(){*/
            $efc = $this->get_effect($effect_used['code']);
        unset($this->all_objects[$efc]); //удаляем игрока из массива игроков.
    /*}, $effect_used['frames'] * $this->interval_send_data / 2);*///todo
        //console.log($effect.frames*effect.action_speed);
    }

    public function animate_effects()
    {
        foreach ($this->all_objects as $object) {
            if ($object['type'] == 'effect') {
                if ($object['animation'] < 9) {
                    $object['animation'] += 1;
                } else {
                    $object['animation'] = 0;
                }
            }
        }
    }

    public function unit_do_animation(&$unit)
    {
        if ($unit['delay'] != 0) {
            if ($unit['delay'] == $unit['delayed']) {
                $unit['delayed'] = 0;
                if ($unit['animation'] < 9) {
                    $unit['animation'] += 1;

                } else {
                    $unit['animation'] = 0;
                }
            } else {
                $unit['delayed']++;
            }
        } else {
            if ($unit['animation'] < 9) {
                $unit['animation'] += 1;
            } else {
                $unit['animation'] = 0;
            }
        }
    }

    public function unit_do_animation_hit(&$unit, $mouse_pos, $key_x, $key_y)
    {
        if ($unit['delay'] != 0) //если замедлен
        {
            if ($unit['delay'] == $unit['delayed']) {
                $unit['delayed'] = 0;
                if ($unit['animation'] < 9) {
                    $unit['animation'] += 1;
                } else {
                    $unit['animation'] = 0;
                    $this->hit($unit, $mouse_pos, $key_x, $key_y);
                }
            } else {
                $unit['delayed']++;
            }
        } else {
            if ($unit['animation'] < 9) {
                $unit['animation'] += 1;
            } else {
                $unit['animation'] = 0;
                $this->hit($unit, $mouse_pos, $key_x, $key_y);
            }
        }
    }

    public function set_unit_direction(&$unit, $mouse_pos)
    {
        if ($mouse_pos['x'] != '0' && $mouse_pos['y'] != '0') {
            $unit['direction'] = $this->unit_direction[$this->arr_mouse_x[$mouse_pos['x']] . '_' . $this->arr_mouse_y[$mouse_pos['y']]];
        } else {
            if ($mouse_pos['x'] == '1') {
                $unit['direction'] = $this->unit_direction['right'];
            } else {
                if ($mouse_pos['x'] == '-1') {
                    $unit['direction'] = $this->unit_direction['left'];
                }
            }

            if ($mouse_pos['y'] == '1') {
                $unit['direction'] = $this->unit_direction['up'];
            } else {
                if ($mouse_pos['y'] == '-1') {
                    $unit['direction'] = $this->unit_direction['down'];
                }
            }
        }
    }

    public function set_unit_new_position(&$unit, $key_x, $key_y)
    {
        if ($key_x != '' && $key_y != '') {
            if ($key_y == 'up') {
                $unit['y'] -= $unit['speed'] / 1.5;
            } else {
                if ($key_y == 'down') {
                    $unit['y'] += $unit['speed'] / 1.5;
                }
            }
            if ($key_x == 'left') {
                $unit['x'] -= $unit['speed'] / 1.5;
            } else {
                if ($key_x == 'right') {
                    $unit['x'] += $unit['speed'] / 1.5;
                }
            }
        } else {
            if ($key_x != '') {
                if ($key_x == 'left') {
                    $unit['x'] -= $unit['speed'];
                } else {
                    if ($key_x == 'right') {
                        $unit['x'] += $unit['speed'];
                    }
                }
            } else {
                if ($key_y != '') {
                    if ($key_y == 'up') {
                        $unit['y'] -= $unit['speed'];
                    } else {
                        if ($key_y == 'down') {
                            $unit['y'] += $unit['speed'];
                        }
                    }
                } else {
                    $unit['action'] = $this->action_code['stop'];
                }
            }
        }
    }

}

