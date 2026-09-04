<?php
// SEKALI PAKAI - ganti logo kop hitam-putih jadi versi warna (dicopy dari
// RESTU, assets/img/logo_instansi.png) di 4 template yang makai logo itu di
// header (pernyataan_melaksanakan_tugas, sk/tim_kerja, sk/panitia, undangan).
// surat_perintah_plh & surat_tugas TIDAK disentuh - sudah pakai versi warna
// dari awal. berita_acara_sumpah & izin_keluar_kantor gak punya logo di kop
// sama sekali (kop teks doang) - gak ada yang perlu diganti.
//
// Cuma ganti byte gambarnya (word/media/image1.png), extent/posisi XML gak
// disentuh sama sekali - rasio gambar baru (871:1080 asli, di-resize ke
// 300:373) udah nyaris sama persis sama kotak tampil yang ada (709265:882641
// EMU = rasio 0.8035), jadi Word nampilin tanpa distorsi kelihatan.
//
// Versi LAMA (hitam-putih) TETAP disimpan (gak dihapus, beda dari
// perbaikan Izin Keluar Kantor kemarin yang emang salah konten) - ini
// cuma kosmetik, aman kalau suatu saat mau balik.
//
// Placeholder/macro di file baru PERSIS SAMA kayak versi lama (cuma ganti
// gambar, gak sentuh teks/macro apa pun) - jadi variabel yang udah terpasang
// ke versi lama di-copy definisinya langsung ke versi baru, gak perlu
// mapping manual ulang.
//
// Jalankan SEKALI: php migrasi/ganti_logo_kop_warna.php
// HAPUS file ini setelah dipakai sekali di produksi.

declare(strict_types=1);
chdir(__DIR__);
require __DIR__ . '/../src/bootstrap.php';

use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\TemplateUpload;
use Aurat\Surat\VariabelRepository;

$daftar = array(
    array('kode' => 'pernyataan_melaksanakan_tugas', 'sub' => null, 'file' => 'pernyataan_melaksanakan_tugas.docx'),
    array('kode' => 'sk', 'sub' => 'tim_kerja', 'file' => 'sk_tim_kerja.docx'),
    array('kode' => 'sk', 'sub' => 'panitia', 'file' => 'sk_panitia.docx'),
    array('kode' => 'undangan', 'sub' => null, 'file' => 'undangan.docx'),
);

foreach ($daftar as $d) {
    $jenisSurat = JenisSuratRepository::muat($d['kode']);
    if (!$jenisSurat) {
        fwrite(STDERR, "jenis_surat '{$d['kode']}' tidak ditemukan, skip.\n");
        continue;
    }
    $subId = null;
    if ($d['sub'] !== null) {
        $sub = JenisSuratRepository::subJenisByKode($jenisSurat['id'], $d['sub']);
        if (!$sub) {
            fwrite(STDERR, "sub_jenis '{$d['sub']}' untuk '{$d['kode']}' tidak ditemukan, skip.\n");
            continue;
        }
        $subId = (int) $sub['id'];
    }

    $label = $d['kode'] . ($d['sub'] ? "/{$d['sub']}" : '');
    $templateLama = TemplateSuratRepository::templateUntuk($jenisSurat['id'], $subId);
    if (!$templateLama) {
        fwrite(STDERR, "Belum ada template aktif untuk $label, skip.\n");
        continue;
    }
    $variabelLama = VariabelRepository::variabelUntukTemplate((int) $templateLama['id']);

    echo "Upload versi baru $label...\n";
    $disimpan = TemplateUpload::simpanDariPath(__DIR__ . '/../templates/' . $d['file'], $d['file']);
    $tplBaruId = TemplateSuratRepository::simpanVersiBaru($jenisSurat['id'], $subId, $disimpan['nama_berkas'], $disimpan['nama_asli'], null);
    echo "  template_surat_id baru = $tplBaruId (versi lama id={$templateLama['id']} tetap tersimpan)\n";

    foreach ($variabelLama as $v) {
        VariabelRepository::pasangKeTemplate(
            $tplBaruId, (int) $v['id'], $v['peran_pegawai_surat_id'], $v['wajib_override'], (int) $v['urutan_template'], false
        );
    }
    echo '  ' . count($variabelLama) . " variabel di-copy dari versi lama.\n";
}

echo "Selesai.\n";
