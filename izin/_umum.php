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

/** Ikon garis kecil (stroke = currentColor), geometri dari Lucide v1.66.0 (lucide.dev, ISC). */
function ikonIzin($nama, $ukuran = 20)
{
    $jalur = array(
        'centang' => '<path d="M20 6 9 17l-5-5"/>',
        'jam' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'silang' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'dokumen' => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'panah' => '<path d="m9 18 6-6-6-6"/>',
        'unduh' => '<path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/>',
        'tambah' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
    );
    return '<svg width="' . (int) $ukuran . '" height="' . (int) $ukuran . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $jalur[$nama] . '</svg>';
}

function inisial($nama)
{
    $semua = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\s]/u', ' ', (string) $nama)));
    $kata = array_values(array_filter($semua, function ($k) { return mb_strlen($k) > 2; })) ?: $semua; // lewati gelar pendek
    return mb_strtoupper(mb_substr($kata[0], 0, 1) . (isset($kata[1]) ? mb_substr($kata[1], 0, 1) : ''));
}
