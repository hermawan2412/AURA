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
$cssTambahan = 'assets/css/izin.css';
$jsTambahan = 'assets/js/izin.js';

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

/** Ikon garis kecil (stroke = currentColor). */
function ikonIzin($nama, $ukuran = 20)
{
    $jalur = array(
        'centang' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'jam' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'silang' => '<path d="M7 7l10 10M17 7L7 17"/>',
        'dokumen' => '<path d="M7 3.5h7l4 4V20.5H7z"/><path d="M14 3.5V8h4M10 13h5M10 16.5h5"/>',
        'panah' => '<path d="M9 6l6 6-6 6"/>',
        'unduh' => '<path d="M12 4v11M7.5 10.5L12 15l4.5-4.5M5 19.5h14"/>',
        'tambah' => '<path d="M12 5v14M5 12h14"/>',
    );
    return '<svg width="' . (int) $ukuran . '" height="' . (int) $ukuran . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $jalur[$nama] . '</svg>';
}

function inisial($nama)
{
    $semua = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\s]/u', ' ', (string) $nama)));
    $kata = array_values(array_filter($semua, function ($k) { return mb_strlen($k) > 2; })) ?: $semua; // lewati gelar pendek
    return mb_strtoupper(mb_substr($kata[0], 0, 1) . (isset($kata[1]) ? mb_substr($kata[1], 0, 1) : ''));
}
