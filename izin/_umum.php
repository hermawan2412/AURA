<?php
// Bagian awal bersama halaman modul izin (bukan endpoint - jangan di-whitelist di Nginx).

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Database;
use Aurat\Formatter;
use Aurat\Izin\Permohonan;

Auth::requireLogin();
$db = Database::pdo();
$peng = Permohonan::pengaturan($db);
$userId = (int) $_SESSION['user_id'];
$rootAsset = '../';

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function sekarangUtc()
{
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

function badgeIzin($status)
{
    $kelas = array(
        'disetujui' => 'badge-aktif',
        'menunggu_persetujuan' => 'badge-segera',
        'diajukan_ke_pta' => 'badge-segera',
        'ditolak' => 'badge-kedaluwarsa',
        'dicabut' => 'badge-kedaluwarsa',
    );
    return '<span class="badge ' . (isset($kelas[$status]) ? $kelas[$status] : 'badge-belum-diisi') . '">'
        . h(Permohonan::LABEL_STATUS[$status]) . '</span>';
}

function jamIzin(array $p)
{
    $mulai = str_replace(':', '.', substr($p['jam_mulai'], 0, 5));
    return $p['jam_kembali']
        ? $mulai . '–' . str_replace(':', '.', substr($p['jam_kembali'], 0, 5))
        : 'mulai ' . $mulai . ', pulang awal';
}

function tanggalIzin(array $p)
{
    return Formatter::tanggalIndonesia($p['tanggal']);
}

/** Timestamp UTC dari DB -> "20 November 2026 13.05" waktu kantor. */
function waktuKantor($utc, $zona)
{
    if (!$utc) {
        return '';
    }
    $t = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($zona));
    return Formatter::tanggalIndonesia($t->format('Y-m-d')) . ' ' . $t->format('H.i');
}

/** Pesan satu kali lewat sesi (setelah redirect POST). */
function kilat($pesan = null, $tipe = 'info')
{
    if ($pesan !== null) {
        $_SESSION['izin_kilat'] = array($pesan, $tipe);
        return null;
    }
    $k = isset($_SESSION['izin_kilat']) ? $_SESSION['izin_kilat'] : null;
    unset($_SESSION['izin_kilat']);
    return $k;
}

function tampilKilat()
{
    $k = kilat();
    if ($k) {
        echo '<div class="alert alert-' . ($k[1] === 'error' ? 'error' : 'info') . '" role="status">' . h($k[0]) . '</div>';
    }
}
