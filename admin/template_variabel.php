<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\VariabelRepository;
use Aurat\Surat\TemplateUpload;
use Aurat\Surat\NilaiResolver;

Auth::requireLogin();

$templateSuratId = isset($_GET['template_surat_id']) ? (int) $_GET['template_surat_id']
    : (isset($_POST['template_surat_id']) ? (int) $_POST['template_surat_id'] : 0);

$template = $templateSuratId > 0 ? TemplateSuratRepository::muatById($templateSuratId) : null;
if (!$template) {
    http_response_code(404);
    exit('Template tidak ditemukan.');
}

$jenisSurat = JenisSuratRepository::muatById($template['jenis_surat_id']);
if (!$jenisSurat) {
    http_response_code(404);
    exit('Jenis surat untuk template ini tidak ditemukan.');
}

$pesan = '';
$pesanTipe = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

    if ($aksi === 'pasang_existing' && isset($_POST['variabel_surat_id'])) {
        $peranId = isset($_POST['peran_pegawai_surat_id']) && $_POST['peran_pegawai_surat_id'] !== ''
            ? (int) $_POST['peran_pegawai_surat_id'] : null;
        VariabelRepository::pasangKeTemplate($templateSuratId, (int) $_POST['variabel_surat_id'], $peranId, null, 0, false);
        $pesan = 'Variabel dipasang ke template.';
    } elseif ($aksi === 'buat_dan_pasang') {
        $kode  = isset($_POST['kode']) ? trim($_POST['kode']) : '';
        $label = isset($_POST['label']) ? trim($_POST['label']) : '';
        $sumber = isset($_POST['sumber']) ? $_POST['sumber'] : 'manual';
        $tipeInput = isset($_POST['tipe_input']) ? $_POST['tipe_input'] : 'text';
        $opsiRaw = isset($_POST['opsi_pilihan']) ? trim($_POST['opsi_pilihan']) : '';
        $peranId = isset($_POST['peran_pegawai_surat_id']) && $_POST['peran_pegawai_surat_id'] !== '' ? (int) $_POST['peran_pegawai_surat_id'] : null;
        $fieldPegawai = isset($_POST['field_pegawai']) && $_POST['field_pegawai'] !== '' ? $_POST['field_pegawai'] : null;
        $fungsiPasca = isset($_POST['fungsi_pasca']) && $_POST['fungsi_pasca'] !== '' ? $_POST['fungsi_pasca'] : null;
        $parameterVariabel = isset($_POST['parameter_variabel']) && is_array($_POST['parameter_variabel']) ? $_POST['parameter_variabel'] : array();
        $fungsiParam1 = isset($_POST['fungsi_parameter_1']) ? trim($_POST['fungsi_parameter_1']) : '';
        $fungsiParam2 = isset($_POST['fungsi_parameter_2']) ? trim($_POST['fungsi_parameter_2']) : '';
        $sistemKode = isset($_POST['sistem_kode']) ? trim($_POST['sistem_kode']) : '';
        $wajibDefault = !empty($_POST['wajib_default']) ? 1 : 0;
        $placeholderDefault = isset($_POST['placeholder_default']) ? trim($_POST['placeholder_default']) : '';

        $kolomDiizinkan = NilaiResolver::kolomPegawaiDiizinkan();
        $fungsiDiizinkan = NilaiResolver::daftarFungsiPasca();

        $error = null;
        if ($kode === '' || $label === '') {
            $error = 'Kode dan Label wajib diisi.';
        } elseif (!in_array($sumber, array('manual', 'pegawai', 'turunan', 'sistem'), true)) {
            $error = 'Sumber tidak valid.';
        } elseif ($sumber === 'pegawai' && $peranId === null) {
            $error = 'Variabel bersumber pegawai wajib memilih Peran.';
        } elseif ($sumber === 'pegawai' && $fieldPegawai !== null && !in_array($fieldPegawai, $kolomDiizinkan, true)) {
            $error = 'Kolom pegawai tidak diizinkan.';
        } elseif ($fungsiPasca !== null && !in_array($fungsiPasca, $fungsiDiizinkan, true)) {
            $error = 'Fungsi pasca tidak dikenal.';
        } elseif ($sumber === 'turunan' && empty($parameterVariabel)) {
            $error = 'Variabel turunan wajib memilih minimal satu parameter.';
        }

        if ($error !== null) {
            $pesan = $error;
            $pesanTipe = 'error';
        } else {
            $opsiJson = null;
            if (($tipeInput === 'select' || $tipeInput === 'textarea_datalist') && $opsiRaw !== '') {
                $opsiArr = array_values(array_filter(array_map('trim', explode("\n", $opsiRaw)), function ($s) { return $s !== ''; }));
                $opsiJson = json_encode($opsiArr, JSON_UNESCAPED_UNICODE);
            }
            $paramJson = $sumber === 'turunan' ? json_encode(array_values($parameterVariabel), JSON_UNESCAPED_UNICODE) : null;

            $pdo = \Aurat\Database::pdo();
            try {
                $existing = VariabelRepository::cariByKode($kode);
                if ($existing) {
                    $variabelSuratId = (int) $existing['id'];
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO variabel_surat
                            (kode, label, tipe_input, opsi_pilihan, sumber, field_pegawai, fungsi_pasca,
                             parameter_variabel, fungsi_parameter_1, fungsi_parameter_2, sistem_kode,
                             wajib_default, placeholder_default, dibuat_oleh)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute(array(
                        $kode, $label, $tipeInput, $opsiJson, $sumber, $sumber === 'pegawai' ? $fieldPegawai : null,
                        $fungsiPasca, $paramJson,
                        $fungsiParam1 !== '' ? $fungsiParam1 : null, $fungsiParam2 !== '' ? $fungsiParam2 : null,
                        $sumber === 'sistem' && $sistemKode !== '' ? $sistemKode : null,
                        $wajibDefault, $placeholderDefault !== '' ? $placeholderDefault : null,
                        isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
                    ));
                    $variabelSuratId = (int) $pdo->lastInsertId();
                }
                VariabelRepository::pasangKeTemplate($templateSuratId, $variabelSuratId, $sumber === 'pegawai' ? $peranId : null, null, 0, false);
                $pesan = 'Variabel "' . $kode . '" dibuat dan dipasang ke template.';
            } catch (\PDOException $e) {
                $pesan = 'Gagal menyimpan variabel (kode mungkin sudah dipakai dengan definisi berbeda).';
                $pesanTipe = 'error';
            }
        }
    } elseif ($aksi === 'lepas' && isset($_POST['template_surat_variabel_id'])) {
        VariabelRepository::lepasDariTemplate((int) $_POST['template_surat_variabel_id']);
        $pesan = 'Variabel dilepas dari template.';
    }
}

