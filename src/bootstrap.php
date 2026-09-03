<?php

$config = require __DIR__ . '/../config/config.php';

session_name($config['session_name']);
session_set_cookie_params(array(
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']), // otomatis nyala kalau beneran diakses lewat HTTPS, gak hardcode
    'httponly' => true,
    'samesite' => 'Lax',
));
session_start();

require __DIR__ . '/../vendor/autoload.php';
