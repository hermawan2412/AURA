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

echo "OK\n";
