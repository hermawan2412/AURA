<?php
// SEKALI PAKAI - upgrade template Izin Keluar Kantor (sub_jenis Pegawai) ke
// versi yang disamakan dengan Lampiran 2 SK 071/KMA/SK/V/2008 asli (lihat
// db/019_perbaikan_izin_keluar_kantor_pegawai.sql utk detail perubahan &
// alasannya - migrasi itu TETAP harus dijalankan terpisah, skrip ini cuma
// urusan template_surat/template_surat_variabel, bukan pengganti migrasi SQL).
//
// Prasyarat: db/018 (jenis_surat dasarnya) SUDAH jalan, template Pegawai
// versi LAMA (format belum sesuai SK asli) masih ada.
//
// Jalankan SEKALI, urutan internal sudah benar (upload versi baru -> hapus
// versi lama -> jalankan db/019 -> pasang ulang semua variabel ke versi
// baru): php migrasi/perbaiki_izin_keluar_kantor_pegawai.php
// HAPUS file ini setelah dipakai sekali di produksi.

declare(strict_types=1);
chdir(__DIR__);
require __DIR__ . '/../src/bootstrap.php';

use Aurat\Database;
use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\TemplateUpload;
use Aurat\Surat\VariabelRepository;

$jenisSurat = JenisSuratRepository::muat('izin_keluar_kantor');
if (!$jenisSurat) {
    fwrite(STDERR, "jenis_surat 'izin_keluar_kantor' belum ada - jalankan db/018 dulu.\n");
    exit(1);
}
$subPegawai = JenisSuratRepository::subJenisByKode($jenisSurat['id'], 'pegawai');
$templateLama = TemplateSuratRepository::templateUntuk($jenisSurat['id'], (int) $subPegawai['id']);
if (!$templateLama) {
    fwrite(STDERR, "Template Pegawai belum ada sama sekali.\n");
    exit(1);
}
$templateLamaId = (int) $templateLama['id'];

// --- 1) Upload versi baru, hapus versi lama ---
echo "Upload template Pegawai versi baru...\n";
$disimpan = TemplateUpload::simpanDariPath(
    __DIR__ . '/../templates/izin_keluar_kantor_pegawai.docx',
    'izin_keluar_kantor_pegawai.docx'
);
$tplBaruId = TemplateSuratRepository::simpanVersiBaru(
    $jenisSurat['id'], (int) $subPegawai['id'], $disimpan['nama_berkas'], $disimpan['nama_asli'], null
);
echo "  template_surat_id baru = $tplBaruId\n";

echo "Hapus versi lama (id=$templateLamaId)...\n";
TemplateSuratRepository::hapus($templateLamaId);

// --- 2) db/019: variabel baru + hapus peran 'mengetahui' (sekarang gak diblokir FK lagi) ---
echo "Jalankan db/019...\n";
$pdo = Database::pdo();
$pdo->exec(
    "INSERT INTO variabel_surat (kode, label, tipe_input, opsi_pilihan, sumber, field_pegawai, fungsi_pasca, parameter_variabel, wajib_default, urutan_tampil)
     SELECT 'penerima_izin_golongan_ruang', 'Golongan Ruang Penerima Izin', 'text', NULL, 'pegawai', 'golongan_ruang', NULL, NULL, 1, 24
     WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'penerima_izin_golongan_ruang')"
);
$pdo->exec("DELETE FROM variabel_surat WHERE kode IN ('mengetahui_nama_lengkap', 'mengetahui_nip')");
$pdo->prepare("DELETE FROM peran_pegawai_surat WHERE jenis_surat_id = ? AND kode = 'mengetahui'")
    ->execute(array($jenisSurat['id']));
echo "  OK\n";

// --- 3) Pasang semua variabel ke template baru ---
$peranId = array();
foreach (JenisSuratRepository::muat('izin_keluar_kantor')['peran_pegawai'] as $p) {
    $peranId[$p['kode']] = (int) $p['id'];
}
$variabelId = array();
foreach (VariabelRepository::semua() as $v) {
    $variabelId[$v['kode']] = (int) $v['id'];
}

echo "Pasang variabel ke versi baru...\n";
$daftar = array(
    'pemberi_izin_nama_lengkap'    => 'pemberi_izin',
    'pemberi_izin_jabatan'         => 'pemberi_izin',
    'pemberi_izin_unit_kerja'      => 'pemberi_izin',
    'penerima_izin_nama_lengkap'   => 'penerima_izin',
    'penerima_izin_nip'            => 'penerima_izin',
    'penerima_izin_golongan_ruang' => 'penerima_izin',
    'penerima_izin_unit_kerja'     => 'penerima_izin',
    'keperluan_izin'               => null,
    'tanggal_surat'                => null,
);
$urutan = 10;
foreach ($daftar as $kodeVar => $kodePeran) {
    $pId = $kodePeran !== null ? $peranId[$kodePeran] : null;
    VariabelRepository::pasangKeTemplate($tplBaruId, $variabelId[$kodeVar], $pId, null, $urutan, false);
    $urutan += 10;
    echo "  + $kodeVar" . ($kodePeran ? " (peran: $kodePeran)" : '') . "\n";
}

echo "Selesai.\n";
