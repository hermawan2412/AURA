<?php

// Run: php tests/izin-waktu.php
require __DIR__ . '/../src/Izin/Waktu.php';

use Aurat\Izin\Waktu;

$gagal = 0;
function cek($nama, $kondisi, $info = null)
{
    global $gagal;
    if (!$kondisi) {
        $gagal++;
        echo "GAGAL $nama " . json_encode($info) . "\n";
    }
}

$jadwal = Waktu::jadwalDariJson('{"1":{"mulai":"08:00","selesai":"16:30"},"5":{"mulai":"08:00","selesai":"17:00"}}');
$zona = 'Asia/Makassar';
// Jumat 20 Nov 2026 13:05 WITA = 05:05 UTC
$kini = new DateTimeImmutable('2026-11-20 05:05:00', new DateTimeZone('UTC'));

$r = Waktu::validasi('pulang_awal', true, '', '', null, $jadwal, array(), $zona, $kini);
cek('mulai sekarang pakai waktu server zona kantor', $r === array('tanggal' => '2026-11-20', 'jam_mulai' => '13:05:00', 'jam_kembali' => null, 'jam_akhir' => '17:00:00'), $r);

$r = Waktu::validasi('keluar_sementara', false, '2026-11-20', '14:00', '15:00', $jadwal, array(), $zona, $kini);
cek('keluar sementara normal', $r['jam_akhir'] === '15:00:00', $r);

$r = Waktu::validasi('keluar_sementara', false, '2026-11-20', '13:00', '15:00', $jadwal, array(), $zona, $kini);
cek('jam manual lewat ditolak', isset($r['error']), $r);

$r = Waktu::validasi('keluar_sementara', false, '2026-11-20', '14:00', null, $jadwal, array(), $zona, $kini);
cek('jam kembali wajib', isset($r['error']), $r);

$r = Waktu::validasi('keluar_sementara', false, '2026-11-20', '14:00', '14:00', $jadwal, array(), $zona, $kini);
cek('jam kembali harus > mulai', isset($r['error']), $r);

$r = Waktu::validasi('keluar_sementara', false, '2026-11-20', '16:00', '17:30', $jadwal, array(), $zona, $kini);
cek('jam kembali lewat jam pulang', isset($r['error']), $r);

$r = Waktu::validasi('pulang_awal', false, '2026-11-20', '17:00', null, $jadwal, array(), $zona, $kini);
cek('mulai tepat jam pulang ditolak', isset($r['error']), $r);

$r = Waktu::validasi('pulang_awal', false, '2026-11-21', '10:00', null, $jadwal, array(), $zona, $kini);
cek('Sabtu bukan hari kerja', isset($r['error']), $r);

$r = Waktu::validasi('pulang_awal', false, '2026-11-23', '10:00', null, $jadwal, array('2026-11-23'), $zona, $kini);
cek('tanggal libur ditolak', isset($r['error']), $r);

$r = Waktu::validasi('pulang_awal', false, '2026-11-23', '15:00', null, $jadwal, array(), $zona, $kini);
cek('Senin depan boleh, akhir = jam pulang Senin', $r['jam_akhir'] === '16:30:00', $r);

$r = Waktu::validasi('pulang_awal', false, '2026-02-30', '10:00', null, $jadwal, array(), $zona, $kini);
cek('tanggal tidak ada ditolak', isset($r['error']), $r);

// 07:30 WITA, mulai sekarang sebelum jam masuk
$pagi = new DateTimeImmutable('2026-11-20 23:30:00', new DateTimeZone('UTC')); // = Sabtu 07:30 WITA
$r = Waktu::validasi('pulang_awal', true, '', '', null, $jadwal, array(), $zona, $pagi);
cek('zona kantor menentukan hari (Sabtu WITA walau Jumat UTC)', isset($r['error']), $r);

cek('boleh diputus hari yang sama', Waktu::masihBolehDiputus('2026-11-20', $zona, $kini));
cek('tidak boleh diputus setelah tanggal lewat', !Waktu::masihBolehDiputus('2026-11-19', $zona, $kini));

echo $gagal === 0 ? "OK izin-waktu\n" : "$gagal gagal\n";
exit($gagal === 0 ? 0 : 1);
