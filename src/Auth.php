<?php

namespace Aurat;

use PDO;

/**
 * Autentikasi sederhana untuk 1-3 administrator berperan seragam.
 * Tidak ada relasi ke tabel pegawai (disepakati berdiri sendiri).
 * Diasumsikan session_start() sudah dipanggil oleh bootstrap.php.
 */
class Auth
{
    public static function login($username, $password)
    {
        $config = require __DIR__ . '/../config/config.php';
        $pdo = Database::pdo();

        $stmt = $pdo->prepare('SELECT * FROM user_login WHERE username = ? LIMIT 1');
        $stmt->execute(array($username));
        $user = $stmt->fetch();

        if (!$user || (int) $user['status_aktif'] !== 1) {
            return array('success' => false, 'message' => 'Nama pengguna atau kata sandi salah.');
        }

        if (!empty($user['terkunci_hingga']) && strtotime($user['terkunci_hingga']) > time()) {
            $sisaMenit = ceil((strtotime($user['terkunci_hingga']) - time()) / 60);
            return array(
                'success' => false,
                'message' => 'Akun terkunci sementara akibat terlalu banyak percobaan gagal. Coba lagi dalam ' . $sisaMenit . ' menit.',
            );
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::catatPercobaanGagal($pdo, $user, $config);
            return array('success' => false, 'message' => 'Nama pengguna atau kata sandi salah.');
        }

        $update = $pdo->prepare('UPDATE user_login SET percobaan_gagal = 0, terkunci_hingga = NULL, login_terakhir_at = NOW() WHERE id = ?');
        $update->execute(array($user['id']));

        $_SESSION['user_id']        = (int) $user['id'];
        $_SESSION['nama_tampilan']  = $user['nama_tampilan'];
        $_SESSION['username']       = $user['username'];
        $_SESSION['is_admin']       = (int) $user['is_admin'];

        return array('success' => true, 'message' => '');
    }

    private static function catatPercobaanGagal(PDO $pdo, array $user, array $config)
    {
        $percobaan = (int) $user['percobaan_gagal'] + 1;
        $maksimum  = (int) $config['max_percobaan_gagal'];

        if ($percobaan >= $maksimum) {
            $terkunciHingga = date('Y-m-d H:i:s', time() + ((int) $config['lama_kunci_menit'] * 60));
            $stmt = $pdo->prepare('UPDATE user_login SET percobaan_gagal = ?, terkunci_hingga = ? WHERE id = ?');
            $stmt->execute(array($percobaan, $terkunciHingga, $user['id']));
        } else {
            $stmt = $pdo->prepare('UPDATE user_login SET percobaan_gagal = ? WHERE id = ?');
            $stmt->execute(array($percobaan, $user['id']));
        }
    }

    public static function logout()
    {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check()
    {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin()
    {
        if (!self::check()) {
            header('Location: ' . self::pathBasis() . 'login.php');
            exit;
        }
    }

    public static function isAdmin()
    {
        return self::check() && !empty($_SESSION['is_admin']);
    }

    public static function requireAdmin()
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Akses ditolak — halaman ini khusus administrator.');
        }
    }

    public static function namaTampilan()
    {
        return isset($_SESSION['nama_tampilan']) ? $_SESSION['nama_tampilan'] : '';
    }

    /**
     * Path absolut dari domain (mis. "/aurat/") ke folder root aplikasi, dihitung dari lokasi
     * berkas ini relatif thd DOCUMENT_ROOT. Dulu requireLogin() redirect pakai path RELATIF
     * ("Location: login.php") — itu hanya benar kalau dipanggil dari skrip yang persis di root
     * aplikasi (index.php, pegawai.php). Dipanggil dari skrip bersarang (surat/*.php, admin/*.php)
     * saat sesi belum/tidak lagi login, browser me-resolve-nya relatif thd folder skrip yang lagi
     * jalan (mis. jadi "surat/login.php"), yang tidak ada — hasilnya 404, bukan form login.
     */
    private static function pathBasis()
    {
        $approotFs = realpath(__DIR__ . '/..');
        $docRoot = (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '')
            ? realpath($_SERVER['DOCUMENT_ROOT'])
            : false;

        if ($approotFs === false || $docRoot === false || strpos($approotFs, $docRoot) !== 0) {
            return ''; // tak bisa dihitung -> fallback ke path relatif (perilaku lama), bukan fatal error
        }

        $basePath = substr($approotFs, strlen($docRoot));
        $basePath = str_replace(DIRECTORY_SEPARATOR, '/', $basePath);

        return rtrim($basePath, '/') . '/';
    }
}
