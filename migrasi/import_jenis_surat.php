<?php
// CLI: php migrasi/import_jenis_surat.php {kode}
// Membaca migrasi/definisi/{kode}.php (ditulis manual per jenis surat, lihat §5
// rencana migrasi) lalu memasukkan datanya ke skema mesin generic (db/002_...sql).
// Tidak menyentuh/menghapus surat/{kode}.php maupun config/jenis_surat/{kode}.json
// yang lama — keduanya tetap ada untuk masa transisi.

require __DIR__ . '/../vendor/autoload.php';

use Aurat\Database;
use Aurat\Surat\TemplateUpload;
use Aurat\Surat\VariabelRepository;
use Aurat\Surat\TemplateSuratRepository;

function gagal($pesan)
{
    fwrite(STDERR, "GAGAL: $pesan\n");
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    gagal('Skrip ini hanya untuk dijalankan lewat CLI.');
}

if (!isset($argv[1]) || trim($argv[1]) === '') {
    gagal('Pemakaian: php migrasi/import_jenis_surat.php {kode}');
}

$kode = trim($argv[1]);
$pathDefinisi = __DIR__ . '/definisi/' . basename($kode) . '.php';

if (!is_file($pathDefinisi)) {
    gagal("Berkas definisi tidak ditemukan: $pathDefinisi");
}

$def = require $pathDefinisi;

if (!isset($def['jenis_surat']['kode']) || $def['jenis_surat']['kode'] !== $kode) {
    gagal('jenis_surat.kode di berkas definisi tidak cocok dengan argumen CLI.');
}

$pdo = Database::pdo();

// --- Idempotensi: tolak jika kode sudah ada, supaya skrip aman dijalankan ulang tanpa duplikasi ---
$cek = $pdo->prepare('SELECT id FROM jenis_surat WHERE kode = ?');
$cek->execute(array($kode));
if ($cek->fetch()) {
    gagal("jenis_surat dengan kode \"$kode\" sudah ada di database. Hapus dulu manual kalau memang mau migrasi ulang.");
}

// --- Normalisasi: dukung 'template' (satu berkas) ATAU 'template_list' (banyak berkas,
// mis. satu per sub_jenis pada kategori dua_dokumen) — semua variabel di $def['variabel']
// dianggap berlaku dan dipasang ke SETIAP template dalam daftar (skema kolom tabel yang
// beda per sub_jenis ditangani terpisah lewat $def['blok_tabel'], bukan di sini). ---
$templateList = isset($def['template_list']) ? $def['template_list'] : array($def['template']);

// --- Verifikasi kompatibilitas placeholder SEBELUM insert apa pun (per template) ---
foreach ($templateList as $tpl) {
    if (!is_file($tpl['sumber_path'])) {
        gagal("Berkas template sumber tidak ditemukan: {$tpl['sumber_path']}");
    }
}

// Variabel manual/pegawai/sistem yg HANYA dipakai sbg parameter_variabel suatu variabel 'turunan'
// (mis. field mentah yg diramu jadi satu kalimat narasi) tidak wajib jadi placeholder sendiri di docx
// — yang tampil di docx cuma hasil 'turunan'-nya. Kumpulkan kode yg dipakai sbg parameter dulu.
$kodeDipakaiSbgParameter = array();
foreach ($def['variabel'] as $v) {
    if ($v['sumber'] === 'turunan' && !empty($v['parameter_variabel'])) {
        foreach ($v['parameter_variabel'] as $p) {
            $kodeDipakaiSbgParameter[] = $p;
        }
    }
}

$kodeVariabelUtama = array(); // hanya variabel yg HARUS ada sbg placeholder literal
foreach ($def['variabel'] as $v) {
    $hanyaHelperTurunan = $v['sumber'] !== 'turunan' && in_array($v['kode'], $kodeDipakaiSbgParameter, true);
    if ($v['sumber'] !== 'turunan' && !$hanyaHelperTurunan) {
        $kodeVariabelUtama[] = $v['kode'];
    }
}

foreach ($templateList as $tpl) {
    $placeholderTerdeteksi = TemplateUpload::deteksiPlaceholder($tpl['sumber_path']);
    $hilang = array_diff($kodeVariabelUtama, $placeholderTerdeteksi);
    if (!empty($hilang)) {
        gagal(
            "Variabel berikut didefinisikan di berkas definisi tapi TIDAK ditemukan sbg placeholder \${...} di {$tpl['sumber_path']}: "
            . implode(', ', $hilang) . "\nPlaceholder yang benar-benar ada di berkas: " . implode(', ', $placeholderTerdeteksi)
        );
    }
}

