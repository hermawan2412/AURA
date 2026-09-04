<?php
// SEKALI PAKAI - upload 2 template (Pegawai/Hakim) jenis_surat "izin_keluar_kantor"
// + pasang variabel-nya, lewat jalur resmi non-HTTP (TemplateUpload::simpanDariPath()
// + TemplateSuratRepository::simpanVersiBaru() + VariabelRepository::pasangKeTemplate()) -
// BUKAN copy file mentah ke templates/uploaded/, itu gak pernah cukup (lihat
// project_aurat_mail_app.md "SOLVED 2026-08-06": app selalu baca lewat
// template_surat.nama_berkas, gak pernah baca templates/*.docx langsung lagi
// setelah migrasi awal).
//
// Prasyarat: db/018_izin_keluar_kantor.sql SUDAH dijalankan duluan (jenis_surat/
// sub_jenis_surat/peran_pegawai_surat/variabel_surat harus sudah ada).
//
// Jalankan SEKALI: php database/deploy_izin_keluar_kantor.php
// Sudah dijalankan sukses di dev lokal (2026-09-04, via HTTP admin UI, verified end
// to end) - skrip ini reproduksi langkah yang sama buat produksi tanpa perlu
// kredensial admin/browser session. HAPUS file ini setelah dipakai sekali di
// produksi, jangan dibiarin nempel.

declare(strict_types=1);
chdir(__DIR__);
require __DIR__ . '/../src/bootstrap.php';

use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\TemplateUpload;
use Aurat\Surat\VariabelRepository;

$jenisSurat = JenisSuratRepository::muat('izin_keluar_kantor');
if (!$jenisSurat) {
    fwrite(STDERR, "jenis_surat 'izin_keluar_kantor' belum ada - jalankan db/018 dulu.\n");
    exit(1);
}

$peranId = array();
foreach ($jenisSurat['peran_pegawai'] as $p) {
    $peranId[$p['kode']] = (int) $p['id'];
}

$variabelId = array();
foreach (VariabelRepository::semua() as $v) {
    $variabelId[$v['kode']] = (int) $v['id'];
}

function auratCekTemplateBelumAda($jenisSuratId, $subJenisSuratId)
{
    $existing = TemplateSuratRepository::templateUntuk($jenisSuratId, $subJenisSuratId);
    if ($existing) {
        fwrite(STDERR, "Template sudah ada (id={$existing['id']}) untuk jenis_surat=$jenisSuratId sub_jenis=$subJenisSuratId - skip, hapus manual dulu lewat admin UI kalau mau ganti.\n");
        return false;
    }
    return true;
}

function auratPasang($templateSuratId, array $variabelId, array $peranId, array $daftar)
{
    // $daftar: [kode_variabel => kode_peran atau null]
    $urutan = 10;
    foreach ($daftar as $kodeVar => $kodePeran) {
        if (!isset($variabelId[$kodeVar])) {
            throw new RuntimeException("Variabel '$kodeVar' tidak ditemukan di variabel_surat.");
        }
        $pId = $kodePeran !== null ? $peranId[$kodePeran] : null;
        VariabelRepository::pasangKeTemplate($templateSuratId, $variabelId[$kodeVar], $pId, null, $urutan, false);
        $urutan += 10;
        echo "  + $kodeVar" . ($kodePeran ? " (peran: $kodePeran)" : '') . "\n";
    }
}

// --- Sub-jenis Pegawai ---
$subPegawai = JenisSuratRepository::subJenisByKode($jenisSurat['id'], 'pegawai');
if (!$subPegawai) {
    fwrite(STDERR, "sub_jenis 'pegawai' belum ada.\n");
    exit(1);
}
if (auratCekTemplateBelumAda($jenisSurat['id'], $subPegawai['id'])) {
    echo "Upload template Pegawai...\n";
    $disimpan = TemplateUpload::simpanDariPath(
        __DIR__ . '/../templates/izin_keluar_kantor_pegawai.docx',
        'izin_keluar_kantor_pegawai.docx'
    );
    $tplId = TemplateSuratRepository::simpanVersiBaru(
        $jenisSurat['id'], (int) $subPegawai['id'], $disimpan['nama_berkas'], $disimpan['nama_asli'], null
    );
    echo "  template_surat_id = $tplId\n";
    auratPasang($tplId, $variabelId, $peranId, array(
        'pemberi_izin_nama_lengkap' => 'pemberi_izin',
        'pemberi_izin_nip'          => 'pemberi_izin',
        'pemberi_izin_jabatan'      => 'pemberi_izin',
        'pemberi_izin_unit_kerja'   => 'pemberi_izin',
        'penerima_izin_nama_lengkap' => 'penerima_izin',
        'penerima_izin_nip'          => 'penerima_izin',
        'penerima_izin_jabatan'      => 'penerima_izin',
        'penerima_izin_unit_kerja'   => 'penerima_izin',
        'mengetahui_nama_lengkap'    => 'mengetahui',
        'keperluan_izin'             => null,
        'tanggal_surat'              => null,
    ));
}

// --- Sub-jenis Hakim ---
$subHakim = JenisSuratRepository::subJenisByKode($jenisSurat['id'], 'hakim');
if (!$subHakim) {
    fwrite(STDERR, "sub_jenis 'hakim' belum ada.\n");
    exit(1);
}
if (auratCekTemplateBelumAda($jenisSurat['id'], $subHakim['id'])) {
    echo "Upload template Hakim...\n";
    $disimpan = TemplateUpload::simpanDariPath(
        __DIR__ . '/../templates/izin_keluar_kantor_hakim.docx',
        'izin_keluar_kantor_hakim.docx'
    );
    $tplId = TemplateSuratRepository::simpanVersiBaru(
        $jenisSurat['id'], (int) $subHakim['id'], $disimpan['nama_berkas'], $disimpan['nama_asli'], null
    );
    echo "  template_surat_id = $tplId\n";
    auratPasang($tplId, $variabelId, $peranId, array(
        'pemberi_izin_nama_lengkap' => 'pemberi_izin',
        'pemberi_izin_jabatan'      => 'pemberi_izin',
        'penerima_izin_nama_lengkap' => 'penerima_izin',
        'penerima_izin_nip'          => 'penerima_izin',
        'hari'                => null,
        'tanggal_izin_keluar' => null,
        'jam_mulai_izin'      => null,
        'jam_selesai_izin'    => null,
        'keperluan_izin'      => null,
        'tanggal_surat'       => null,
    ));
}

echo "Selesai.\n";
