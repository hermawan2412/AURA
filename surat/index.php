<?php
// Entry point generic untuk SEMUA jenis surat — menggantikan surat/{kode}.php satu-satu.
// Dipakai lewat surat/index.php?kode={kode}[&sub_jenis={kode}]. Menambah jenis surat
// baru tidak perlu berkas PHP baru: cukup data di tabel jenis_surat/variabel_surat/dst
// (lihat admin/*.php) + berkas .docx template.

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Database;
use Aurat\DocxGenerator;
use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\VariabelRepository;
use Aurat\Surat\BlokTabelRepository;
use Aurat\Surat\NilaiResolver;
use Aurat\Surat\SuratDiterbitkanRepository;
use Aurat\Surat\FotoUpload;

Auth::requireLogin();

/**
 * Substitusi {kode_variabel} dari jenis_surat.pola_nama_unduhan; fallback kode+tanggal,
 * lalu disanitasi jadi nama file aman. Dokumen lampiran (tipe_dokumen != 'utama') dapat
 * suffix dari nama asli berkas template-nya, biar tiap dokumen dalam 1 paket .zip beda
 * nama - generik, gak hardcode "daftar hadir" di sini (jenis lampiran apa pun otomatis
 * kebedain lewat nama_asli yang admin kasih pas upload).
 */
function auratNamaUnduhan(array $jenisSurat, $subJenisKode, array $nilai, ?array $template = null)
{
    $pola = isset($jenisSurat['pola_nama_unduhan']) ? $jenisSurat['pola_nama_unduhan'] : '';

    if ($pola === null || trim((string) $pola) === '') {
        $basis = $jenisSurat['kode'] . ($subJenisKode !== '' ? '_' . $subJenisKode : '') . '_' . date('Y-m-d');
    } else {
        $basis = preg_replace_callback('/\{([A-Za-z0-9_]+)\}/', function ($m) use ($nilai) {
            return isset($nilai[$m[1]]) ? $nilai[$m[1]] : '';
        }, $pola);
    }

    $basis = preg_replace('/[^A-Za-z0-9_-]/', '_', $basis);
    if ($basis === '' || $basis === null) {
        $basis = $jenisSurat['kode'] . '_' . date('Y-m-d');
    }

    if ($template !== null && $template['tipe_dokumen'] !== 'utama') {
        $suffix = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo((string) $template['nama_asli'], PATHINFO_FILENAME));
        $basis .= '_' . $suffix;
    }

    return $basis . '.docx';
}

/**
 * Ringkasan teks peserta rapat dari tabel_lengkap['peserta'] surat_diterbitkan
 * sumber (mis. Daftar Hadir Undangan) - dipakai buat prefill field manual
 * "peserta_rapat" Notula. Nomor urut ikut baris asli (sudah sesuai urutan
 * drag-reorder pas Undangan dibuat), bukan dihitung ulang.
 */
function auratRingkasPeserta(array $baris)
{
    $lines = array();
    foreach ($baris as $b) {
        $nama = isset($b['nama']) ? trim((string) $b['nama']) : '';
        if ($nama === '') {
            continue;
        }
        $bagian = isset($b['bagian']) && trim((string) $b['bagian']) !== '' ? ' (' . trim((string) $b['bagian']) . ')' : '';
        $no = isset($b['no']) ? $b['no'] : (count($lines) + 1);
        $lines[] = $no . '. ' . $nama . $bagian;
    }
    return implode("\n", $lines);
}

/**
 * Validasi + resolusi nilai + generate dokumen dari data POST. Return string pesan
 * error kalau gagal; kalau berhasil, stream dokumen langsung lalu exit (tidak return).
 */
