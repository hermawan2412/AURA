<?php

// Run: php tests/izin-wa.php   (DB lokal; tidak pernah mengirim WA sungguhan - pakai pengirim palsu)
require __DIR__ . '/izin-db.php';

use Aurat\Izin\Permohonan as P;
use Aurat\Izin\Wa;

$utc = new DateTimeZone('UTC');
$jumat = new DateTimeImmutable('2026-11-20 05:05:00', $utc);
$db->exec("UPDATE izin_pengaturan SET wa_token = 'token-uji' WHERE id = 1");
$izin = function ($m, $k) {
    return array('jenis' => 'keluar_sementara', 'tanggal' => '2026-11-20', 'jam_mulai' => $m, 'jam_kembali' => $k, 'alasan' => 'Urusan bank yang mendesak');
};

$terkirim = array();
$skenario = array();
Wa::$pengirimPalsu = function ($nomor, $pesan) use (&$terkirim, &$skenario) {
    $terkirim[] = array($nomor, $pesan);
    return $skenario ? array_shift($skenario) : array(true, null, false);
};
$status = function ($idPermohonan, $penerima) use ($db) {
    $s = $db->prepare('SELECT status, percobaan, error FROM izin_wa_outbox WHERE permohonan_id = ? AND penerima_user_id = ?');
    $s->execute(array($idPermohonan, $penerima));
    return $s->fetch();
};

// Sukses normal + format nomor Fonnte
$a = P::ajukan($db, 7, $izin('14:00', '14:30'), $jumat);
$h = Wa::prosesAntrean($db, $jumat);
cek('terkirim', $status($a, 5)['status'] === 'terkirim', $h);
cek('nomor dinormalisasi 62..', $terkirim[0][0] === '6281230000' . '05', $terkirim[0]);
cek('A29 pesan memuat tautan detail', strpos($terkirim[0][1], 'izin/detail.php?id=' . $a) !== false);
cek('tidak dikirim dua kali', Wa::prosesAntrean($db, $jumat)['terkirim'] === 0 && count($terkirim) === 1);

// Gagal sementara -> diulang setelah 1 menit, lalu sukses
$b = P::ajukan($db, 9, $izin('14:00', '14:30'), $jumat);
$skenario = array(array(false, 'koneksi gagal', true));
Wa::prosesAntrean($db, $jumat);
cek('gagal sementara diantre ulang', $status($b, 6)['status'] === 'antre' && (int) $status($b, 6)['percobaan'] === 1, $status($b, 6));
cek('belum diulang sebelum jeda', Wa::prosesAntrean($db, $jumat->modify('+30 seconds'))['terkirim'] === 0);
Wa::prosesAntrean($db, $jumat->modify('+61 seconds'));
cek('diulang lalu terkirim', $status($b, 6)['status'] === 'terkirim', $status($b, 6));

// Maks 3 percobaan
$c = P::ajukan($db, 3, $izin('14:00', '14:30'), $jumat);
$skenario = array(array(false, 'x', true), array(false, 'x', true), array(false, 'x', true));
Wa::prosesAntrean($db, $jumat);
Wa::prosesAntrean($db, $jumat->modify('+2 minutes'));
Wa::prosesAntrean($db, $jumat->modify('+10 minutes'));
cek('berhenti setelah 3 percobaan', $status($c, 1)['status'] === 'gagal' && (int) $status($c, 1)['percobaan'] === 3, $status($c, 1));

// A26 timeout tidak pasti -> tidak diulang buta; token salah -> tidak diulang
$d = P::ajukan($db, 8, $izin('14:00', '14:30'), $jumat);
$skenario = array(array(false, 'tidak pasti: batas waktu habis', false));
Wa::prosesAntrean($db, $jumat);
cek('A26 timeout ambigu tidak diulang', $status($d, 5)['status'] === 'gagal', $status($d, 5));

// A27 tugas sudah diputus sebelum WA terkirim -> dibatalkan, pesan keputusan tetap dikirim
$e = P::ajukan($db, 7, $izin('15:00', '15:30'), $jumat);
P::setujui($db, 5, $e, 1, '', $jumat);
$n = count($terkirim);
Wa::prosesAntrean($db, $jumat);
cek('A27 tugas usang tidak terkirim', $status($e, 5)['status'] === 'dibatalkan');
cek('A27 kabar disetujui ke pemohon terkirim', $status($e, 7)['status'] === 'terkirim');
cek('A27 kabar mengetahui ke Wakil terkirim', $status($e, 2)['status'] === 'terkirim');

// Tanggal izin lewat -> pesan tugas basi dibatalkan
$f = P::ajukan($db, 9, $izin('16:00', '16:30'), $jumat);
Wa::prosesAntrean($db, new DateTimeImmutable('2026-11-21 01:00:00', $utc));
cek('tugas basi setelah tanggal lewat dibatalkan', $status($f, 6)['status'] === 'dibatalkan');

// WA dinonaktifkan -> tidak diantrekan & tidak diproses
$db->exec('UPDATE izin_pengaturan SET wa_aktif = 0 WHERE id = 1');
$g = P::ajukan($db, 4, $izin('14:00', '14:30'), $jumat);
cek('WA nonaktif: tidak ada antrean baru', $status($g, 1) === false);
$db->exec('UPDATE izin_pengaturan SET wa_aktif = 1 WHERE id = 1');

// Batas harian: terkirim hari ini (zona kantor) >= batas -> antrean ditahan, tidak dikirim
$db->exec('UPDATE izin_pengaturan SET wa_batas_harian = 1 WHERE id = 1');
$sudah = Wa::hitungTerkirim($db, P::pengaturan($db), $jumat, 'hari');
$db->exec('UPDATE izin_pengaturan SET wa_batas_harian = ' . max(1, $sudah) . ' WHERE id = 1');
$jumlahSebelum = count($terkirim);
$h2 = P::ajukan($db, 4, $izin('15:00', '15:30'), $jumat);
$hb = Wa::prosesAntrean($db, $jumat);
cek('batas harian: tidak ada kiriman baru', count($terkirim) === $jumlahSebelum, $hb);
cek('batas harian: antrean ditahan, tetap antre', $hb['ditahan'] >= 1 && $status($h2, 1)['status'] === 'antre', $hb);
$db->exec('UPDATE izin_pengaturan SET wa_batas_harian = ' . ($sudah + 50) . ' WHERE id = 1');
Wa::prosesAntrean($db, $jumat);
cek('batas dinaikkan: antrean terkirim', $status($h2, 1)['status'] === 'terkirim');
cek('hitung bulan >= hari', Wa::hitungTerkirim($db, P::pengaturan($db), $jumat, 'bulan') >= Wa::hitungTerkirim($db, P::pengaturan($db), $jumat, 'hari'));
$db->exec('UPDATE izin_pengaturan SET wa_batas_harian = 100 WHERE id = 1');

cek('format nomor', Wa::formatNomor('+62 812-3456-789') === '628123456789' && Wa::formatNomor('0812') === null && Wa::formatNomor('') === null);

echo $gagal === 0 ? "OK izin-wa\n" : "$gagal gagal\n";
exit($gagal === 0 ? 0 : 1);