$pathAbsolut = __DIR__ . '/../' . TemplateSuratRepository::path($template);
$placeholderTerdeteksi = is_file($pathAbsolut) ? TemplateUpload::deteksiPlaceholder($pathAbsolut) : array();
$variabelTerpasang = VariabelRepository::variabelUntukTemplate($templateSuratId);

$kodeTerpasang = array();
foreach ($variabelTerpasang as $v) {
    $kodeTerpasang[] = $v['kode'];
}

$placeholderBaru = array_values(array_diff($placeholderTerdeteksi, $kodeTerpasang));
$placeholderHilang = array_values(array_diff($kodeTerpasang, $placeholderTerdeteksi));

$semuaVariabel = VariabelRepository::semua();
$semuaVariabelByKode = array();
foreach ($semuaVariabel as $v) {
    $semuaVariabelByKode[$v['kode']] = $v;
}

$halamanAktif = 'admin_jenis_surat';
$judulHalaman = 'Variabel — ' . htmlspecialchars((string) $jenisSurat['nama']);
$breadcrumb   = 'Kelola Jenis Surat';
$subJudul     = 'Petakan tiap placeholder ${...} di berkas ke satu variabel. Placeholder kolom tabel dikelola di halaman Blok Tabel.';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<div class="note">
  <a href="template_surat.php?jenis_surat_id=<?php echo (int) $jenisSurat['id']; ?><?php echo $template['sub_jenis_surat_id'] ? '&amp;sub_jenis_surat_id=' . (int) $template['sub_jenis_surat_id'] : ''; ?>">&larr; Kembali ke Template</a>
  — Berkas: <b><?php echo htmlspecialchars((string) $template['nama_asli']); ?></b> (versi <?php echo (int) $template['versi']; ?>)
</div>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-<?php echo htmlspecialchars((string) $pesanTipe); ?>"><?php echo htmlspecialchars((string) $pesan); ?></div>
<?php endif; ?>