function auratProsesGenerate(array $jenisSurat, $subJenisSuratId, $subJenisKode, array $templateSemua, array $variabelList, array $variabelManual, array $variabelFile, array $blokList, array $peranDipakai)
{
    $pdo = Database::pdo();

    // --- 1. Kumpulkan id pegawai dari picker peran tunggal ---
    $idPeran = array(); // peran_kode => id pegawai
    foreach ($peranDipakai as $peran) {
        $nilaiPost = isset($_POST['pegawai_id'][$peran['kode']]) ? (int) $_POST['pegawai_id'][$peran['kode']] : 0;
        if ($nilaiPost > 0) {
            $idPeran[$peran['kode']] = $nilaiPost;
        } elseif (!empty($peran['wajib'])) {
            return 'Pegawai untuk "' . $peran['label'] . '" wajib dipilih.';
        }
    }

    // --- 2. Kumpulkan id pegawai + nilai manual per baris dari tiap blok tabel ---
    $idBlokPerBlok = array();     // blok_kode => array id pegawai per baris (urut)
    $manualBlokPerBlok = array(); // blok_kode => [kolom_kode => array nilai per baris urut]
    foreach ($blokList as $blok) {
        $blokKode = $blok['kode'];
        $idBaris = (isset($_POST['blok'][$blokKode]['pegawai_id']) && is_array($_POST['blok'][$blokKode]['pegawai_id']))
            ? array_map('intval', $_POST['blok'][$blokKode]['pegawai_id'])
            : array();
        $manual = (isset($_POST['blok'][$blokKode]['manual']) && is_array($_POST['blok'][$blokKode]['manual']))
            ? $_POST['blok'][$blokKode]['manual']
            : array();

        if (count($idBaris) < (int) $blok['minimal_baris']) {
            $label = isset($blok['label']) && $blok['label'] !== null ? $blok['label'] : $blokKode;
            return 'Tabel "' . $label . '" minimal harus berisi ' . $blok['minimal_baris'] . ' baris.';
        }

        $idBlokPerBlok[$blokKode] = $idBaris;
        $manualBlokPerBlok[$blokKode] = $manual;
    }

    // --- 3. SATU query batch untuk semua pegawai yang terlibat (peran + semua baris tabel) ---
    $semuaId = array_values($idPeran);
    foreach ($idBlokPerBlok as $idBaris) {
        $semuaId = array_merge($semuaId, $idBaris);
    }
    $semuaId = array_values(array_unique(array_filter($semuaId, function ($v) { return $v > 0; })));

    $pegawaiById = array();
    if (!empty($semuaId)) {
        $placeholder = implode(',', array_fill(0, count($semuaId), '?'));
        $stmt = $pdo->prepare('SELECT * FROM pegawai WHERE id IN (' . $placeholder . ') AND status_aktif = 1');
        $stmt->execute($semuaId);
        foreach ($stmt->fetchAll() as $baris) {
            $pegawaiById[(int) $baris['id']] = $baris;
        }
    }

    $pegawaiTerpilih = array(); // peran_kode => baris pegawai (utk NilaiResolver)
    foreach ($idPeran as $peranKode => $id) {
        if (!isset($pegawaiById[$id])) {
            return 'Pegawai yang dipilih tidak ditemukan/nonaktif. Silakan cari ulang.';
        }
        $pegawaiTerpilih[$peranKode] = $pegawaiById[$id];
    }

    // --- 4. Validasi + kumpulkan variabel manual ---
    $inputManual = array();
    foreach ($variabelManual as $v) {
        if ($v['tipe_input'] === 'daftar_teks') {
            $itemsPost = isset($_POST['var'][$v['kode']]) && is_array($_POST['var'][$v['kode']])
                ? $_POST['var'][$v['kode']] : array();
            $items = array();
            foreach ($itemsPost as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $items[] = $item;
                }
            }
            if (empty($items) && !empty($v['wajib'])) {
                return 'Isian "' . $v['label'] . '" wajib diisi minimal satu item.';
            }
            $baris = array();
            foreach ($items as $i => $item) {
                $baris[] = ($i + 1) . '. ' . $item;
            }
            $inputManual[$v['kode']] = implode("\n", $baris);
            continue;
        }

        $nilaiPost = isset($_POST['var'][$v['kode']]) ? trim((string) $_POST['var'][$v['kode']]) : '';
        if ($nilaiPost === '' && !empty($v['wajib'])) {
            return 'Isian "' . $v['label'] . '" wajib diisi.';
        }
        $inputManual[$v['kode']] = $nilaiPost;
    }

    // --- 4b. Validasi + simpan berkas gambar (variabel tipe_input='file') ---
    // kode => path absolut file tersimpan, ATAU tidak ada key sama sekali kalau
    // field opsional dikosongkan (DocxGenerator akan setValue('') placeholder-nya).
    $gambar = array();
    foreach ($variabelFile as $v) {
        $berkas = isset($_FILES['var_file']['error'][$v['kode']]) ? array(
            'name' => $_FILES['var_file']['name'][$v['kode']],
            'type' => $_FILES['var_file']['type'][$v['kode']],
            'tmp_name' => $_FILES['var_file']['tmp_name'][$v['kode']],
            'error' => $_FILES['var_file']['error'][$v['kode']],
            'size' => $_FILES['var_file']['size'][$v['kode']],
        ) : array('error' => UPLOAD_ERR_NO_FILE);

        try {
            $path = FotoUpload::simpan($berkas);
        } catch (RuntimeException $e) {
            return 'Berkas "' . $v['label'] . '": ' . $e->getMessage();
        }

        if ($path === null) {
            if (!empty($v['wajib'])) {
                return 'Berkas "' . $v['label'] . '" wajib diunggah.';
            }
            continue;
        }
        $gambar[$v['kode']] = $path;
    }

    // --- 5. Bangun $tabel utk DocxGenerator dari tiap blok ---
    $tabel = array();
    $kolomPegawaiDiizinkan = NilaiResolver::kolomPegawaiDiizinkan();
    foreach ($blokList as $blok) {
        $blokKode = $blok['kode'];
        $barisTabel = array();

        foreach ($idBlokPerBlok[$blokKode] as $idx => $id) {
            if (!isset($pegawaiById[$id])) {
                continue; // pegawai tidak valid/nonaktif -> baris dilewati, bukan gagal total
            }
            $p = $pegawaiById[$id];
            $baris = array();
            foreach ($blok['kolom'] as $kolom) {
                switch ($kolom['sumber']) {
                    case 'auto_nomor':
                        $baris[$kolom['kode']] = (string) (count($barisTabel) + 1);
                        break;
                    case 'pegawai_field':
                        if (!in_array($kolom['field_pegawai'], $kolomPegawaiDiizinkan, true)) {
                            return 'Kolom pegawai tidak diizinkan: ' . $kolom['field_pegawai'];
                        }
                        $baris[$kolom['kode']] = isset($p[$kolom['field_pegawai']]) ? $p[$kolom['field_pegawai']] : '';
                        break;
                    case 'pegawai_fungsi':
                        if (empty($kolom['fungsi_pasca'])) {
                            return 'Kolom "' . $kolom['label'] . '" bersumber pegawai_fungsi tapi fungsi_pasca belum diisi.';
                        }
                        try {
                            $baris[$kolom['kode']] = NilaiResolver::panggilFungsiPasca($kolom['fungsi_pasca'], array($p));
                        } catch (RuntimeException $e) {
                            return $e->getMessage();
                        }
                        break;
                    case 'manual_per_baris':
                        $nilaiManual = isset($manualBlokPerBlok[$blokKode][$kolom['kode']][$idx])
                            ? trim((string) $manualBlokPerBlok[$blokKode][$kolom['kode']][$idx])
                            : '';
                        if ($kolom['tipe'] === 'date' && $nilaiManual !== '') {
                            $nilaiManual = NilaiResolver::panggilFungsiPasca('tanggal_indonesia', array($nilaiManual));
                        }
                        $baris[$kolom['kode']] = $nilaiManual;
                        break;
                    default:
                        return 'Sumber kolom tidak dikenal: ' . $kolom['sumber'];
                }
            }
            $barisTabel[] = $baris;
        }

        if (count($barisTabel) < (int) $blok['minimal_baris']) {
            $label = isset($blok['label']) && $blok['label'] !== null ? $blok['label'] : $blokKode;
            return 'Tabel "' . $label . '" minimal harus berisi ' . $blok['minimal_baris'] . ' baris (setelah validasi pegawai).';
        }

        $tabel[$blok['nama_anchor_kolom']] = $barisTabel;
    }

    // --- 6. Resolusi nilai variabel ---
    $konteksSistem = array(
        'tanggal_sekarang' => date('Y-m-d'),
        // Dipakai variabel 'kode_klasifikasi_surat' (sumber=sistem) - basis
        // nomor_surat_otomatis(). Resolve ke kode_klasifikasi JENIS SURAT
        // yang lagi diproses, bukan hardcode - satu definisi variabel
        // dipakai bareng semua jenis surat.
        'kode_klasifikasi' => isset($jenisSurat['kode_klasifikasi']) ? (string) $jenisSurat['kode_klasifikasi'] : '',
        // Dipakai variabel 'kode_satker_surat' - SATU nilai tetap semua
        // jenis surat (beda dari kode_klasifikasi di atas yang per jenis
        // surat), diatur admin lewat admin/pengaturan.php.
        'kode_satker' => (function () {
            $baris = Database::pdo()->query('SELECT kode_satker FROM pengaturan_aplikasi WHERE id = 1')->fetch();
            return $baris && $baris['kode_satker'] !== null ? (string) $baris['kode_satker'] : '';
        })(),
    );
    try {
        $resolver = new NilaiResolver($variabelList, $inputManual, $pegawaiTerpilih, $konteksSistem);
        $nilai = $resolver->resolveSemua();
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    // --- 7. Rekam ke ledger (surat_diterbitkan), 1 baris per template aktif ---
    // dibungkus try/catch sendiri, gagal nyimpen histori TIDAK BOLEH menghalangi
    // dokumen tetap terbit (sama prinsip kayak notifikasi WA di RESTU: fitur
    // sekunder gak pernah memblokir alur utama). Lampiran (Daftar Hadir dkk)
    // di-induk_id-kan ke baris utama biar keliatan sebagai 1 paket di histori.
    $indukId = null;
    foreach ($templateSemua as $t) {
        try {
            $id = SuratDiterbitkanRepository::catat(
                $jenisSurat, $subJenisSuratId, (int) $t['id'], $nilai,
                isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
                $t['tipe_dokumen'] === 'utama' ? null : $indukId,
                $tabel
            );
            if ($t['tipe_dokumen'] === 'utama') {
                $indukId = $id;
            }
        } catch (\Throwable $e) {
            error_log('[AURA] Gagal mencatat surat_diterbitkan: ' . $e->getMessage());
        }
    }

    // --- 8. Generate dokumen. 1 template aktif = unduhan .docx langsung
    // (perilaku lama, gak berubah). >1 template aktif (ada lampiran) = semua
    // digenerate lalu dibungkus 1 berkas .zip, biar user gak diminta unduh
    // berkali-kali.
    try {
        if (count($templateSemua) === 1) {
            $namaUnduhan = auratNamaUnduhan($jenisSurat, $subJenisKode, $nilai);
            DocxGenerator::generateDanUnduh(TemplateSuratRepository::path($templateSemua[0]), $nilai, $tabel, $namaUnduhan, $gambar);
        } else {
            $dokumen = array();
            foreach ($templateSemua as $t) {
                $dokumen[] = array(
                    'templateRelPath' => TemplateSuratRepository::path($t),
                    'nilai' => $nilai,
                    'tabel' => $tabel,
                    'gambar' => $gambar,
                    'namaUnduhan' => auratNamaUnduhan($jenisSurat, $subJenisKode, $nilai, $t),
                );
            }
            $namaZip = preg_replace('/\.docx$/', '.zip', auratNamaUnduhan($jenisSurat, $subJenisKode, $nilai));
            DocxGenerator::generateZipDanUnduh($dokumen, $namaZip);
        }
        exit; // generateDanUnduh/generateZipDanUnduh sudah exit setelah stream; baris ini jaga-jaga.
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
}

$kode = isset($_GET['kode']) ? trim($_GET['kode']) : '';
if ($kode === '') {
    http_response_code(404);
    exit('Jenis surat tidak ditentukan.');
}

$jenisSurat = JenisSuratRepository::muat($kode);
if (!$jenisSurat || (int) $jenisSurat['status_aktif'] !== 1) {
    http_response_code(404);
    exit('Jenis surat "' . htmlspecialchars((string) $kode) . '" tidak ditemukan atau tidak aktif.');
}
if (!Auth::bolehAksesJenisSurat($kode)) {
    http_response_code(403);
    exit('Akses ditolak — peran Anda tidak diizinkan membuat jenis surat ini.');
}

$metode = $_SERVER['REQUEST_METHOD'];
if ($metode === 'POST') {
    Csrf::verify();
}
$subJenisKode = $metode === 'POST'
    ? (isset($_POST['sub_jenis']) ? trim($_POST['sub_jenis']) : '')
    : (isset($_GET['sub_jenis']) ? trim($_GET['sub_jenis']) : '');

$subJenis = null;
$tampilkanPemilihSubJenis = false;

if ($jenisSurat['kategori'] === 'dua_dokumen') {
    if ($subJenisKode !== '') {
        $subJenis = JenisSuratRepository::subJenisByKode($jenisSurat['id'], $subJenisKode);
        if (!$subJenis) {
            http_response_code(404);
            exit('Sub-jenis surat tidak ditemukan.');
        }
    } elseif ($metode !== 'POST') {
        $tampilkanPemilihSubJenis = true;
    } else {
        http_response_code(400);
        exit('Sub-jenis surat wajib dipilih.');
    }
}

if ($tampilkanPemilihSubJenis) {
    $halamanAktif = $kode;
    $judulHalaman = $jenisSurat['nama'];
    $breadcrumb   = 'Buat Surat';
    $subJudul     = 'Pilih jenis dokumen yang akan dibuat.';
    $rootAsset    = '../';

    require __DIR__ . '/../views/layout_atas.php';
    ?>
    <div class="form-card">
      <?php foreach ($jenisSurat['sub_jenis'] as $sj): ?>
        <a class="btn btn-secondary" style="margin:0 10px 10px 0;"
           href="index.php?kode=<?php echo urlencode($kode); ?>&amp;sub_jenis=<?php echo urlencode($sj['kode']); ?>">
          <?php echo htmlspecialchars((string) $sj['label']); ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
    require __DIR__ . '/../views/layout_bawah.php';
    exit;
}

$subJenisSuratId = $subJenis ? (int) $subJenis['id'] : null;

$templateSemua = TemplateSuratRepository::templateAktifSemua($jenisSurat['id'], $subJenisSuratId);
if (empty($templateSemua)) {
    http_response_code(500);
    exit('Template belum tersedia untuk jenis surat ini. Hubungi administrator untuk mengunggah template.');
}

// Form + variabel yang ditampilkan/divalidasi = GABUNGAN semua template aktif
// (utama + lampiran-lampirannya, mis. Undangan + Daftar Hadir) - 1 submit form
// mengisi kebutuhan semua dokumen sekaligus. Digabung by-kode (variabel_surat.kode
// unik, dipakai ulang lintas template lewat template_surat_variabel).
$variabelList = array();
$kodeTerpakai = array();
foreach ($templateSemua as $t) {
    foreach (VariabelRepository::variabelUntukTemplate($t['id']) as $v) {
        if (!isset($kodeTerpakai[$v['kode']])) {
            $variabelList[] = $v;
            $kodeTerpakai[$v['kode']] = true;
        }
    }
}
// Variabel bertipe 'file' (mis. foto_notula) ditangani terpisah dari input
// teks biasa - nilainya bukan string yang di-setValue(), tapi path gambar
// yang di-setImageValue() (lihat DocxGenerator + FotoUpload).
$variabelManual = array();
$variabelFile = array();
foreach ($variabelList as $v) {
    if ($v['sumber'] === 'manual' && $v['tipe_input'] === 'file') {
        $variabelFile[] = $v;
    } elseif ($v['sumber'] === 'manual') {
        $variabelManual[] = $v;
    }
}
$blokList = BlokTabelRepository::blokUntuk($jenisSurat['id'], $subJenisSuratId);

// peran_pegawai_surat itu SLOT jenis_surat-wide, bukan per sub_jenis - jenis surat
// dengan 2+ sub_jenis yang butuh peran BEDA (mis. sub_jenis A pakai peran X, sub_jenis
// B pakai peran Y) bakal salah kalau render loop pakai $jenisSurat['peran_pegawai']
// mentah-mentah (nampilin/wajibin picker peran yang gak dipakai template sub_jenis
// yang lagi aktif sama sekali). Persempit ke peran yang BENERAN dipasang ke template
// aktif ini (peran_kode dari VariabelRepository::variabelUntukTemplate(), sudah
// scoped per template_surat_id) sebelum dipakai buat render/validasi/JS di bawah.
$peranKodeDipakai = array_unique(array_filter(array_column($variabelList, 'peran_kode')));
$peranDipakai = array_values(array_filter($jenisSurat['peran_pegawai'], function ($p) use ($peranKodeDipakai) {
    return in_array($p['kode'], $peranKodeDipakai, true);
}));

// Prefill dari surat_diterbitkan lain (mis. Notula dibuat dari Undangan yang
// sudah diterbitkan) - ?dari={surat_diterbitkan.id}, GET doang (bukan
// mekanisme yang berlaku pas POST/validasi ulang - $_POST yang lebih
// diutamakan kalau ada, lihat pemakaian $prefillNilai di form di bawah).
// Kode variabel discocokin LANGSUNG antara sumber & tujuan (mis. hari,
// tanggal_acara, waktu, tempat, nama_acara dipakai bareng Undangan+Notula) -
// makanya variabel²  itu SENGAJA reuse kode yang sama, bukan bikin kode baru
// per jenis_surat (lihat db/034_notula.sql).
$prefillNilai = array();
$dariId = $metode !== 'POST' && isset($_GET['dari']) ? (int) $_GET['dari'] : 0;
if ($dariId > 0) {
    $sumber = SuratDiterbitkanRepository::muatById($dariId);
    if ($sumber) {
        $nilaiSumber = json_decode((string) $sumber['nilai_lengkap'], true);
        if (is_array($nilaiSumber)) {
            $prefillNilai = $nilaiSumber;
        }
        // $tabel disimpan dg key = blok_tabel_surat.nama_anchor_kolom (bukan .kode) -
        // lihat gimana $tabel dibangun di auratProsesGenerate() step 5. Blok peserta
        // Undangan (db/033) nama_anchor_kolom='no', jadi 'no' di sini, bukan 'peserta'.
        $tabelSumber = json_decode((string) $sumber['tabel_lengkap'], true);
        if (is_array($tabelSumber) && isset($tabelSumber['no'])) {
            $prefillNilai['peserta_rapat'] = auratRingkasPeserta($tabelSumber['no']);
        }
        if (!empty($sumber['nomor']) || !empty($sumber['tanggal_dokumen'])) {
            $prefillNilai['dasar'] = 'Surat Undangan'
                . (!empty($sumber['nomor']) ? ' Nomor ' . $sumber['nomor'] : '')
                . (!empty($sumber['tanggal_dokumen']) ? ' tanggal ' . NilaiResolver::panggilFungsiPasca('tanggal_indonesia', array($sumber['tanggal_dokumen'])) : '');
        }
    }
}

$pesanError = '';

if ($metode === 'POST') {
    $pesanError = auratProsesGenerate($jenisSurat, $subJenisSuratId, $subJenisKode, $templateSemua, $variabelList, $variabelManual, $variabelFile, $blokList, $peranDipakai);
    // Kalau sukses, auratProsesGenerate() sudah exit() setelah stream dokumen — baris di bawah ini hanya jalan kalau gagal.
}

$halamanAktif = $kode;
$judulHalaman = $jenisSurat['nama'];
$breadcrumb   = 'Buat Surat';
$subJudul     = isset($subJenis['label']) ? $subJenis['label'] : '';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<?php if ($pesanError !== ''): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars((string) $pesanError); ?></div>
<?php endif; ?>

<div class="form-card">
  <form method="post" action="index.php?kode=<?php echo urlencode($kode); ?><?php echo $subJenisKode !== '' ? '&amp;sub_jenis=' . urlencode($subJenisKode) : ''; ?>" id="formSurat" enctype="multipart/form-data">
    <?php echo Csrf::field(); ?>
    <?php if ($subJenisKode !== ''): ?>
      <input type="hidden" name="sub_jenis" value="<?php echo htmlspecialchars((string) $subJenisKode); ?>">
    <?php endif; ?>

    <?php if (!empty($peranDipakai)): ?>
    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Pegawai</h4>
      <?php foreach ($peranDipakai as $peran): $pk = $peran['kode']; ?>
        <div class="field">
          <label><?php echo htmlspecialchars((string) $peran['label']); ?> <?php if (!empty($peran['wajib'])): ?><span class="req">*</span><?php endif; ?></label>
          <input type="text" id="pegawaiCari_<?php echo htmlspecialchars((string) $pk); ?>" placeholder="Ketik nama pegawai&hellip;" autocomplete="off">
          <div class="picker-results" id="pegawaiHasil_<?php echo htmlspecialchars((string) $pk); ?>"></div>
        </div>
        <input type="hidden" name="pegawai_id[<?php echo htmlspecialchars((string) $pk); ?>]" id="pegawaiId_<?php echo htmlspecialchars((string) $pk); ?>">
        <div id="pegawaiTerpilih_<?php echo htmlspecialchars((string) $pk); ?>"></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($variabelManual)): ?>
    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Rincian</h4>
      <div class="grid-2">
        <?php foreach ($variabelManual as $v): $vk = $v['kode']; $tipe = $v['tipe_input'];
              $lebarPenuh = ($tipe === 'textarea' || $tipe === 'textarea_datalist' || $tipe === 'daftar_teks');
              $nilaiIsi = ($tipe === 'daftar_teks') ? '' : (isset($_POST['var'][$vk]) ? (string) $_POST['var'][$vk] : (isset($prefillNilai[$vk]) ? (string) $prefillNilai[$vk] : '')); ?>
          <div class="field"<?php echo $lebarPenuh ? ' style="grid-column:1 / -1;"' : ''; ?>>
            <label><?php echo htmlspecialchars((string) $v['label']); ?> <?php if (!empty($v['wajib'])): ?><span class="req">*</span><?php endif; ?></label>
            <?php if ($tipe === 'daftar_teks'): ?>
              <ul id="daftarList_<?php echo htmlspecialchars((string) $vk); ?>" style="list-style:none; padding:0; margin:0 0 8px;"></ul>
              <div style="display:flex; gap:8px;">
                <input type="text" id="daftarInput_<?php echo htmlspecialchars((string) $vk); ?>" placeholder="Tambah item&hellip;" style="flex:1;">
                <button type="button" id="daftarTambah_<?php echo htmlspecialchars((string) $vk); ?>" class="btn">Tambah</button>
              </div>
              <span class="form-hint">Seret item untuk mengubah urutan. Nomor urut otomatis.</span>
            <?php elseif ($tipe === 'select'): $opsi = json_decode((string) $v['opsi_pilihan'], true); if (!is_array($opsi)) { $opsi = array(); } ?>
              <select name="var[<?php echo htmlspecialchars((string) $vk); ?>]" <?php echo !empty($v['wajib']) ? 'required' : ''; ?>>
                <?php foreach ($opsi as $o): ?>
                  <option value="<?php echo htmlspecialchars((string) $o); ?>" <?php echo $o === $nilaiIsi ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $o); ?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($tipe === 'textarea'): ?>
              <textarea name="var[<?php echo htmlspecialchars((string) $vk); ?>]" <?php echo !empty($v['wajib']) ? 'required' : ''; ?>><?php echo htmlspecialchars($nilaiIsi); ?></textarea>
            <?php elseif ($tipe === 'textarea_datalist'): $opsi = json_decode((string) $v['opsi_pilihan'], true); if (!is_array($opsi)) { $opsi = array(); } ?>
              <textarea name="var[<?php echo htmlspecialchars((string) $vk); ?>]" list="dl_<?php echo htmlspecialchars((string) $vk); ?>" <?php echo !empty($v['wajib']) ? 'required' : ''; ?>><?php echo htmlspecialchars($nilaiIsi); ?></textarea>
              <datalist id="dl_<?php echo htmlspecialchars((string) $vk); ?>">
                <?php foreach ($opsi as $o): ?><option value="<?php echo htmlspecialchars((string) $o); ?>"><?php endforeach; ?>
              </datalist>
            <?php elseif ($tipe === 'date'): ?>
              <input type="date" name="var[<?php echo htmlspecialchars((string) $vk); ?>]" value="<?php echo htmlspecialchars(preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilaiIsi) ? $nilaiIsi : ''); ?>" <?php echo !empty($v['wajib']) ? 'required' : ''; ?>>
            <?php else: ?>
              <input type="text" name="var[<?php echo htmlspecialchars((string) $vk); ?>]" value="<?php echo htmlspecialchars($nilaiIsi); ?>" <?php echo !empty($v['wajib']) ? 'required' : ''; ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($variabelFile)): ?>
    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Berkas</h4>
      <?php foreach ($variabelFile as $v): $vk = $v['kode']; ?>
        <div class="field">
          <label><?php echo htmlspecialchars((string) $v['label']); ?> <?php if (!empty($v['wajib'])): ?><span class="req">*</span><?php endif; ?></label>
          <input type="file" name="var_file[<?php echo htmlspecialchars((string) $vk); ?>]" accept="image/jpeg,image/png" <?php echo !empty($v['wajib']) ? 'required' : ''; ?>>
          <p class="form-hint">JPG/PNG, maksimal 5MB.</p>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($blokList)): ?>
    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Tabel</h4>
      <?php foreach ($blokList as $blok): $bk = $blok['kode']; ?>
        <div class="field">
          <label><?php echo htmlspecialchars(isset($blok['label']) && $blok['label'] !== null ? $blok['label'] : $bk); ?></label>
          <input type="text" id="blokCari_<?php echo htmlspecialchars((string) $bk); ?>" placeholder="Ketik nama pegawai&hellip;" autocomplete="off">
          <div class="picker-results" id="blokHasil_<?php echo htmlspecialchars((string) $bk); ?>"></div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th></th>
                <?php foreach ($blok['kolom'] as $kolom): ?><th><?php echo htmlspecialchars((string) $kolom['label']); ?></th><?php endforeach; ?>
                <th></th>
              </tr>
            </thead>
            <tbody id="blokBody_<?php echo htmlspecialchars((string) $bk); ?>">
              <tr id="blokKosong_<?php echo htmlspecialchars((string) $bk); ?>">
                <td colspan="<?php echo count($blok['kolom']) + 2; ?>" style="text-align:center; color:var(--ink-dim); font-style:italic;">Belum ada baris ditambahkan.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <span class="form-hint">Seret baris untuk mengubah urutan.</span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary">Unduh Dokumen (.docx)</button>
  </form>
