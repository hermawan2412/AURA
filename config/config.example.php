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

    // Opsional - cuma dibutuhkan cron/sync_pegawai_dari_restu.php (sync
    // harian data pegawai dari app RESTU). User MySQL 'aura_restu_reader'
    // (bukan aura_app) - SELECT-only ke restu.pegawai/jabatan/golongan,
    // lihat DEPLOY.md bagian "Sync pegawai dari RESTU". Hapus/kosongkan
    // blok ini kalau server ini gak satu MySQL instance dgn RESTU.
    'db_restu_readonly' => array(
        'host'    => '127.0.0.1',
        'dbname'  => 'restu',
        'user'    => 'aura_restu_reader',
        'pass'    => 'GANTI_DENGAN_PASSWORD_ASLI',
        'charset' => 'utf8mb4',
    ),
);
