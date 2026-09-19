<?php
// Sekali-jalan: ganti template Notula aktif dengan templates/notula.docx yang baru
// (teks "Pejabat yang punya Acara" -> "Pejabat yang Mengundang"). Membuat versi
// baru lewat jalur yang sama dengan admin/template_surat.php, lalu menyalin
// tautan variabel dari versi lama (tautan tidak ikut pindah otomatis).
// Versi lama tetap ada dan bisa diaktifkan kembali dari halaman admin (rollback).
//
// Jalan: php migrasi/ganti_template_notula.php   (sebagai user yang boleh menulis templates/uploaded/, mis. www-data)
// Hapus berkas ini setelah dijalankan sukses di produksi.

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Database;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\TemplateUpload;

$pdo = Database::pdo();

$jenis = $pdo->query("SELECT id FROM jenis_surat WHERE kode = 'notula'")->fetch();
if (!$jenis) {
    fwrite(STDERR, "jenis_surat 'notula' tidak ditemukan.\n");
    exit(1);
}
$jenisId = (int) $jenis['id'];

$lama = TemplateSuratRepository::templateUntuk($jenisId, null, 'utama');
if (!$lama) {
    fwrite(STDERR, "Belum ada template Notula aktif - pakai migrasi/pasang_template_notula.php (sudah dihapus) atau admin UI.\n");
    exit(1);
}

$sumber = __DIR__ . '/../templates/notula.docx';
$berkasLama = TemplateUpload::direktoriUpload() . '/' . $lama['nama_berkas'];
if (is_file($berkasLama) && hash_file('sha256', $berkasLama) === hash_file('sha256', $sumber)) {
    echo "Template aktif sudah sama dengan templates/notula.docx - tidak ada yang dilakukan.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $disimpan = TemplateUpload::simpanDariPath($sumber, 'notula.docx');
    $baruId = TemplateSuratRepository::simpanVersiBaru($jenisId, null, $disimpan['nama_berkas'], $disimpan['nama_asli'], null, 'utama');

    $salin = $pdo->prepare(
        'INSERT INTO template_surat_variabel (template_surat_id, variabel_surat_id, peran_pegawai_surat_id, wajib_override, urutan_tampil, terdeteksi_otomatis)
         SELECT ?, variabel_surat_id, peran_pegawai_surat_id, wajib_override, urutan_tampil, terdeteksi_otomatis
         FROM template_surat_variabel WHERE template_surat_id = ?'
    );
    $salin->execute(array($baruId, (int) $lama['id']));
    $jumlah = $salin->rowCount();

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'GAGAL: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Template Notula versi {$lama['versi']} -> versi baru (id={$baruId}), {$jumlah} tautan variabel disalin.\n";