echo "Verifikasi placeholder OK (" . count($kodeVariabelUtama) . " variabel cocok dgn isi " . count($templateList) . " berkas template).\n";

// --- Insert, dalam satu transaksi ---
$pdo->beginTransaction();

try {
    $js = $def['jenis_surat'];
    $stmt = $pdo->prepare(
        'INSERT INTO jenis_surat (kode, nama, deskripsi, kategori, kop_surat, pola_nama_unduhan, status_aktif, urutan_tampil)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
    );
    $stmt->execute(array(
        $js['kode'], $js['nama'], isset($js['deskripsi']) ? $js['deskripsi'] : null,
        $js['kategori'], $js['kop_surat'], isset($js['pola_nama_unduhan']) ? $js['pola_nama_unduhan'] : null,
        isset($js['urutan_tampil']) ? $js['urutan_tampil'] : 0,
    ));
    $jenisSuratId = (int) $pdo->lastInsertId();
    echo "jenis_surat #$jenisSuratId ($kode) dibuat.\n";

    // sub_jenis_surat
    $subJenisIdByKode = array();
    foreach ($def['sub_jenis'] as $sj) {
        $stmt = $pdo->prepare('INSERT INTO sub_jenis_surat (jenis_surat_id, kode, label, urutan_tampil) VALUES (?, ?, ?, ?)');
        $stmt->execute(array($jenisSuratId, $sj['kode'], $sj['label'], isset($sj['urutan_tampil']) ? $sj['urutan_tampil'] : 0));
        $subJenisIdByKode[$sj['kode']] = (int) $pdo->lastInsertId();
        echo "  sub_jenis_surat '{$sj['kode']}' dibuat.\n";
    }

    // peran_pegawai_surat
    foreach ($def['peran_pegawai'] as $pp) {
        $stmt = $pdo->prepare(
            'INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $jenisSuratId, $pp['kode'], $pp['label'], !empty($pp['wajib']) ? 1 : 0,
            isset($pp['urutan_tampil']) ? $pp['urutan_tampil'] : 0,
        ));
    }
    echo '  ' . count($def['peran_pegawai']) . " peran_pegawai_surat dibuat.\n";

    $peranIdByKode = array();
    $ambilPeran = $pdo->prepare('SELECT id, kode FROM peran_pegawai_surat WHERE jenis_surat_id = ?');
    $ambilPeran->execute(array($jenisSuratId));
    foreach ($ambilPeran->fetchAll() as $baris) {
        $peranIdByKode[$baris['kode']] = (int) $baris['id'];
    }

    // template_surat — salin tiap berkas ke templates/uploaded/ (berkas asli tidak disentuh),
    // lalu pasang SEMUA variabel di $def['variabel'] ke SETIAP template (lihat catatan normalisasi
    // template_list di atas — skema kolom tabel yang beda per template ditangani via blok_tabel).
    $templateSuratIds = array();

    foreach ($templateList as $tpl) {
        $subJenisSuratId = isset($tpl['sub_jenis_kode']) && $tpl['sub_jenis_kode'] !== null
            ? $subJenisIdByKode[$tpl['sub_jenis_kode']]
            : null;

        $disimpan = TemplateUpload::simpanDariPath($tpl['sumber_path'], $tpl['nama_asli']);
        $templateSuratId = TemplateSuratRepository::simpanVersiBaru(
            $jenisSuratId, $subJenisSuratId, $disimpan['nama_berkas'], $disimpan['nama_asli'], null
        );
        $templateSuratIds[] = $templateSuratId;
        echo "  template_surat #$templateSuratId dibuat (salinan {$tpl['sumber_path']} -> templates/uploaded/{$disimpan['nama_berkas']}).\n";

        $urutanTampilTsv = 0;
        foreach ($def['variabel'] as $v) {
            $existing = VariabelRepository::cariByKode($v['kode']);

            if ($existing) {
                $variabelSuratId = (int) $existing['id'];
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO variabel_surat
                        (kode, label, tipe_input, opsi_pilihan, sumber, field_pegawai, fungsi_pasca,
                         parameter_variabel, fungsi_parameter_1, fungsi_parameter_2, sistem_kode,
                         wajib_default, placeholder_default, urutan_tampil)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute(array(
                    $v['kode'],
                    $v['label'],
                    isset($v['tipe_input']) ? $v['tipe_input'] : 'text',
                    isset($v['opsi_pilihan']) ? json_encode(array_values($v['opsi_pilihan']), JSON_UNESCAPED_UNICODE) : null,
                    $v['sumber'],
                    isset($v['field_pegawai']) ? $v['field_pegawai'] : null,
                    isset($v['fungsi_pasca']) ? $v['fungsi_pasca'] : null,
                    isset($v['parameter_variabel']) ? json_encode(array_values($v['parameter_variabel']), JSON_UNESCAPED_UNICODE) : null,
                    isset($v['fungsi_parameter_1']) ? $v['fungsi_parameter_1'] : null,
                    isset($v['fungsi_parameter_2']) ? $v['fungsi_parameter_2'] : null,
                    isset($v['sistem_kode']) ? $v['sistem_kode'] : null,
                    !empty($v['wajib_default']) ? 1 : 0,
                    isset($v['placeholder_default']) ? $v['placeholder_default'] : null,
                    isset($v['urutan_tampil']) ? $v['urutan_tampil'] : 0,
                ));
                $variabelSuratId = (int) $pdo->lastInsertId();
            }

            $peranPegawaiSuratId = null;
            if ($v['sumber'] === 'pegawai') {
                if (empty($v['peran_kode']) || !isset($peranIdByKode[$v['peran_kode']])) {
                    throw new RuntimeException("Variabel '{$v['kode']}' sumber=pegawai tapi peran_kode tidak valid.");
                }
                $peranPegawaiSuratId = $peranIdByKode[$v['peran_kode']];
            }

            VariabelRepository::pasangKeTemplate(
                $templateSuratId, $variabelSuratId, $peranPegawaiSuratId,
                null, // wajib_override: pakai wajib_default variabel_surat
                $urutanTampilTsv, false
            );
            $urutanTampilTsv += 10;
        }
        echo '    ' . count($def['variabel']) . " variabel dipasang ke template #$templateSuratId.\n";
    }

    // blok_tabel_surat + kolom
    foreach ($def['blok_tabel'] as $bt) {
        $btSubJenisId = isset($bt['sub_jenis_kode']) && $bt['sub_jenis_kode'] !== null
            ? $subJenisIdByKode[$bt['sub_jenis_kode']]
            : null;

        $stmt = $pdo->prepare(
            'INSERT INTO blok_tabel_surat (jenis_surat_id, sub_jenis_surat_id, kode, nama_anchor_kolom, label, minimal_baris, urutan_tampil)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $jenisSuratId, $btSubJenisId, $bt['kode'], $bt['nama_anchor_kolom'],
            isset($bt['label']) ? $bt['label'] : null,
            isset($bt['minimal_baris']) ? $bt['minimal_baris'] : 1,
            isset($bt['urutan_tampil']) ? $bt['urutan_tampil'] : 0,
        ));
        $blokId = (int) $pdo->lastInsertId();

        foreach ($bt['kolom'] as $i => $kol) {
            $stmt = $pdo->prepare(
                'INSERT INTO blok_tabel_surat_kolom (blok_tabel_surat_id, kode, label, sumber, field_pegawai, fungsi_pasca, urutan_kolom)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute(array(
                $blokId, $kol['kode'], $kol['label'], $kol['sumber'],
                isset($kol['field_pegawai']) ? $kol['field_pegawai'] : null,
                isset($kol['fungsi_pasca']) ? $kol['fungsi_pasca'] : null,
                isset($kol['urutan_kolom']) ? $kol['urutan_kolom'] : $i * 10,
            ));
        }
        echo "  blok_tabel_surat '{$bt['kode']}' + " . count($bt['kolom']) . " kolom dibuat.\n";
    }

    $pdo->commit();
    echo "\nMigrasi '$kode' SELESAI (jenis_surat_id=$jenisSuratId, template_surat_id=" . implode(',', $templateSuratIds) . ").\n";
} catch (Exception $e) {
    $pdo->rollBack();
    gagal('Transaksi dibatalkan: ' . $e->getMessage());
}
