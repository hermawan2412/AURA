<?php

// Run: php tests/izin-routing.php
require __DIR__ . '/../src/Izin/Routing.php';

use Aurat\Izin\Routing;

$gagal = 0;
function cek($nama, $hasil, $harap)
{
    global $gagal;
    if ($hasil !== $harap) {
        $gagal++;
        echo "GAGAL $nama\n  dapat: " . json_encode($hasil) . "\n  harap: " . json_encode($harap) . "\n";
    }
}

// pegawai_id: 1 Ketua, 2 Wakil, 5 Panitera, 6 Sekretaris, 3 hakim, 7 staf kepaniteraan, 9 staf sekretariat
$p = array(
    'ketua' => array('pegawai_id' => 1, 'user_aktif' => true),
    'wakil' => array('pegawai_id' => 2, 'user_aktif' => true),
    'panitera' => array('pegawai_id' => 5, 'user_aktif' => true),
    'sekretaris' => array('pegawai_id' => 6, 'user_aktif' => true),
);

// A01 hakim/wakil -> Ketua, tanpa mengetahui
cek('A01 hakim', Routing::tentukan('hakim', $p, array(), 3), array('pemberi' => 'ketua', 'mengetahui' => null));
cek('A01 wakil', Routing::tentukan('wakil', $p, array(), 2), array('pemberi' => 'ketua', 'mengetahui' => null));
// A02/A03 normal -> Panitera/Sekretaris, Wakil diberi tahu
cek('A02', Routing::tentukan('kepaniteraan', $p, array(), 7), array('pemberi' => 'panitera', 'mengetahui' => 'wakil'));
cek('A03', Routing::tentukan('kesekretariatan', $p, array(), 9), array('pemberi' => 'sekretaris', 'mengetahui' => 'wakil'));
// A04 Panitera berhalangan -> Wakil, tanpa mengetahui
cek('A04', Routing::tentukan('kepaniteraan', $p, array(5), 7), array('pemberi' => 'wakil', 'mengetahui' => null));
// A05 Sekretaris + Wakil berhalangan -> Ketua
cek('A05', Routing::tentukan('kesekretariatan', $p, array(6, 2), 9), array('pemberi' => 'ketua', 'mengetahui' => null));
// A06 Wakil berhalangan, Panitera hadir -> tetap Panitera, pemberitahuan tetap ke Wakil
cek('A06', Routing::tentukan('kepaniteraan', $p, array(2), 7), array('pemberi' => 'panitera', 'mengetahui' => 'wakil'));
// Panitera/Sekretaris sebagai pemohon -> Wakil, lalu Ketua
cek('panitera', Routing::tentukan('panitera', $p, array(), 5), array('pemberi' => 'wakil', 'mengetahui' => null));
cek('sekretaris wakil halangan', Routing::tentukan('sekretaris', $p, array(2), 6), array('pemberi' => 'ketua', 'mengetahui' => null));
// A07b Ketua -> PTA, Wakil diberi tahu
cek('A07b', Routing::tentukan('ketua', $p, array(), 1), array('pemberi' => 'pta', 'mengetahui' => 'wakil'));
// A07 Ketua berhalangan untuk hakim -> error, tidak fallback
cek('A07 ketua halangan', isset(Routing::tentukan('hakim', $p, array(1), 3)['error']), true);
// Ketua kosong
$tanpaKetua = $p;
unset($tanpaKetua['ketua']);
cek('A07 ketua kosong', isset(Routing::tentukan('wakil', $tanpaKetua, array(), 2)['error']), true);
// Semua pemberi kepaniteraan habis
cek('semua halangan', isset(Routing::tentukan('kepaniteraan', $p, array(5, 2, 1), 7)['error']), true);
// Akun pejabat nonaktif dianggap tidak tersedia
$panNonaktif = $p;
$panNonaktif['panitera']['user_aktif'] = false;
cek('panitera nonaktif', Routing::tentukan('kepaniteraan', $panNonaktif, array(), 7), array('pemberi' => 'wakil', 'mengetahui' => null));
// Wakil tidak tercatat -> pemberitahuan dilewati, tidak memblokir
$tanpaWakil = $p;
unset($tanpaWakil['wakil']);
cek('tanpa wakil', Routing::tentukan('kepaniteraan', $tanpaWakil, array(), 7), array('pemberi' => 'panitera', 'mengetahui' => null));
// Pemetaan ambigu: pejabat dipetakan ke kelompok staf -> error, bukan menyetujui sendiri
cek('panitera salah kelompok', isset(Routing::tentukan('kepaniteraan', $p, array(), 5)['error']), true);
// Kelompok tak dikenal
cek('tak dipetakan', isset(Routing::tentukan('', $p, array(), 7)['error']), true);

echo $gagal === 0 ? "OK izin-routing\n" : "$gagal gagal\n";
exit($gagal === 0 ? 0 : 1);
