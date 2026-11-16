<?php

// Run: php tests/izin-transisi.php
// Tes integrasi DB. HANYA jalan terhadap database lokal (nama berakhiran _local/_test):
// isi tabel izin_* DIHAPUS setiap kali jalan. Butuh data dari tests/data-sintetis-izin.sql.
require __DIR__ . '/izin-db.php';

use Aurat\Izin\Permohonan as P;

$utc = new DateTimeZone('UTC');
$jumat = new DateTimeImmutable('2026-11-20 05:05:00', $utc); // Jumat 13:05 WITA
$sabtu = new DateTimeImmutable('2026-11-21 02:00:00', $utc);
// user id: 1 ketua, 2 wakil, 3 hakim1, 5 panitera, 6 sekretaris, 7 kpn1, 8 kpn2, 9 skr1, 10 skr2, 11 admin
$izin = function ($jamMulai, $jamKembali, $jenis = 'keluar_sementara') {
    return array('jenis' => $jenis, 'tanggal' => '2026-11-20', 'jam_mulai' => $jamMulai, 'jam_kembali' => $jamKembali, 'alasan' => 'Mengurus keperluan keluarga');
};

// --- A02 kepaniteraan normal ---
$id1 = P::ajukan($db, 7, $izin('14:00', '15:00'), $jumat);
$p = P::muat($db, $id1);
cek('A02 status menunggu', $p['status'] === 'menunggu_persetujuan', $p['status']);
cek('A02 snapshot mengetahui = Wakil', (int) $p['mengetahui_user_id'] === 2);
$t = P::tahap($db, $id1);
cek('A02 satu tahap ke Panitera', count($t) === 1 && (int) $t[0]['pemberi_user_id'] === 5, $t);
cek('A02 notifikasi ke Panitera', satu($db, 'SELECT COUNT(*) FROM izin_notifikasi WHERE permohonan_id=? AND penerima_user_id=5', array($id1)) == 1);
cek('A02 WA antre ke Panitera', satu($db, "SELECT status FROM izin_wa_outbox WHERE permohonan_id=? AND penerima_user_id=5", array($id1)) === 'antre');
cek('A02 tautan di WA', strpos(satu($db, 'SELECT pesan FROM izin_wa_outbox WHERE permohonan_id=?', array($id1)), 'izin/detail.php?id=' . $id1) !== false);
cek('A02 alasan tidak masuk WA', strpos(satu($db, 'SELECT pesan FROM izin_wa_outbox WHERE permohonan_id=?', array($id1)), 'keluarga') === false);

// --- A09 bukan pemberi ---
ditolak('A09 admin approve', function () use ($db, $id1, $jumat) { P::setujui($db, 11, $id1, 1, '', $jumat); });
ditolak('A09 wakil approve', function () use ($db, $id1, $jumat) { P::setujui($db, 2, $id1, 1, '', $jumat); });
ditolak('pemohon approve sendiri', function () use ($db, $id1, $jumat) { P::setujui($db, 7, $id1, 1, '', $jumat); });

// --- Akses baca ---
cek('lihat: pemohon', P::bolehLihat($db, $p, 7, false));
cek('lihat: pemberi', P::bolehLihat($db, $p, 5, false));
cek('lihat: mengetahui', P::bolehLihat($db, $p, 2, false));
cek('lihat: admin', P::bolehLihat($db, $p, 11, true));
cek('lihat: staf lain ditolak', !P::bolehLihat($db, $p, 9, false));

// --- A11 setujui + klik ganda ---
P::setujui($db, 5, $id1, 1, '', $jumat);
$p = P::muat($db, $id1);
cek('A11 disetujui', $p['status'] === 'disetujui' && (int) $p['versi'] === 2, $p);
ditolak('A11 klik ganda', function () use ($db, $id1, $jumat) { P::setujui($db, 5, $id1, 1, '', $jumat); });
cek('A11 tahap selesai satu', satu($db, "SELECT COUNT(*) FROM izin_tahap WHERE permohonan_id=? AND status='selesai'", array($id1)) == 1);
cek('A02 Wakil diberi tahu setelah disetujui', satu($db, "SELECT COUNT(*) FROM izin_notifikasi WHERE permohonan_id=? AND penerima_user_id=2 AND peristiwa='mengetahui'", array($id1)) == 1);
cek('A02 pemohon diberi tahu', satu($db, "SELECT COUNT(*) FROM izin_notifikasi WHERE permohonan_id=? AND penerima_user_id=7 AND peristiwa='disetujui'", array($id1)) == 1);
cek('A27 WA tugas usang dibatalkan', satu($db, "SELECT status FROM izin_wa_outbox WHERE permohonan_id=? AND penerima_user_id=5", array($id1)) === 'dibatalkan');

