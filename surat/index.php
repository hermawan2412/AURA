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

Auth::requireLogin();

/** Substitusi {kode_variabel} dari jenis_surat.pola_nama_unduhan; fallback kode+tanggal, lalu disanitasi jadi nama file aman. */
function auratNamaUnduhan(array $jenisSurat, $subJenisKode, array $nilai)
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

    return $basis . '.docx';
}

/**
 * Validasi + resolusi nilai + generate dokumen dari data POST. Return string pesan
 * error kalau gagal; kalau berhasil, stream dokumen langsung lalu exit (tidak return).
 */
function auratProsesGenerate(array $jenisSurat, $subJenisSuratId, $subJenisKode, array $template, array $variabelList, array $variabelManual, array $blokList, array $peranDipakai)
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
        $nilaiPost = isset($_POST['var'][$v['kode']]) ? trim((string) $_POST['var'][$v['kode']]) : '';
        if ($nilaiPost === '' && !empty($v['wajib'])) {
            return 'Isian "' . $v['label'] . '" wajib diisi.';
        }
        $inputManual[$v['kode']] = $nilaiPost;
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

    $namaUnduhan = auratNamaUnduhan($jenisSurat, $subJenisKode, $nilai);

    // Rekam ke ledger (surat_diterbitkan) - dibungkus try/catch sendiri,
    // gagal nyimpen histori TIDAK BOLEH menghalangi dokumen tetap terbit
    // (sama prinsip kayak notifikasi WA di RESTU: fitur sekunder gak pernah
    // memblokir alur utama).
    try {
        SuratDiterbitkanRepository::catat(
            $jenisSurat, $subJenisSuratId, (int) $template['id'], $nilai,
            isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null
        );
    } catch (\Throwable $e) {
        error_log('[AURA] Gagal mencatat surat_diterbitkan: ' . $e->getMessage());
    }

    try {
        DocxGenerator::generateDanUnduh(TemplateSuratRepository::path($template), $nilai, $tabel, $namaUnduhan);
        exit; // generateDanUnduh sudah exit setelah stream; baris ini jaga-jaga.
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

$template = TemplateSuratRepository::templateUntuk($jenisSurat['id'], $subJenisSuratId);
if (!$template) {
    http_response_code(500);
    exit('Template belum tersedia untuk jenis surat ini. Hubungi administrator untuk mengunggah template.');
}

$variabelList = VariabelRepository::variabelUntukTemplate($template['id']);
$variabelManual = array();
foreach ($variabelList as $v) {
    if ($v['sumber'] === 'manual') {
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

$pesanError = '';

if ($metode === 'POST') {
    $pesanError = auratProsesGenerate($jenisSurat, $subJenisSuratId, $subJenisKode, $template, $variabelList, $variabelManual, $blokList, $peranDipakai);
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
  <form method="post" action="index.php?kode=<?php echo urlencode($kode); ?><?php echo $subJenisKode !== '' ? '&amp;sub_jenis=' . urlencode($subJenisKode) : ''; ?>" id="formSurat">
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
        <?php foreach ($variabelManual as $v): $vk = $v['kode']; $tipe = $v['tipe_input']; $lebarPenuh = ($tipe === 'textarea' || $tipe === 'textarea_datalist'); ?>
          <div class="field"<?php echo $lebarPenuh ? ' style="grid-column:1 / -1;"' : ''; ?>>
            <label><?php echo htmlspecialchars((string) $v['label']); ?> <?php if (!empty($v['wajib'])): ?><span class="req">*</span><?php endif; ?></label>
            <?php if ($tipe === 'select'): $opsi = json_decode((string) $v['opsi_pilihan'], true); if (!is_array($opsi)) { $opsi = array(); } ?>
              <select name="var[<?php echo htmlspecialchars((string) $vk); ?>]" <?php echo !empty($v['wajib']) ? 'required' : ''; ?>>
                <?php foreach ($opsi as $o): ?>
                  <option value="<?php echo htmlspecialchars((string) $o); ?>"><?php echo htmlspecialchars((string) $o); ?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($tipe === 'textarea'): ?>
              <textarea name="var[<?php echo htmlspecialchars((string) $vk); ?>]" <?php echo !empty($v['wajib']) ? 'required' : ''; ?>></textarea>
            <?php elseif ($tipe === 'textarea_datalist'): $opsi = json_decode((string) $v['opsi_pilihan'], true); if (!is_array($opsi)) { $opsi = array(); } ?>
              <textarea name="var[<?php echo htmlspecialchars((string) $vk); ?>]" list="dl_<?php echo htmlspecialchars((string) $vk); ?>" <?php echo !empty($v['wajib']) ? 'required' : ''; ?>></textarea>
              <datalist id="dl_<?php echo htmlspecialchars((string) $vk); ?>">
                <?php foreach ($opsi as $o): ?><option value="<?php echo htmlspecialchars((string) $o); ?>"><?php endforeach; ?>
              </datalist>
            <?php elseif ($tipe === 'date'): ?>
              <input type="date" name="var[<?php echo htmlspecialchars((string) $vk); ?>]" <?php echo !empty($v['wajib']) ? 'required' : ''; ?>>
            <?php else: ?>
              <input type="text" name="var[<?php echo htmlspecialchars((string) $vk); ?>]" <?php echo !empty($v['wajib']) ? 'required' : ''; ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
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
