<?php
// SEKALI PAKAI - pindahin 8 template (sk x3, pernyataan_melaksanakan_tugas,
// undangan, surat_tugas, surat_perintah_plh, berita_acara_sumpah) dari
// nomor manual apa adanya ke nomor OTOMATIS (nomor_urut + kode_klasifikasi
// dari jenis_surat + bulan-romawi/tahun dari tanggal dokumen). Lihat
// db/025 & db/026 (harus sudah jalan duluan) + src/Formatter.php::
// nomorSuratOtomatis().
//
// Per template: upload versi baru (macro ${nomor_sk}/${nomor_surat} sudah
// diganti ke ${nomor_lengkap_sk}/${nomor_lengkap}/${nomor_lengkap_bas} di
// templates/*.docx, lihat commit ini), copy semua variabel LAMA ke versi
// baru KECUALI nomor_sk/nomor_surat yang diganti nomor_urut + varian
// nomor_lengkap yang sesuai. Versi lama TETAP tersimpan (bisa di-rollback
// manual lewat admin UI kalau perlu balik ke nomor manual apa adanya).
//
// kode_klasifikasi jenis_surat SENGAJA gak di-set di sini - itu fakta
// institusi yang user isi sendiri lewat admin/jenis_surat.php begitu ada.
//
// Jalankan SEKALI: php migrasi/pasang_nomor_otomatis.php
// HAPUS file ini setelah dipakai sekali di produksi.

declare(strict_types=1);
chdir(__DIR__);
require __DIR__ . '/../src/bootstrap.php';

use Aurat\Database;
use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\TemplateUpload;
use Aurat\Surat\VariabelRepository;

$daftar = array(
    array('kode' => 'sk', 'sub' => 'tim_kerja', 'file' => 'sk_tim_kerja.docx', 'lama' => 'nomor_sk', 'baru_lengkap' => 'nomor_lengkap_sk'),
    array('kode' => 'sk', 'sub' => 'panitia', 'file' => 'sk_panitia.docx', 'lama' => 'nomor_sk', 'baru_lengkap' => 'nomor_lengkap_sk'),
    array('kode' => 'sk', 'sub' => 'umum', 'file' => 'sk_umum.docx', 'lama' => 'nomor_sk', 'baru_lengkap' => 'nomor_lengkap_sk'),
    array('kode' => 'pernyataan_melaksanakan_tugas', 'sub' => null, 'file' => 'pernyataan_melaksanakan_tugas.docx', 'lama' => 'nomor_surat', 'baru_lengkap' => 'nomor_lengkap'),
    array('kode' => 'undangan', 'sub' => null, 'file' => 'undangan.docx', 'lama' => 'nomor_surat', 'baru_lengkap' => 'nomor_lengkap'),
    array('kode' => 'surat_tugas', 'sub' => null, 'file' => 'surat_tugas.docx', 'lama' => 'nomor_surat', 'baru_lengkap' => 'nomor_lengkap'),
    array('kode' => 'surat_perintah_plh', 'sub' => null, 'file' => 'surat_perintah_plh.docx', 'lama' => 'nomor_surat', 'baru_lengkap' => 'nomor_lengkap'),
    array('kode' => 'berita_acara_sumpah', 'sub' => null, 'file' => 'berita_acara_sumpah.docx', 'lama' => 'nomor_surat', 'baru_lengkap' => 'nomor_lengkap_bas'),
);

$variabelId = array();
foreach (VariabelRepository::semua() as $v) {
    $variabelId[$v['kode']] = (int) $v['id'];
}
$pdo = Database::pdo();

foreach ($daftar as $d) {
    $jenisSurat = JenisSuratRepository::muat($d['kode']);
    if (!$jenisSurat) {
        fwrite(STDERR, "jenis_surat '{$d['kode']}' tidak ditemukan, skip.\n");
        continue;
    }
    $subId = null;
    $labelLog = $d['kode'];
    if ($d['sub'] !== null) {
        $sub = JenisSuratRepository::subJenisByKode($jenisSurat['id'], $d['sub']);
        if (!$sub) {
            fwrite(STDERR, "sub_jenis '{$d['sub']}' utk '{$d['kode']}' tidak ditemukan, skip.\n");
            continue;
        }
        $subId = (int) $sub['id'];
        $labelLog .= "/{$d['sub']}";
    }

    $templateLama = TemplateSuratRepository::templateUntuk($jenisSurat['id'], $subId);
    if (!$templateLama) {
        fwrite(STDERR, "Belum ada template aktif utk $labelLog, skip.\n");
        continue;
    }
    $variabelLama = VariabelRepository::variabelUntukTemplate((int) $templateLama['id']);

    echo "Upload versi baru $labelLog...\n";
    $disimpan = TemplateUpload::simpanDariPath(__DIR__ . '/../templates/' . $d['file'], $d['file']);
    $tplBaruId = TemplateSuratRepository::simpanVersiBaru($jenisSurat['id'], $subId, $disimpan['nama_berkas'], $disimpan['nama_asli'], null);
    echo "  template_surat_id baru = $tplBaruId (versi lama id={$templateLama['id']} tetap tersimpan)\n";

    $urutan = 10;
    foreach ($variabelLama as $v) {
        if ($v['kode'] === $d['lama']) {
            continue; // nomor_sk/nomor_surat lama - gak dipasang lagi ke versi baru
        }
        VariabelRepository::pasangKeTemplate($tplBaruId, (int) $v['id'], $v['peran_pegawai_surat_id'], $v['wajib_override'], (int) $v['urutan_template'], false);
        $urutan = max($urutan, (int) $v['urutan_template'] + 10);
    }
    VariabelRepository::pasangKeTemplate($tplBaruId, $variabelId['nomor_urut'], null, null, 0, false);
    VariabelRepository::pasangKeTemplate($tplBaruId, $variabelId[$d['baru_lengkap']], null, null, $urutan, false);
    // kode_klasifikasi_surat dipakai HANYA sbg parameter_variabel turunan
    // di atas, gak pernah jadi placeholder ${...} langsung di docx manapun -
    // tetap wajib dipasang eksplisit ke template (NilaiResolver nolak
    // variabel yg direferensikan tapi gak "terpasang", sama gotcha yg sudah
    // pernah ketemu di AURAT dulu soal pemohon_tmt/nomor_urut_cuti).
    VariabelRepository::pasangKeTemplate($tplBaruId, $variabelId['kode_klasifikasi_surat'], null, null, 0, false);
    echo "  variabel lama di-copy (kecuali {$d['lama']}), + nomor_urut, {$d['baru_lengkap']}, kode_klasifikasi_surat dipasang.\n";

    // Ledger (surat_diterbitkan): kolom "Nomor" sekarang harus baca dari
    // variabel yg BARU, bukan yg lama (yg udah gak dipasang lagi).
    $pdo->prepare('UPDATE jenis_surat SET variabel_nomor_kode = ? WHERE id = ?')
        ->execute(array($d['baru_lengkap'], $jenisSurat['id']));
}

echo "Selesai. INGAT: isi 'Kode Klasifikasi' per jenis surat lewat admin/jenis_surat.php begitu kode aslinya ada dari TU - sebelum itu, nomor_lengkap bakal kosong (aman, fungsinya udah nge-guard).\n";
