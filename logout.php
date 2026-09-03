<?php

require __DIR__ . '/src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

Csrf::verify();

Auth::logout();
header('Location: login.php');
exit;