<?php if (!empty($placeholderBaru)): ?>
<div class="form-card" style="margin-bottom:20px;">
  <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Placeholder Belum Dipetakan (<?php echo count($placeholderBaru); ?>)</h4>
  <?php foreach ($placeholderBaru as $ph): $sudahAda = isset($semuaVariabelByKode[$ph]); ?>
    <div class="form-section">
      <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
        <code style="background:var(--surface-2); padding:3px 8px; border-radius:4px;">$<?php echo htmlspecialchars('{' . $ph . '}'); ?></code>
        <span style="font-size:0.78rem; color:var(--ink-dim);">Kalau ini kolom tabel (bukan variabel tunggal), kelola lewat <a href="blok_tabel.php?jenis_surat_id=<?php echo (int) $jenisSurat['id']; ?><?php echo $template['sub_jenis_surat_id'] ? '&sub_jenis_surat_id=' . (int) $template['sub_jenis_surat_id'] : ''; ?>">Blok Tabel</a>, bukan di sini.</span>
      </div>

      <?php if ($sudahAda): $vs = $semuaVariabelByKode[$ph]; ?>
        <form method="post" action="template_variabel.php">
            <?php echo Csrf::field(); ?>
          <input type="hidden" name="aksi" value="pasang_existing">
          <input type="hidden" name="template_surat_id" value="<?php echo $templateSuratId; ?>">
          <input type="hidden" name="variabel_surat_id" value="<?php echo (int) $vs['id']; ?>">
          <p style="font-size:0.85rem; margin-bottom:10px;">Variabel dengan kode ini sudah ada (dipakai template lain) — sumber: <b><?php echo htmlspecialchars((string) $vs['sumber']); ?></b>, label: <b><?php echo htmlspecialchars((string) $vs['label']); ?></b>.</p>
          <?php if ($vs['sumber'] === 'pegawai'): ?>
            <div class="field" style="max-width:320px;">
              <label>Peran Pegawai (utk template ini)</label>
              <select name="peran_pegawai_surat_id" required>
                <option value="">— pilih peran —</option>
                <?php foreach ($jenisSurat['peran_pegawai'] as $pp): ?>
                  <option value="<?php echo (int) $pp['id']; ?>"><?php echo htmlspecialchars((string) $pp['label']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-secondary">Pasang ke Template</button>
        </form>
      <?php else: ?>
        <form method="post" action="template_variabel.php">
            <?php echo Csrf::field(); ?>
          <input type="hidden" name="aksi" value="buat_dan_pasang">
          <input type="hidden" name="template_surat_id" value="<?php echo $templateSuratId; ?>">
          <input type="hidden" name="kode" value="<?php echo htmlspecialchars((string) $ph); ?>">
          <div class="grid-2">
            <div class="field"><label>Label</label><input type="text" name="label" placeholder="mis. Nomor Surat" required></div>
            <div class="field">
              <label>Sumber</label>
              <select name="sumber" class="aurat-sumber" data-ph="<?php echo htmlspecialchars((string) $ph); ?>" required>
                <option value="manual">Input manual (diisi pengguna)</option>
                <option value="pegawai">Dari data pegawai (peran)</option>
                <option value="turunan">Turunan dari variabel lain</option>
                <option value="sistem">Otomatis dari sistem (mis. tanggal hari ini)</option>
              </select>
            </div>
          </div>

          <div class="grid-2 aurat-blok-manual" id="blokManual_<?php echo htmlspecialchars((string) $ph); ?>">
            <div class="field">
              <label>Tipe Input</label>
              <select name="tipe_input">
                <option value="text">Teks singkat</option>
                <option value="textarea">Teks panjang</option>
                <option value="date">Tanggal</option>
                <option value="select">Pilihan (dropdown)</option>
                <option value="textarea_datalist">Teks panjang + saran pilihan</option>
              </select>
            </div>
            <div class="field">
              <label>Opsi Pilihan (satu per baris, utk tipe Pilihan/saran)</label>
              <textarea name="opsi_pilihan" placeholder="Tahunan&#10;Sakit&#10;Melahirkan"></textarea>
            </div>
          </div>

          <div class="grid-2 aurat-blok-pegawai" id="blokPegawai_<?php echo htmlspecialchars((string) $ph); ?>" style="display:none;">
            <div class="field">
              <label>Peran Pegawai</label>
              <select name="peran_pegawai_surat_id">
                <option value="">— pilih peran —</option>
                <?php foreach ($jenisSurat['peran_pegawai'] as $pp): ?>
                  <option value="<?php echo (int) $pp['id']; ?>"><?php echo htmlspecialchars((string) $pp['label']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Kolom Pegawai (kosongkan utk pakai baris penuh, mis. Nama Bergelar)</label>
              <select name="field_pegawai">
                <option value="">— baris penuh (perlu Fungsi Pasca) —</option>
                <?php foreach (\Aurat\Surat\NilaiResolver::kolomPegawaiDiizinkan() as $kp): ?>
                  <option value="<?php echo htmlspecialchars((string) $kp); ?>"><?php echo htmlspecialchars((string) $kp); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="aurat-blok-turunan" id="blokTurunan_<?php echo htmlspecialchars((string) $ph); ?>" style="display:none;">
            <div class="field">
              <label>Parameter (variabel lain yang sudah terpasang di template ini)</label>
              <select name="parameter_variabel[]" multiple size="4">
                <?php foreach ($variabelTerpasang as $vt): ?>
                  <option value="<?php echo htmlspecialchars((string) $vt['kode']); ?>"><?php echo htmlspecialchars($vt['kode'] . ' — ' . $vt['label']); ?></option>
                <?php endforeach; ?>
              </select>
              <span class="form-hint">Ctrl/Cmd+klik utk pilih lebih dari satu. Urutan pemilihan = urutan argumen ke fungsi.</span>
            </div>
          </div>

          <div class="grid-2 aurat-blok-sistem" id="blokSistem_<?php echo htmlspecialchars((string) $ph); ?>" style="display:none;">
            <div class="field">
              <label>Kode Sistem</label>
              <input type="text" name="sistem_kode" value="tanggal_sekarang" placeholder="tanggal_sekarang">
            </div>
          </div>

          <div class="grid-2">
            <div class="field">
              <label>Fungsi Pasca (opsional — format nilai sebelum dicetak)</label>
              <select name="fungsi_pasca">
                <option value="">— tidak ada —</option>
                <?php foreach (\Aurat\Surat\NilaiResolver::daftarFungsiPasca() as $f): ?>
                  <option value="<?php echo htmlspecialchars((string) $f); ?>"><?php echo htmlspecialchars((string) $f); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Argumen Tambahan Fungsi (opsional)</label>
              <input type="text" name="fungsi_parameter_1" placeholder="mis. awalan teks utk klausa_jika_ada">
            </div>
          </div>

          <div class="field" style="flex-direction:row; align-items:center; gap:8px;">
            <input type="checkbox" name="wajib_default" value="1" id="wajib_<?php echo htmlspecialchars((string) $ph); ?>" style="width:auto;">
            <label for="wajib_<?php echo htmlspecialchars((string) $ph); ?>" style="margin:0;">Wajib diisi (khusus input manual)</label>
          </div>

          <button type="submit" class="btn btn-secondary">Buat &amp; Pasang Variabel</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="form-card" style="margin-bottom:20px;">
  <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Variabel Terpasang (<?php echo count($variabelTerpasang); ?>)</h4>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Kode</th><th>Label</th><th>Sumber</th><th>Peran</th><th>Fungsi Pasca</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($variabelTerpasang)): ?>
          <tr><td colspan="6" style="text-align:center; color:var(--ink-dim); font-style:italic;">Belum ada variabel terpasang.</td></tr>
        <?php else: foreach ($variabelTerpasang as $v): ?>
          <tr>
            <td class="tnum">$<?php echo htmlspecialchars('{' . $v['kode'] . '}'); ?></td>
            <td><?php echo htmlspecialchars((string) $v['label']); ?></td>
            <td><?php echo htmlspecialchars((string) $v['sumber']); ?></td>
            <td><?php echo htmlspecialchars((string) $v['peran_kode']); ?></td>
            <td><?php echo htmlspecialchars((string) $v['fungsi_pasca']); ?></td>
            <td>
              <form method="post" action="template_variabel.php" style="display:inline;" onsubmit="return confirm('Lepas variabel ini dari template? Definisi variabelnya tetap ada, hanya pemasangannya yang dihapus.');">
                  <?php echo Csrf::field(); ?>
                <input type="hidden" name="aksi" value="lepas">
                <input type="hidden" name="template_surat_id" value="<?php echo $templateSuratId; ?>">
                <input type="hidden" name="template_surat_variabel_id" value="<?php echo (int) $v['template_surat_variabel_id']; ?>">
                <button type="submit" class="btn btn-secondary">Lepas</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!empty($placeholderHilang)): ?>