// --- A12 bentrok ---
ditolak('A12 tumpang tindih', function () use ($db, $izin, $jumat) { P::ajukan($db, 7, $izin('14:30', '15:30'), $jumat); });
$id2 = P::ajukan($db, 7, $izin('15:00', '16:00'), $jumat);
cek('A12 berdampingan lolos', $id2 > 0);

// --- approve vs batal ---
ditolak('batal tanpa alasan', function () use ($db, $id2, $jumat) { P::batalkan($db, 7, $id2, 1, '', $jumat); });
ditolak('batal oleh orang lain', function () use ($db, $id2, $jumat) { P::batalkan($db, 8, $id2, 1, 'iseng', $jumat); });
P::batalkan($db, 7, $id2, 1, 'Tidak jadi keluar', $jumat);
ditolak('approve setelah batal', function () use ($db, $id2, $jumat) { P::setujui($db, 5, $id2, 1, '', $jumat); });
cek('batal: status', P::muat($db, $id2)['status'] === 'dibatalkan');
cek('batal: pemberi diberi tahu', satu($db, "SELECT COUNT(*) FROM izin_notifikasi WHERE permohonan_id=? AND penerima_user_id=5 AND peristiwa='dibatalkan'", array($id2)) == 1);
$id2b = P::ajukan($db, 7, $izin('15:00', '16:00'), $jumat);
cek('A22 interval batal tidak memblokir', $id2b > 0);

// --- A04 Panitera berhalangan -> Wakil; lalu dialihkan ---
$db->exec("INSERT INTO izin_berhalangan (pegawai_id, mulai, selesai, jenis, dibuat_oleh, dibuat_at) VALUES (5, '2026-11-20 00:00:00', '2026-11-21 00:00:00', 'dinas_luar', 11, '2026-11-20 00:00:00')");
ditolak('pemberi berhalangan tidak bisa memutus tugas lama', function () use ($db, $id2b, $jumat) { P::setujui($db, 5, $id2b, 1, '', $jumat); });
$id3 = P::ajukan($db, 8, $izin('13:30', '14:00'), $jumat);
$p3 = P::muat($db, $id3);
cek('A04 pemberi Wakil', (int) P::tahap($db, $id3)[0]['pemberi_user_id'] === 2);
cek('A04 tanpa mengetahui', $p3['mengetahui_user_id'] === null);
// tugas lama id2b (ke Panitera yang kini berhalangan) dialihkan admin -> Wakil
ditolak('alihkan tanpa alasan', function () use ($db, $id2b, $jumat) { P::alihkan($db, 11, $id2b, 1, '', $jumat); });
P::alihkan($db, 11, $id2b, 1, 'Panitera dinas luar', $jumat);
$t = P::tahap($db, $id2b);
cek('A14 tahap lama diganti, baru ke Wakil', $t[0]['status'] === 'diganti' && $t[1]['status'] === 'menunggu' && (int) $t[1]['pemberi_user_id'] === 2, $t);
ditolak('A14 pemberi lama tak bisa bertindak', function () use ($db, $id2b, $jumat) { P::setujui($db, 5, $id2b, 2, '', $jumat); });
cek('A14 WA tugas lama dibatalkan', satu($db, "SELECT status FROM izin_wa_outbox WHERE tahap_id=?", array($t[0]['id'])) === 'dibatalkan');
cek('A14 WA tugas baru antre', satu($db, "SELECT status FROM izin_wa_outbox WHERE tahap_id=?", array($t[1]['id'])) === 'antre');
ditolak('alihkan tanpa perubahan rute', function () use ($db, $id2b, $jumat) { P::alihkan($db, 11, $id2b, 2, 'coba lagi', $jumat); });
P::setujui($db, 2, $id2b, 2, '', $jumat);
cek('Wakil pengganti menyetujui', P::muat($db, $id2b)['status'] === 'disetujui');

// --- tolak wajib alasan ---
ditolak('tolak tanpa alasan', function () use ($db, $id3, $jumat) { P::tolak($db, 2, $id3, 1, ' ', $jumat); });
P::tolak($db, 2, $id3, 1, 'Sedang ada sidang', $jumat);
cek('ditolak', P::muat($db, $id3)['status'] === 'ditolak');
cek('A30 pemohon tanpa nomor -> WA gagal tercatat, keputusan tetap tersimpan',
    satu($db, 'SELECT error FROM izin_wa_outbox WHERE permohonan_id=? AND penerima_user_id=8', array($id3)) === 'Nomor WA tidak tersedia');

