<?php
/**
 * Plugin Name: Vue Quiz
 * Description: Форма-опросник
 * Author URI:  nick.iv.dev@gmail.com
 * Author:      Nick Iv
 * Version:     1.0
 *
 * License:     MIT
 *
 */

register_activation_hook(__FILE__, 'create_plugin_tables');

function create_plugin_tables()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'vue_quiz';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
    `id` INT NOT NULL AUTO_INCREMENT,
	`category` VARCHAR(255) DEFAULT NULL,
	`typeCar` VARCHAR(255) DEFAULT NULL,
	`address` VARCHAR(255) DEFAULT NULL,
	`date` DATE DEFAULT NULL,
	`time` TIME DEFAULT NULL,
	`datetime` DATETIME DEFAULT NULL,
	`name` VARCHAR(255) DEFAULT NULL,
	`phone` VARCHAR(255) DEFAULT NULL,
	`created_at` DATETIME DEFAULT NOW(),
	PRIMARY KEY (`id`)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function check_time()
{
    $_POST = json_decode(file_get_contents('php://input'), true);
    global $wpdb;
    $table_name = $wpdb->prefix . 'vue_quiz';

    $row = $wpdb->get_results($wpdb->prepare("SELECT datetime as time FROM " . $table_name . " WHERE datetime = %s", trim($_POST['date']) . ' ' . trim($_POST['time'])));

    if (!count($row)) {
        return ['avail' => true];
    } else {
        return ['avail' => false];
    }
}

function get_free_time()
{
    $_POST = json_decode(file_get_contents('php://input'), true);
    $times = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

    global $wpdb;
    $table_name = $wpdb->prefix . 'vue_quiz';

    $row = $wpdb->get_results($wpdb->prepare("SELECT TIME_FORMAT(`time`, '%H:%i') as time FROM " . $table_name . " WHERE date = %s", trim($_POST['date'])));

    if (count($row)) {
        foreach ($row as $key => $item) {
            unset($times[array_search($item->time, $times)]);
        }
    }
    return array_values($times);
}

function set_data()
{
    $_POST = json_decode(file_get_contents('php://input'), true);

    global $wpdb;
    $table_name = $wpdb->prefix . 'vue_quiz';

    $data = [
        'category' => trim($_POST['category']),
        'typeCar' => trim($_POST['typeCar']),
        'address' => trim($_POST['address']),
        'date' => trim($_POST['date']),
        'time' => trim($_POST['time']),
        'datetime' => trim($_POST['date']) . ' ' . trim($_POST['time']) . ':00',
        'name' => trim($_POST['name']),
        'phone' => trim($_POST['phone']),
    ];

    $result = $wpdb->insert(
        $table_name,
        $data
    );

    if ($result) {
        sendToTelegram($data, $wpdb->insert_id);
        sendToWhatsApp($data['phone'], $wpdb->insert_id);
        return ['success' => true];
    } else {
        return ['success' => false];
    }
}

function sendToTelegram($data, $id)
{
    $url = 'https://api.telegram.org/bot';
    $token = '5129667492:AAEXJgEpqZIko4nAEk_kziAX9xlB98l8iKg';
    $chatid = '-1001734882908';
    $data = [
        'chat_id' => $chatid,
        'text' => "Новая заявка на техосмотр. Номер заявки №$id. Данные клиента:" .
            "\nИмя: " . $data['name'] .
            "\nТелефон: " . $data['phone'] .
            "\nЗапись на: " . $data['datetime'] .
            "\nАдрес: " . $data['address'] .
            "\nУслуга: " . $data['category'] .
            "\nТип ТС: " . $data['typeCar']
    ];

    file_get_contents($url . $token . "/sendMessage?" . http_build_query($data));
}

function sendToWhatsApp($phone, $id)
{
    $messagewa = 'Спасибо! Ваша заявка на техосмотр принята. Номер заявки №' . $id;
    $w_token = 'YbUz0Z3mQnwMDd055a356297b3008a5cde8d204b079e6';
    $array = [
        [
            'chatId' => phonewa_format($phone),
            'message' => $messagewa,
        ]
    ];

    $sURL = "https://app.api-messenger.com/sendmessage?token=$w_token";
    $sPD = $messagewa;
    $aHTTP = [
        'http' =>
            [
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $sPD
            ]
    ];
    $context = stream_context_create($aHTTP);
    $contents = file_get_contents($sURL, false, $context);
}

function phonewa_format($phone)
{
    $phone = trim($phone);
    $res = preg_replace(
        array(
            '/[\+]?([7|8])[-|\s]?\([-|\s]?(\d{3})[-|\s]?\)[-|\s]?(\d{3})[-|\s]?(\d{2})[-|\s]?(\d{2})/',
            '/[\+]?([7|8])[-|\s]?(\d{3})[-|\s]?(\d{3})[-|\s]?(\d{2})[-|\s]?(\d{2})/',
            '/[\+]?([7|8])[-|\s]?\([-|\s]?(\d{4})[-|\s]?\)[-|\s]?(\d{2})[-|\s]?(\d{2})[-|\s]?(\d{2})/',
            '/[\+]?([7|8])[-|\s]?(\d{4})[-|\s]?(\d{2})[-|\s]?(\d{2})[-|\s]?(\d{2})/',
            '/[\+]?([7|8])[-|\s]?\([-|\s]?(\d{4})[-|\s]?\)[-|\s]?(\d{3})[-|\s]?(\d{3})/',
            '/[\+]?([7|8])[-|\s]?(\d{4})[-|\s]?(\d{3})[-|\s]?(\d{3})/',
        ),
        array(
            '7$2$3$4$5',
            '7$2$3$4$5',
            '7$2$3$4$5',
            '7$2$3$4$5',
            '7$2$3$4',
            '7$2$3$4',
        ),
        $phone
    );
    return $res;
}


add_action('rest_api_init', function () {
    register_rest_route('vue-quiz/v1', 'checkTime', [
        'methods' => 'POST',
        'callback' => 'check_time'
    ]);
    register_rest_route('vue-quiz/v1', 'getFreeTime', [
        'methods' => 'POST',
        'callback' => 'get_free_time'
    ]);
    register_rest_route('vue-quiz/v1', 'setData', [
        'methods' => 'POST',
        'callback' => 'set_data'
    ]);
});

register_uninstall_hook(__FILE__, 'drop_plugin_tables');
function drop_plugin_tables()
{
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'vue_quiz');
}
