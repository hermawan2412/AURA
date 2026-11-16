<?php

// Setup bersama tes integrasi izin: koneksi DB lokal + reset tabel izin_* + data rute sintetis.
// Dipakai lewat require dari tests/izin-*.php, bukan dijalankan langsung.
require __DIR__ . '/../vendor/autoload.php';

use Aurat\Izin\IzinException;

$config = require __DIR__ . '/../config/config.php';
if (!preg_match('/_(local|test)$/', $config['db']['dbname'])) {
    fwrite(STDERR, "Menolak jalan: DB '{$config['db']['dbname']}' bukan DB lokal/tes.\n");
    exit(1);
}
$c = $config['db'];
$db = new PDO("mysql:host={$c['host']};dbname={$c['dbname']};charset={$c['charset']}", $c['user'], $c['pass'], array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
));

$gagal = 0;
function cek($nama, $kondisi, $info = null)
{
    global $gagal;
    if (!$kondisi) {
        $gagal++;
        echo "GAGAL $nama " . ($info === null ? '' : json_encode($info, JSON_UNESCAPED_UNICODE)) . "\n";
    }
}
/** Jalankan aksi yang HARUS ditolak dengan IzinException; kembalikan pesannya. */
function ditolak($nama, callable $f)
{
    try {
        $f();
    } catch (IzinException $e) {
        return $e->getMessage();
    }
    cek($nama . ' (harusnya ditolak)', false);
    return null;
}
function satu(PDO $db, $sql, array $arg = array())
{
    $s = $db->prepare($sql);
    $s->execute($arg);
    return $s->fetchColumn();
}

// --- Siapkan data ---
foreach (array('izin_wa_outbox', 'izin_notifikasi', 'izin_audit', 'izin_dokumen', 'izin_tahap', 'izin_berhalangan', 'izin_pejabat', 'izin_pemetaan_pegawai', 'izin_hari_libur') as $t) {
    $db->exec("DELETE FROM $t");
}
$db->exec('UPDATE izin_permohonan SET asal_permohonan_id = NULL');
$db->exec('DELETE FROM izin_permohonan');
$db->exec("UPDATE izin_pengaturan SET mode = 'aktif', wa_aktif = 1, zona_waktu = 'Asia/Makassar' WHERE id = 1");
$db->exec("UPDATE pegawai SET no_telp = CASE WHEN id = 8 THEN NULL ELSE CONCAT('08123', LPAD(id, 6, '0')) END");
$db->exec("INSERT INTO izin_pejabat (kode, pegawai_id) VALUES ('ketua',1),('wakil',2),('panitera',5),('sekretaris',6)");
$db->exec("INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok) VALUES
    (1,'ketua'),(2,'wakil'),(3,'hakim'),(4,'hakim'),(5,'panitera'),(6,'sekretaris'),(7,'kepaniteraan'),(8,'kepaniteraan'),(9,'kesekretariatan')");
// pegawai 10 (skr2) sengaja tidak dipetakan

