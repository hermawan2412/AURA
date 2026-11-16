<?php
// Semua mutasi modul izin (POST + CSRF). Validasi hak & status ada di Permohonan.

require __DIR__ . '/_umum.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Izin\Dokumen;
use Aurat\Izin\IzinException;
use Aurat\Izin\Permohonan;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
Csrf::verify();

$aksi = isset($_POST['aksi']) ? (string) $_POST['aksi'] : '';
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$versi = isset($_POST['versi']) ? (int) $_POST['versi'] : 0;
$catatan = isset($_POST['catatan']) ? (string) $_POST['catatan'] : '';
$kini = sekarangUtc();
$kembali = 'detail.php?id=' . $id;

try {
    switch ($aksi) {
        case 'setujui':
            Permohonan::setujui($db, $userId, $id, $versi, $catatan, $kini);
            $d = Dokumen::terbitkan($db, $id, $kini);
            kilat('Izin disetujui.' . ($d && $d['status'] === 'siap' ? ' Surat siap diunduh.' : ' Surat sedang disiapkan.'));
            break;
        case 'tolak':
            Permohonan::tolak($db, $userId, $id, $versi, $catatan, $kini);
            kilat('Permohonan ditolak.');
            break;
        case 'batalkan':
            Permohonan::batalkan($db, $userId, $id, $versi, $catatan, $kini);
            kilat('Permohonan dibatalkan.');
            break;
        case 'cabut':
            Permohonan::cabut($db, $userId, $id, $versi, $catatan, $kini);
            kilat('Izin dicabut.');
            break;
        case 'alihkan':
            if (!Auth::isAdmin()) {
                throw new IzinException('Hanya admin yang dapat mengalihkan permohonan.');
            }
            Permohonan::alihkan($db, $userId, $id, $versi, $catatan, $kini);
            kilat('Permohonan dialihkan.');
            break;
        case 'buat_ulang_surat':
            if (!Auth::isAdmin()) {
                throw new IzinException('Hanya admin yang dapat membuat ulang surat.');
            }
            $d = Dokumen::terbitkan($db, $id, $kini);
            kilat($d && $d['status'] === 'siap' ? 'Surat siap.' : 'Surat masih gagal dibuat: ' . ($d ? $d['error'] : '-'), $d && $d['status'] === 'siap' ? 'info' : 'error');
            break;
        case 'baca_notifikasi':
            $db->prepare('UPDATE izin_notifikasi SET dibaca_at = ? WHERE penerima_user_id = ? AND dibaca_at IS NULL')
                ->execute(array(Permohonan::utc($kini), $userId));
            $kembali = 'index.php';
            break;
        default:
            http_response_code(400);
            exit('Aksi tidak dikenal.');
    }
} catch (IzinException $e) {
    kilat($e->getMessage(), 'error');
}

header('Location: ' . $kembali);
exit;
