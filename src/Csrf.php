<?php

namespace Aurat;

/**
 * Proteksi CSRF sederhana: 1 token per sesi, dicocokkan pada setiap request POST.
 * Pola sama dengan RESTU (includes/csrf.php) — AURA sebelumnya tidak punya
 * proteksi ini sama sekali di form manapun.
 */
class Csrf
{
    public static function token()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(self::randomBytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /** Cetak <input type=hidden>, tinggal ditempel di dalam <form method="post">. */
    public static function field()
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Panggil di awal penanganan setiap request POST. Menghentikan request
     * (403) kalau token tidak dikirim atau tidak cocok dengan punya sesi.
     */
    public static function verify()
    {
        $dikirim   = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        $tersimpan = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

        if ($tersimpan === '' || !hash_equals($tersimpan, $dikirim)) {
            http_response_code(403);
            die('Permintaan ditolak — sesi form sudah kedaluwarsa atau tidak valid. Muat ulang halaman dan coba lagi.');
        }
    }

    /** random_bytes() baru ada sejak PHP 7.0; di PHP 5.6 (target platform AURA) pakai openssl sbg pengganti. */
    private static function randomBytes($panjang)
    {
        if (function_exists('random_bytes')) {
            return random_bytes($panjang);
        }

        return openssl_random_pseudo_bytes($panjang);
    }
}
