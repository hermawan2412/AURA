<?php
/**
 * Salin file ini menjadi config.php (di folder yang sama) lalu isi
 * kredensial sesuai server. config.php TIDAK ikut disimpan di kontrol versi.
 */
return array(
    'db' => array(
        'host'    => '127.0.0.1',
        'dbname'  => 'aura',
        'user'    => 'aura_app',
        'pass'    => 'GANTI_DENGAN_PASSWORD_ASLI',
        'charset' => 'utf8mb4',
    ),
    'session_name' => 'aura_sid',
    'max_percobaan_gagal' => 5,
    'lama_kunci_menit'    => 15,
);
