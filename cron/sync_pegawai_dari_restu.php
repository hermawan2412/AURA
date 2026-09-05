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
//
// TAHAP 2 (ditambah 2026-09-05): sync AKUN LOGIN dari RESTU juga (user_login
// + kolom nip baru, db/030) - AURA sebelumnya cuma 1 akun generik
// (admin.kepegawaian), butuh AKUN per-pegawai spy tiap pegawai bisa pakai
// surat izin keluar kantor sendiri-sendiri.
// - Password DISINKRON (dikonfirmasi user: 1 kredensial buat 2 app) - dicek
//   dulu hash RESTU beneran bcrypt ($2y$...), password_verify() PHP
//   universal terhadap format itu terlepas app mana yang bikin hash-nya,
//   jadi aman disalin mentah, BUKAN re-hash/re-encode apa pun.
// - peran (db/031, gantiin is_admin lama - lihat 3-tier peran di src/Auth.php)
//   CUMA di-set dari role RESTU (Admin->pengelola/User->pengguna) pas akun
//   PERTAMA KALI dibuat - update berikutnya GAK PERNAH nimpa peran,
//   biar admin AURA bisa promosikan manual (mis. ke 'admin') tanpa
//   ke-reset lagi besoknya (beda dari password yang memang harus selalu
//   ngikutin RESTU).
// - Akun tanpa nip (mis. "admin.kepegawaian" versi RESTU sendiri, generik
//   bukan punya pegawai tertentu) DILEWATI - gak ada pegawai riil buat
//   dicocokkan.

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

// ============================================================
// TAHAP 2: sync akun login (user_login) - lihat catatan di kepala file.
// ============================================================
$rowsAkun = $restu->query("SELECT username, nip, password, role FROM user WHERE nip != ''")->fetchAll();

$cekAkunAda = $aura->prepare('SELECT id, username, password_hash, nama_tampilan FROM user_login WHERE nip = ?');
$updateAkun = $aura->prepare('UPDATE user_login SET password_hash = ?, nama_tampilan = ?, updated_at = NOW() WHERE nip = ?');
$insertAkun = $aura->prepare(
    'INSERT INTO user_login (username, nip, password_hash, nama_tampilan, peran, status_aktif)
     VALUES (?, ?, ?, ?, ?, 1)'
);
// nama_tampilan diambil dari pegawai.nama_lengkap (AURA sendiri, yang barusan
// ikut disinkron di Tahap 1 di atas - bukan JOIN ke RESTU lagi).
$cariNama = $aura->prepare('SELECT nama_lengkap FROM pegawai WHERE nip = ?');

$akunDiupdate = 0;
$akunDibuat = 0;
$akunGagal = 0;
$akunDilewati = 0;

foreach ($rowsAkun as $r) {
    try {
        $cariNama->execute(array($r['nip']));
        $pegawai = $cariNama->fetch();
        if (!$pegawai) {
            // NIP di RESTU tapi belum kesinkron ke pegawai AURA (jarang -
            // cuma kalau Tahap 1 di atas gagal utk NIP ini) - lewati dulu,
            // coba lagi run besok setelah Tahap 1 kejar.
            $akunDilewati++;
            continue;
        }
        $namaTampilan = $pegawai['nama_lengkap'];

        $cekAkunAda->execute(array($r['nip']));
        $existing = $cekAkunAda->fetch();
        if ($existing) {
            $akunBerubah = $existing['password_hash'] !== $r['password'] || $existing['nama_tampilan'] !== $namaTampilan;
            if ($dryRun) {
                if ($akunBerubah) {
                    echo "  [akun update] {$r['nip']} {$existing['username']}\n";
                }
            } else {
                $updateAkun->execute(array($r['password'], $namaTampilan, $r['nip']));
            }
            if ($akunBerubah) {
                $akunDiupdate++;
            }
        } else {
            // peran cuma di-set pas akun PERTAMA KALI dibuat (Admin->pengelola,
            // User->pengguna) - update di atas GAK PERNAH nyentuh peran, biar
            // admin AURA bisa promosikan manual (mis. ke 'admin') tanpa
            // ke-reset lagi besok (sama filosofi kayak password vs peran
            // sebelumnya, lihat catatan kepala file).
            $peran = ($r['role'] === 'Admin') ? 'pengelola' : 'pengguna';
            if ($dryRun) {
                echo "  [akun baru] {$r['nip']} {$r['username']}\n";
            } else {
                $insertAkun->execute(array($r['username'], $r['nip'], $r['password'], $namaTampilan, $peran));
            }
            $akunDibuat++;
        }
    } catch (PDOException $e) {
        $akunGagal++;
        error_log('[AURA sync-restu] gagal proses akun NIP ' . $r['nip'] . ': ' . $e->getMessage());
    }
}

echo '[' . date('c') . '] ' . ($dryRun ? 'DRY-RUN akun: ' : 'Sync akun selesai: ')
    . count($rowsAkun) . " baris RESTU diproses, "
    . "$akunDiupdate ke-update, $akunDibuat baru dibuat, $akunDilewati dilewati (pegawai blm sinkron), $akunGagal gagal.\n";
