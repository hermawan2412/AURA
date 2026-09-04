<?php
// Sync HARIAN data pegawai dari RESTU (app cuti, database beda: 'restu',
// tabel pegawai/jabatan/golongan) ke AURA - diminta user 2026-09-04, biar
// jabatan/golongan/TMT pegawai gak perlu diketik ulang manual di 2 aplikasi.
// Dipasang lewat /etc/cron.d/aura-sync-pegawai-restu, jam 01:00 tiap hari.
//
// SATU ARAH (RESTU -> AURA), read-only terhadap RESTU (SELECT doang, pakai
// user MySQL 'aura_restu_reader' yang cuma di-grant SELECT ke 3 tabel itu -
// BUKAN kredensial aura_app biasa, prinsip least-privilege: kalau aplikasi
// web AURA kena, itu gak otomatis bawa akses baca RESTU).
//
// Field yang disinkron CUMA nama_lengkap/jabatan/golongan_ruang/tmt
// (dikonfirmasi user via AskUserQuestion) - field lain (pangkat, gelar_depan,
// gelar_belakang, unit_kerja, status_aktif) SENGAJA GAK disentuh:
// - pangkat/gelar: RESTU gak punya kolom ini sama sekali (kalau disentuh,
//   data AURA yang udah ada bakal ke-NULL-kan sia-sia).
// - unit_kerja: RESTU seragam "Pengadilan Agama Rantau" di semua baris,
//   gak ada nilai baru buat disinkron.
// - status_aktif: pegawai yang ADA di AURA tapi UDAH GAK ADA di RESTU
//   (keluar/pensiun, RESTU beneran hapus baris-nya, gak ada soft-delete)
//   DIBIARKAN APA ADANYA, gak di-nonaktifin otomatis - butuh review manual
//   admin, bukan keputusan otomatis skrip ini (dikonfirmasi user).
// - tmt: pakai COALESCE, cuma ngisi kalau AURA-nya masih NULL - gak pernah
//   nimpa TMT yang udah keburu diisi manual di AURA dengan versi RESTU.
//
// NIP baru di RESTU yang belum ada di AURA -> auto-insert (dikonfirmasi
// user), status_aktif=1, unit_kerja default "Pengadilan Agama Rantau"
// (satu-satunya nilai yang ada di RESTU juga).

declare(strict_types=1);
chdir(__DIR__);
require __DIR__ . '/../src/bootstrap.php';

use Aurat\Database;

$config = require __DIR__ . '/../config/config.php';
if (!isset($config['db_restu_readonly'])) {
    fwrite(STDERR, "config['db_restu_readonly'] belum di-set di config/config.php - lihat DEPLOY.md.\n");
    exit(1);
}
$cfgRestu = $config['db_restu_readonly'];

try {
    $restu = new PDO(
        'mysql:host=' . $cfgRestu['host'] . ';dbname=' . $cfgRestu['dbname'] . ';charset=' . $cfgRestu['charset'],
        $cfgRestu['user'],
        $cfgRestu['pass'],
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
    );
} catch (PDOException $e) {
    fwrite(STDERR, '[' . date('c') . "] Gagal konek ke RESTU: " . $e->getMessage() . "\n");
    exit(1);
}

$aura = Database::pdo();

$rows = $restu->query(
    'SELECT p.nip, p.nama_pegawai, j.nama_jabatan, g.nama_golongan, p.tmt_pegawai
     FROM pegawai p
     JOIN jabatan j ON j.id_jabatan = p.id_jabatan
     JOIN golongan g ON g.id_golongan = p.id_golongan'
)->fetchAll();

$cekAda = $aura->prepare('SELECT id, nama_lengkap, jabatan, golongan_ruang, tmt FROM pegawai WHERE nip = ?');
$update = $aura->prepare(
    'UPDATE pegawai SET nama_lengkap = ?, jabatan = ?, golongan_ruang = ?, tmt = COALESCE(tmt, ?), updated_at = NOW()
     WHERE nip = ?'
);
$insert = $aura->prepare(
    "INSERT INTO pegawai (nip, nama_lengkap, jabatan, golongan_ruang, unit_kerja, tmt, status_aktif)
     VALUES (?, ?, ?, ?, 'Pengadilan Agama Rantau', ?, 1)"
);

// --dry-run: preview doang (SELECT-only terhadap AURA juga), gak ada
// INSERT/UPDATE - dipakai buat verifikasi manual pertama kali sebelum
// dipasang ke cron beneran.
$dryRun = in_array('--dry-run', $argv, true);

$diupdate = 0;
$dibuat = 0;
$gagal = 0;

foreach ($rows as $r) {
    try {
        $cekAda->execute(array($r['nip']));
        $existing = $cekAda->fetch();
        if ($existing) {
            $berubah = $existing['nama_lengkap'] !== $r['nama_pegawai']
                || $existing['jabatan'] !== $r['nama_jabatan']
                || $existing['golongan_ruang'] !== $r['nama_golongan']
                || ($existing['tmt'] === null && $r['tmt_pegawai'] !== null);
            if ($dryRun) {
                if ($berubah) {
                    echo "  [update] {$r['nip']} {$r['nama_pegawai']}\n";
                }
            } else {
                $update->execute(array($r['nama_pegawai'], $r['nama_jabatan'], $r['nama_golongan'], $r['tmt_pegawai'], $r['nip']));
            }
            if ($berubah) {
                $diupdate++;
            }
        } else {
            if ($dryRun) {
                echo "  [baru] {$r['nip']} {$r['nama_pegawai']}\n";
            } else {
                $insert->execute(array($r['nip'], $r['nama_pegawai'], $r['nama_jabatan'], $r['nama_golongan'], $r['tmt_pegawai']));
            }
            $dibuat++;
        }
    } catch (PDOException $e) {
        $gagal++;
        error_log('[AURA sync-restu] gagal proses NIP ' . $r['nip'] . ': ' . $e->getMessage());
    }
}

echo '[' . date('c') . '] ' . ($dryRun ? 'DRY-RUN (gak ada yg beneran ditulis): ' : 'Sync selesai: ')
    . count($rows) . " baris RESTU diproses, "
    . "$diupdate ke-update, $dibuat baru dibuat, $gagal gagal.\n";