// --- A01 hakim -> Ketua ---
$id4 = P::ajukan($db, 3, $izin('14:00', null, 'pulang_awal'), $jumat);
cek('A01 tahap ke Ketua', (int) P::tahap($db, $id4)[0]['pemberi_user_id'] === 1);
cek('A01 tanpa mengetahui', P::muat($db, $id4)['mengetahui_user_id'] === null);
// tanggal lewat: persetujuan diblokir, penolakan boleh
ditolak('setujui setelah tanggal lewat', function () use ($db, $id4, $sabtu) { P::setujui($db, 1, $id4, 1, '', $sabtu); });
P::tolak($db, 1, $id4, 1, 'Sudah lewat', $sabtu);

// --- Ketua: di luar ruang lingkup; "Mulai sekarang" memakai jam server ---
ditolak('Ketua tidak mengajukan lewat AURA', function () use ($db, $jumat) {
    P::ajukan($db, 1, array('jenis' => 'pulang_awal', 'mulai_sekarang' => true, 'alasan' => 'Rapat di luar kantor'), $jumat);
});
$id5 = P::ajukan($db, 4, array('jenis' => 'pulang_awal', 'mulai_sekarang' => true, 'alasan' => 'Menjemput anak di sekolah'), $jumat);
cek('A28 jam mulai dari server', P::muat($db, $id5)['jam_mulai'] === '13:05:00', P::muat($db, $id5)['jam_mulai']);

// --- cabut ---
ditolak('cabut oleh bukan pemberi', function () use ($db, $id1, $jumat) { P::cabut($db, 2, $id1, 2, 'x x x', $jumat); });
P::cabut($db, 5, $id1, 2, 'Dibutuhkan untuk sidang', $jumat);
cek('dicabut', P::muat($db, $id1)['status'] === 'dicabut');
cek('cabut: pemohon & mengetahui diberi tahu', satu($db, "SELECT COUNT(*) FROM izin_notifikasi WHERE permohonan_id=? AND peristiwa='dicabut'", array($id1)) == 2);

// --- tidak dipetakan / mode nonaktif ---
ditolak('kelompok tidak dipetakan', function () use ($db, $izin, $jumat) { P::ajukan($db, 10, $izin('14:00', '15:00'), $jumat); });
ditolak('admin tanpa NIP tidak bisa mengajukan', function () use ($db, $izin, $jumat) { P::ajukan($db, 11, $izin('14:00', '15:00'), $jumat); });

// --- A13 audit gagal -> rollback ---
$sebelum = satu($db, 'SELECT COUNT(*) FROM izin_permohonan');
$db->exec("CREATE TRIGGER uji_gagalkan_audit BEFORE INSERT ON izin_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'uji audit gagal'");
try {
    P::ajukan($db, 9, $izin('14:00', '15:00'), $jumat);
    cek('A13 harusnya gagal', false);
} catch (PDOException $e) {
    cek('A13 tidak ada permohonan tersisa', satu($db, 'SELECT COUNT(*) FROM izin_permohonan') == $sebelum);
    cek('A13 tidak ada notifikasi yatim', satu($db, 'SELECT COUNT(*) FROM izin_notifikasi n LEFT JOIN izin_permohonan p ON p.id = n.permohonan_id WHERE p.id IS NULL') == 0);
} finally {
    $db->exec('DROP TRIGGER IF EXISTS uji_gagalkan_audit');
}

$db->exec("UPDATE izin_pengaturan SET mode = 'pemeliharaan' WHERE id = 1");
ditolak('pemeliharaan menolak pengajuan', function () use ($db, $izin, $jumat) { P::ajukan($db, 9, $izin('14:00', '15:00'), $jumat); });
$db->exec("UPDATE izin_pengaturan SET mode = 'nonaktif' WHERE id = 1");
ditolak('nonaktif menolak pengajuan', function () use ($db, $izin, $jumat) { P::ajukan($db, 9, $izin('14:00', '15:00'), $jumat); });

cek('audit tercatat per aksi', satu($db, 'SELECT COUNT(*) FROM izin_audit') >= 12, satu($db, 'SELECT COUNT(*) FROM izin_audit'));

echo $gagal === 0 ? "OK izin-transisi\n" : "$gagal gagal\n";
exit($gagal === 0 ? 0 : 1);