</div>

<script src="<?php echo $rootAsset; ?>assets/js/pegawai-picker.js"></script>
<script>
(function(){
  <?php foreach ($peranDipakai as $peran): $pk = $peran['kode']; ?>
  AuratPicker.initTunggal(<?php echo json_encode(array(
      'inputId'          => 'pegawaiCari_' . $pk,
      'hasilId'          => 'pegawaiHasil_' . $pk,
      'hiddenIdField'    => 'pegawaiId_' . $pk,
      'targetTerpilihId' => 'pegawaiTerpilih_' . $pk,
      'apiUrl'           => '../api/pegawai_cari.php',
  ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
  <?php endforeach; ?>

  <?php foreach ($blokList as $blok): $bk = $blok['kode']; ?>
  AuratPicker.initTabel(<?php echo json_encode(array(
      'blokKode' => $bk,
      'inputId'  => 'blokCari_' . $bk,
      'hasilId'  => 'blokHasil_' . $bk,
      'tbodyId'  => 'blokBody_' . $bk,
      'kosongId' => 'blokKosong_' . $bk,
      'kolom'    => $blok['kolom'],
      'apiUrl'   => '../api/pegawai_cari.php',
  ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
  <?php endforeach; ?>

  <?php foreach ($variabelManual as $v): if ($v['tipe_input'] !== 'daftar_teks') { continue; } $vk = $v['kode'];
        $itemsAwal = (isset($_POST['var'][$vk]) && is_array($_POST['var'][$vk]))
            ? array_values(array_filter(array_map('trim', $_POST['var'][$vk]), function ($s) { return $s !== ''; }))
            : array(); ?>
  AuratPicker.initDaftarTeks(<?php echo json_encode(array(
      'kode'     => $vk,
      'listId'   => 'daftarList_' . $vk,
      'inputId'  => 'daftarInput_' . $vk,
      'tambahId' => 'daftarTambah_' . $vk,
      'items'    => $itemsAwal,
  ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
  <?php endforeach; ?>

  document.getElementById('formSurat').addEventListener('submit', function(e){
    <?php foreach ($peranDipakai as $peran): if (empty($peran['wajib'])) { continue; } $pk = $peran['kode']; ?>
    if (!document.getElementById('pegawaiId_<?php echo $pk; ?>').value) {
      e.preventDefault();
      alert('Pilih <?php echo htmlspecialchars(addslashes($peran['label'])); ?> terlebih dahulu.');
      return;
    }
    <?php endforeach; ?>
  });
})();
</script>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
