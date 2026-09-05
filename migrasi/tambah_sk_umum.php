<?php
// SEKALI PAKAI - upload template sk/umum + pasang variabelnya. Prasyarat:
// db/022_sk_umum.sql sudah jalan (sub_jenis_surat "umum" harus sudah ada).
// TIDAK ada blok_tabel_surat yang perlu dipasang - varian ini memang sengaja
// gak punya lampiran daftar pegawai sama sekali.
//
// Jalankan SEKALI: php migrasi/tambah_sk_umum.php
// HAPUS file ini setelah dipakai sekali di produksi.

declare(strict_types=1);
chdir(__DIR__);
require __DIR__ . '/../src/bootstrap.php';

use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\TemplateUpload;
use Aurat\Surat\VariabelRepository;

$jenisSurat = JenisSuratRepository::muat('sk');
if (!$jenisSurat) {
    fwrite(STDERR, "jenis_surat 'sk' tidak ditemukan.\n");
    exit(1);
}
$sub = JenisSuratRepository::subJenisByKode($jenisSurat['id'], 'umum');
if (!$sub) {
    fwrite(STDERR, "sub_jenis 'umum' belum ada - jalankan db/022 dulu.\n");
    exit(1);
}
if (TemplateSuratRepository::templateUntuk($jenisSurat['id'], (int) $sub['id'])) {
    fwrite(STDERR, "Template sk/umum sudah ada - skip, hapus manual dulu lewat admin UI kalau mau ganti.\n");
    exit(0);
}

echo "Upload template sk/umum...\n";
$disimpan = TemplateUpload::simpanDariPath(__DIR__ . '/../templates/sk_umum.docx', 'sk_umum.docx');
$tplId = TemplateSuratRepository::simpanVersiBaru($jenisSurat['id'], (int) $sub['id'], $disimpan['nama_berkas'], $disimpan['nama_asli'], null);
echo "  template_surat_id = $tplId\n";

$peranId = array();
foreach ($jenisSurat['peran_pegawai'] as $p) {
    $peranId[$p['kode']] = (int) $p['id'];
}
$variabelId = array();
foreach (VariabelRepository::semua() as $v) {
    $variabelId[$v['kode']] = (int) $v['id'];
}

$daftar = array(
    'nomor_sk' => null,
    'tanggal_penetapan' => null,
    'tentang' => null,
    'menimbang' => null,
    'mengingat' => null,
    'diktum' => null,
    'penetap_nama_lengkap' => 'penetap',
    'penetap_nip' => 'penetap',
);
$urutan = 10;
foreach ($daftar as $kodeVar => $kodePeran) {
    $pId = $kodePeran !== null ? $peranId[$kodePeran] : null;
    VariabelRepository::pasangKeTemplate($tplId, $variabelId[$kodeVar], $pId, null, $urutan, false);
    $urutan += 10;
    echo "  + $kodeVar" . ($kodePeran ? " (peran: $kodePeran)" : '') . "\n";
}

echo "Selesai.\n";
