<?php

// Run: php tests/pecah-poin.php
// Contoh input diambil dari pola isian SK asli (tempelan dari Word: tab, CRLF, label ikut tertempel).
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/DocxGenerator.php';

use Aurat\DocxGenerator;

function cek($teks, array $harap)
{
    $hasil = DocxGenerator::pecahPoin($teks);
    if ($hasil !== $harap) {
        fwrite(STDERR, "GAGAL: " . json_encode($teks) . "\n  dapat: " . json_encode($hasil) . "\n");
        exit(1);
    }
}

cek("a.\tbahwa satu;\r\nb.\tbahwa dua.", array(array('a.', 'bahwa satu;'), array('b.', 'bahwa dua.')));
cek("Menimbang\t:\ta.\tbahwa satu;\r\nb.\tbahwa dua.", array(array('a.', 'bahwa satu;'), array('b.', 'bahwa dua.')));
cek("Mengingat\t:\t1.\tUU 48;\r\n2.\tUU 3.", array(array('1.', 'UU 48;'), array('2.', 'UU 3.')));
cek("KESATU\t:\tSatu;\r\nKEDUA : Dua;\r\nKETIGA\t: \tTiga.", array(array('KESATU', 'Satu;'), array('KEDUA', 'Dua;'), array('KETIGA', 'Tiga.')));
cek("1. Keputusan Sekretaris\nR.I. Nomor 1\n\n2. Lain", array(array('1.', "Keputusan Sekretaris\nR.I. Nomor 1"), array('2.', 'Lain')));
cek("KEPUTUSAN ini berlaku", array(array('', 'KEPUTUSAN ini berlaku')));
cek("bahwa tanpa penanda", array(array('', 'bahwa tanpa penanda')));
cek('', array(array('', '')));

// Hanya baris terakhir yang ditahan, dan hanya kalau paragraf sesudah tabel memakai keepNext.
$baris = '<w:tr><w:tc><w:p><w:r><w:t>%s</w:t></w:r></w:p></w:tc></w:tr>';
$tabel = '<w:tbl>' . sprintf($baris, '1') . sprintf($baris, '2');
$tahan = '<w:tr><w:tc><w:p><w:pPr><w:keepNext/></w:pPr><w:r><w:t>2</w:t></w:r></w:p></w:tc></w:tr>';
$sesudah = '</w:tbl><w:p><w:pPr><w:keepNext/></w:pPr></w:p>';
$hasil = DocxGenerator::tahanBarisTerakhir($tabel . $sesudah);
if ($hasil !== '<w:tbl>' . sprintf($baris, '1') . $tahan . $sesudah) {
    fwrite(STDERR, "GAGAL tahanBarisTerakhir: $hasil\n");
    exit(1);
}
$tanpa = $tabel . '</w:tbl><w:p><w:pPr><w:spacing/></w:pPr></w:p>';
if (DocxGenerator::tahanBarisTerakhir($tanpa) !== $tanpa) {
    fwrite(STDERR, "GAGAL: tabel tanpa keepNext ikut berubah\n");
    exit(1);
}

echo "OK\n";
