<?php
// SEKALI PAKAI - lanjutan pasang_nomor_otomatis.php (sudah dihapus): 3
// template terakhir yang butuh perlakuan beda dari 5 yang sudah beres
// (SK/Pernyataan Tugas/Berita Acara Sumpah, itu cuma nambah 2 variabel ke
// template AKTIF yg sama, gak perlu ganti versi docx):
//
// - surat_perintah_plh: pindah dari 'nomor_lengkap' (sekarang direbut
//   pernyataan_melaksanakan_tugas doang) ke 'nomor_lengkap_plh' (baca
//   jabatan_diplh langsung, bukan pegawai-picker - lihat db/028).
// - undangan & surat_tugas: BARU punya peran 'penandatangan' (Pejabat
//   Penandatangan, db/027) - sebelumnya nama penandatangan STATIS di docx
//   (undangan: teks "Ketua Pengadilan Agama Rantau"; surat_tugas malah
//   GAMBAR stempel TTE tetap "KETUA...Dr. RASYID RIZANI" - dikonfirmasi
//   user BISA siapa aja yg tanda tangan, jadi stempel gambarnya dihapus,
//   diganti teks dinamis sama kayak undangan). templates/undangan.docx &
//   surat_tugas.docx di commit ini sudah macro-nya diganti.
//
// Jalankan SEKALI: php migrasi/pasang_nomor_penandatangan.php
// HAPUS file ini setelah dipakai sekali di produksi.

declare(strict_types=1);
chdir(__DIR__);
require __DIR__ . '/../src/bootstrap.php';

use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\TemplateUpload;
use Aurat\Surat\VariabelRepository;

$variabelId = array();
foreach (VariabelRepository::semua() as $v) {
    $variabelId[$v['kode']] = (int) $v['id'];
}

function auratGantiVersi($kodeJenis, $namaFile, $variabelLamaKode, array $variabelBaru, array $variabelId)
{
    $jenisSurat = JenisSuratRepository::muat($kodeJenis);
    if (!$jenisSurat) {
        fwrite(STDERR, "jenis_surat '$kodeJenis' tidak ditemukan, skip.\n");
        return;
    }
    $peranId = array();
    foreach ($jenisSurat['peran_pegawai'] as $p) {
        $peranId[$p['kode']] = (int) $p['id'];
    }

    $templateLama = TemplateSuratRepository::templateUntuk($jenisSurat['id'], null);
    if (!$templateLama) {
        fwrite(STDERR, "Belum ada template aktif utk $kodeJenis, skip.\n");
        return;
    }
    $variabelLama = VariabelRepository::variabelUntukTemplate((int) $templateLama['id']);

    echo "Upload versi baru $kodeJenis...\n";
    $disimpan = TemplateUpload::simpanDariPath(__DIR__ . '/../templates/' . $namaFile, $namaFile);
    $tplBaruId = TemplateSuratRepository::simpanVersiBaru($jenisSurat['id'], null, $disimpan['nama_berkas'], $disimpan['nama_asli'], null);
    echo "  template_surat_id baru = $tplBaruId (versi lama id={$templateLama['id']} tetap tersimpan)\n";

    $urutan = 10;
    foreach ($variabelLama as $v) {
        if ($v['kode'] === $variabelLamaKode) {
            continue;
        }
        VariabelRepository::pasangKeTemplate($tplBaruId, (int) $v['id'], $v['peran_pegawai_surat_id'], $v['wajib_override'], (int) $v['urutan_template'], false);
        $urutan = max($urutan, (int) $v['urutan_template'] + 10);
    }
    foreach ($variabelBaru as $kodeVar => $kodePeran) {
        $pId = $kodePeran !== null ? (isset($peranId[$kodePeran]) ? $peranId[$kodePeran] : null) : null;
        VariabelRepository::pasangKeTemplate($tplBaruId, $variabelId[$kodeVar], $pId, null, $urutan, false);
        $urutan += 10;
        echo "  + $kodeVar" . ($kodePeran ? " (peran: $kodePeran)" : '') . "\n";
    }
}

auratGantiVersi('surat_perintah_plh', 'surat_perintah_plh.docx', 'nomor_lengkap', array(
    'nomor_lengkap_plh' => null,
    'kode_satker_surat' => null,
), $variabelId);

auratGantiVersi('undangan', 'undangan.docx', 'nomor_lengkap', array(
    'nomor_lengkap_ttd' => null,
    'kode_penandatangan_penandatangan' => 'penandatangan',
    'kode_satker_surat' => null,
    'penandatangan_nama_lengkap' => 'penandatangan',
    'penandatangan_jabatan' => 'penandatangan',
), $variabelId);

auratGantiVersi('surat_tugas', 'surat_tugas.docx', 'nomor_lengkap', array(
    'nomor_lengkap_ttd' => null,
    'kode_penandatangan_penandatangan' => 'penandatangan',
    'kode_satker_surat' => null,
    'penandatangan_nama_lengkap' => 'penandatangan',
    'penandatangan_jabatan' => 'penandatangan',
), $variabelId);

echo "Selesai.\n";
