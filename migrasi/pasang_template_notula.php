<?php
// Sekali-jalan: pasang templates/notula.docx sebagai template utama jenis_surat
// 'notula' + pasangkan 13 variabelnya (7 reuse by-kode dari Undangan, 6 baru
// khusus Notula). Padanan CLI dari alur admin/template_surat.php - lihat
// migrasi/pasang_template_lampiran_undangan.php (sudah dihapus, sama pola).
//
// Jalan lewat: php migrasi/pasang_template_notula.php
// Hapus berkas ini setelah dijalankan sukses di produksi.

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Database;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\TemplateUpload;
use Aurat\Surat\VariabelRepository;

$pdo = Database::pdo();

$jenisSurat = $pdo->query("SELECT id FROM jenis_surat WHERE kode = 'notula'")->fetch();
if (!$jenisSurat) {
    fwrite(STDERR, "jenis_surat 'notula' tidak ditemukan - jalankan db/034_notula.sql dulu.\n");
    exit(1);
}
$jenisSuratId = (int) $jenisSurat['id'];

$sudahAda = TemplateSuratRepository::templateUntuk($jenisSuratId, null, 'utama');
if ($sudahAda) {
    fwrite(STDERR, "Template utama utk 'notula' sudah ada (id={$sudahAda['id']}) - skip.\n");
    exit(0);
}

$sumberDocx = __DIR__ . '/../templates/notula.docx';
$disimpan = TemplateUpload::simpanDariPath($sumberDocx, 'notula.docx');
$templateSuratId = TemplateSuratRepository::simpanVersiBaru(
    $jenisSuratId, null, $disimpan['nama_berkas'], $disimpan['nama_asli'], null, 'utama'
);
echo "Template notula dibuat: id={$templateSuratId}\n";

// kode => peran_kode (null = bukan sumber pegawai, gak butuh peran).
$variabelDipasang = array(
    'dasar' => null,
    'hari' => null,
    'tanggal_acara' => null,
    'waktu' => null,
    'tempat' => null,
    'nama_acara' => null,
    'peserta_rapat' => null,
    'isi_notula' => null,
    'foto_notula' => null,
    'notulis_nama_lengkap' => 'notulis',
    'notulis_nip' => 'notulis',
    'pejabat_acara_nama_lengkap' => 'pejabat_acara',
    'pejabat_acara_nip' => 'pejabat_acara',
);

$peranStmt = $pdo->prepare("SELECT id, kode FROM peran_pegawai_surat WHERE jenis_surat_id = ?");
$peranStmt->execute(array($jenisSuratId));
$peranById = array();
foreach ($peranStmt->fetchAll() as $p) {
    $peranById[$p['kode']] = (int) $p['id'];
}

$urutan = 10;
foreach ($variabelDipasang as $kode => $peranKode) {
    $v = VariabelRepository::cariByKode($kode);
    if (!$v) {
        fwrite(STDERR, "PERINGATAN: variabel_surat kode '{$kode}' tidak ditemukan - cek migrasi 034 sudah jalan.\n");
        continue;
    }
    $peranId = $peranKode !== null ? (isset($peranById[$peranKode]) ? $peranById[$peranKode] : null) : null;
    if ($peranKode !== null && $peranId === null) {
        fwrite(STDERR, "PERINGATAN: peran '{$peranKode}' tidak ditemukan utk jenis_surat notula - '{$kode}' dilewati.\n");
        continue;
    }
    VariabelRepository::pasangKeTemplate($templateSuratId, (int) $v['id'], $peranId, null, $urutan, false);
    echo "  + {$kode} terpasang" . ($peranId ? " (peran id={$peranId})" : '') . "\n";
    $urutan += 10;
}

echo "Selesai.\n";
