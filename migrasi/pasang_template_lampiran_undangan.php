<?php
// Sekali-jalan: pasang templates/daftar_hadir_undangan.docx sebagai template
// LAMPIRAN (tipe_dokumen='lampiran') buat jenis_surat 'undangan', + pasangkan
// 9 variabel yang di-reuse by-kode dari template utama Undangan. Ini padanan
// CLI dari alur upload admin/template_surat.php (pakai TemplateUpload::
// simpanDariPath() yang memang dibikin buat skenario ini, bukan file transfer
// biasa - lihat catatan lama soal kenapa file transfer gak pernah kepake).
//
// Jalan lewat: php migrasi/pasang_template_lampiran_undangan.php
// Hapus berkas ini setelah dijalankan sukses di produksi (pola sama kayak
// migrasi/pasang_nomor_*.php sebelumnya - sekali pakai, jangan ikut nyangkut).

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Database;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\TemplateUpload;
use Aurat\Surat\VariabelRepository;

$pdo = Database::pdo();

$jenisSurat = $pdo->query("SELECT id FROM jenis_surat WHERE kode = 'undangan'")->fetch();
if (!$jenisSurat) {
    fwrite(STDERR, "jenis_surat 'undangan' tidak ditemukan.\n");
    exit(1);
}
$jenisSuratId = (int) $jenisSurat['id'];

$sudahAda = TemplateSuratRepository::templateUntuk($jenisSuratId, null, 'lampiran');
if ($sudahAda) {
    fwrite(STDERR, "Template lampiran utk 'undangan' sudah ada (id={$sudahAda['id']}) - skip, tidak bikin versi baru.\n");
    exit(0);
}

$sumberDocx = __DIR__ . '/../templates/daftar_hadir_undangan.docx';
$disimpan = TemplateUpload::simpanDariPath($sumberDocx, 'daftar_hadir_undangan.docx');
$templateSuratId = TemplateSuratRepository::simpanVersiBaru(
    $jenisSuratId, null, $disimpan['nama_berkas'], $disimpan['nama_asli'], null, 'lampiran'
);
echo "Template lampiran dibuat: id={$templateSuratId}\n";

// Kode variabel yang di-reuse dari template utama Undangan (bukan bikin
// variabel baru - variabel_surat.jenis_kegiatan & penandatangan_nip sudah
// dibuat lewat db/033_lampiran_undangan.sql, sisanya sudah ada dari sebelumnya).
$kodeDipakai = array(
    'jenis_kegiatan', 'nama_acara', 'hari', 'tanggal_acara', 'waktu', 'tempat',
    'tanggal_surat', 'penandatangan_nama_lengkap', 'penandatangan_nip',
);
// peran_pegawai_surat 'penandatangan' milik jenis_surat 'undangan' - id-nya
// beda per instalasi/server, WAJIB dicari dinamis, jangan hardcode.
$peran = $pdo->prepare("SELECT id FROM peran_pegawai_surat WHERE jenis_surat_id = ? AND kode = 'penandatangan'");
$peran->execute(array($jenisSuratId));
$peranRow = $peran->fetch();
$peranPenandatanganId = $peranRow ? (int) $peranRow['id'] : null;

$urutan = 10;
foreach ($kodeDipakai as $kode) {
    $v = VariabelRepository::cariByKode($kode);
    if (!$v) {
        fwrite(STDERR, "PERINGATAN: variabel_surat kode '{$kode}' tidak ditemukan - lewati, cek migrasi 033 sudah jalan.\n");
        continue;
    }
    $butuhPeran = $v['sumber'] === 'pegawai';
    VariabelRepository::pasangKeTemplate(
        $templateSuratId, (int) $v['id'],
        $butuhPeran ? $peranPenandatanganId : null,
        null, $urutan, false
    );
    echo "  + {$kode} terpasang" . ($butuhPeran ? " (peran id={$peranPenandatanganId})" : '') . "\n";
    $urutan += 10;
}

echo "Selesai.\n";
