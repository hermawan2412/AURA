<?php

// Run: php tests/izin-dokumen.php   (butuh soffice; DB lokal seperti izin-transisi.php)
// Contoh PDF disalin ke folder yang disebut di akhir output untuk ditinjau manual.
require __DIR__ . '/izin-db.php';

use Aurat\Izin\Dokumen as D;
use Aurat\Izin\Permohonan as P;

$arsip = sys_get_temp_dir() . '/aura-izin-arsip-' . bin2hex(random_bytes(4));
D::$direktoriOverride = $arsip;
$contoh = getenv('AURA_CONTOH_PDF') ?: $arsip . '/contoh';
@mkdir($contoh, 0700, true);

$utc = new DateTimeZone('UTC');
$jumat = new DateTimeImmutable('2026-11-20 05:05:00', $utc);
$keluar = array('jenis' => 'keluar_sementara', 'tanggal' => '2026-11-20', 'jam_mulai' => '14:00', 'jam_kembali' => '15:30', 'alasan' => 'Mengantar anak berobat ke puskesmas');

// TTD contoh (fiktif) untuk Panitera (5) dan Wakil (2); Ketua (1) sengaja tanpa TTD.
@mkdir($arsip . '/ttd', 0700, true);
$ttd = hash_file('sha256', __DIR__ . '/contoh-ttd.png') . '.png';
copy(__DIR__ . '/contoh-ttd.png', $arsip . '/ttd/' . $ttd);
$db->exec("UPDATE pegawai SET ttd_berkas = NULL");
$db->prepare('UPDATE pegawai SET ttd_berkas = ? WHERE id IN (2, 5)')->execute(array($ttd));

// Pegawai kepaniteraan (Panitera menyetujui, Wakil mengetahui)
$idPeg = P::ajukan($db, 7, $keluar, $jumat);
ditolak('belum disetujui tidak bisa terbit', function () use ($db, $idPeg, $jumat) { D::siapkan($db, $idPeg, $jumat); });
P::setujui($db, 5, $idPeg, 1, '', $jumat);
$d = D::terbitkan($db, $idPeg, $jumat);
cek('pegawai: siap', $d['status'] === 'siap', $d);
cek('pegawai: nomor register', $d['nomor'] === 'IZN/2026/1', $d['nomor']);
cek('pegawai: PDF ada & hash cocok', is_file(D::pathPdf($d)) && hash_file('sha256', D::pathPdf($d)) === $d['sha256']);
$nilai = json_decode($d['snapshot_nilai'], true);
cek('pegawai: waktu izin eksplisit', strpos($nilai['waktu_izin'], 'Jumat, 20 November 2026, pukul 14.00 WITA s.d. 15.30 WITA') === 0, $nilai['waktu_izin']);
cek('pegawai: mengetahui Wakil', $nilai['mengetahui_nama_lengkap'] === 'Uji Wakil', $nilai);
cek('pegawai: jabatan mengetahui otomatis', $nilai['mengetahui_jabatan'] === 'Wakil Ketua Pengadilan Agama Rantau', $nilai);
cek('pegawai: keterangan AURA', strpos($nilai['keterangan_aura'], 'Disetujui melalui AURA oleh Uji Panitera pada 20 November 2026 pukul 13.05 WITA') === 0, $nilai['keterangan_aura']);
cek('TTD pemberi & mengetahui dibekukan di snapshot', $nilai['_ttd'] === array('ttd_pemberi' => $ttd, 'ttd_mengetahui' => $ttd), $nilai['_ttd']);
cek('PDF memuat gambar TTD', substr_count(file_get_contents(D::pathPdf($d)), '/Subtype /Image') + substr_count(file_get_contents(D::pathPdf($d)), '/Subtype/Image') >= 2);
copy(D::pathPdf($d), $contoh . '/contoh-izin-pegawai.pdf');

// Idempoten: terbit ulang tidak membuat nomor/berkas baru
$mtime = filemtime(D::pathPdf($d));
$d2 = D::terbitkan($db, $idPeg, $jumat);
cek('idempoten: nomor sama', $d2['nomor'] === $d['nomor'] && $d2['id'] === $d['id']);
cek('idempoten: berkas tidak dirender ulang', filemtime(D::pathPdf($d2)) === $mtime);

// Hakim pulang awal (Ketua menyetujui) + A16 konversi gagal lalu retry
$idHak = P::ajukan($db, 3, array('jenis' => 'pulang_awal', 'tanggal' => '2026-11-20', 'jam_mulai' => '15:00', 'alasan' => 'Menghadiri pemakaman kerabat'), $jumat);
P::setujui($db, 1, $idHak, 1, '', $jumat);
putenv('AURA_SOFFICE=/usr/bin/false');
$d = D::terbitkan($db, $idHak, $jumat);
cek('A16 gagal konversi -> status gagal', $d['status'] === 'gagal', $d);
cek('A16 keputusan tetap disetujui', P::muat($db, $idHak)['status'] === 'disetujui');
cek('A16 tidak ada PDF palsu', !is_file($arsip . '/2026/' . $idHak . '.pdf'));
putenv('AURA_SOFFICE');
$d3 = D::render($db, $idHak);
cek('A16 retry siap dengan nomor sama', $d3['status'] === 'siap' && $d3['nomor'] === $d['nomor'], $d3);
$nilai = json_decode($d3['snapshot_nilai'], true);
cek('hakim: pulang awal tanpa jam kembali fiktif', $nilai['jam_selesai_izin'] === 'selesai jam kerja', $nilai);
copy(D::pathPdf($d3), $contoh . '/contoh-izin-hakim.pdf');

// A16 crash setelah berkas dipindah: DB masih 'diproses' tapi berkas ada -> rekonsiliasi
$db->prepare("UPDATE izin_dokumen SET status = 'diproses', sha256 = NULL WHERE id = ?")->execute(array($d3['id']));
$d4 = D::render($db, $idHak);
cek('A16 rekonsiliasi tanpa render ulang', $d4['status'] === 'siap' && $d4['sha256'] === $d3['sha256']);

// A17 template sumber berubah setelah final -> arsip & snapshot tetap
$hashLama = hash_file('sha256', D::pathPdf($d3));
$db->exec("UPDATE pegawai SET nama_lengkap = 'Nama Berubah' WHERE id = 3");
cek('A17 arsip identik setelah data pegawai berubah', hash_file('sha256', D::pathPdf(D::terbitkan($db, $idHak, $jumat))) === $hashLama);
$db->exec("UPDATE pegawai SET nama_lengkap = 'Uji Hakim Satu' WHERE id = 3");

// Nomor register berurutan untuk surat berikutnya
$idLagi = P::ajukan($db, 9, array('jenis' => 'pulang_awal', 'tanggal' => '2026-11-20', 'jam_mulai' => '16:00', 'alasan' => 'Menjemput anak di sekolah'), $jumat);
P::setujui($db, 6, $idLagi, 1, '', $jumat);
cek('nomor register berurutan', D::terbitkan($db, $idLagi, $jumat)['nomor'] === 'IZN/2026/3');

echo $gagal === 0 ? "OK izin-dokumen (contoh PDF: $contoh)\n" : "$gagal gagal\n";
exit($gagal === 0 ? 0 : 1);