<div class="form-card">
  <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px; color:var(--accent);">Sudah Dipetakan, Tapi Tak Ada Lagi di Berkas (<?php echo count($placeholderHilang); ?>)</h4>
  <p style="font-size:0.85rem; color:var(--ink-dim); margin-bottom:12px;">Kemungkinan berkas diganti dan placeholder ini dihapus/diganti nama. Tinjau dan lepas manual dari daftar "Variabel Terpasang" di atas kalau memang sudah tidak relevan.</p>
  <ul>
    <?php foreach ($placeholderHilang as $ph): ?>
      <li><code>$<?php echo htmlspecialchars('{' . $ph . '}'); ?></code></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<script>
(function(){
  document.querySelectorAll('.aurat-sumber').forEach(function(sel){
    var ph = sel.dataset.ph;
    function terapkan(){
      var blokManual = document.getElementById('blokManual_' + ph);
      var blokPegawai = document.getElementById('blokPegawai_' + ph);
      var blokTurunan = document.getElementById('blokTurunan_' + ph);
      var blokSistem = document.getElementById('blokSistem_' + ph);
      blokManual.style.display = (sel.value === 'manual') ? '' : 'none';
      blokPegawai.style.display = (sel.value === 'pegawai') ? '' : 'none';
      blokTurunan.style.display = (sel.value === 'turunan') ? '' : 'none';
      blokSistem.style.display = (sel.value === 'sistem') ? '' : 'none';
    }
    sel.addEventListener('change', terapkan);
    terapkan();
  });
})();
</script>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
