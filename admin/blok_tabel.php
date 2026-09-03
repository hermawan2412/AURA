<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Database;
use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\BlokTabelRepository;
use Aurat\Surat\NilaiResolver;

Auth::requireLogin();

$jenisSuratId = isset($_GET['jenis_surat_id']) ? (int) $_GET['jenis_surat_id'] : (isset($_POST['jenis_surat_id']) ? (int) $_POST['jenis_surat_id'] : 0);
$jenisSurat = $jenisSuratId > 0 ? JenisSuratRepository::muatById($jenisSuratId) : null;
if (!$jenisSurat) {
    http_response_code(404);
    exit('Jenis surat tidak ditemukan.');
}

$subJenisSuratId = isset($_GET['sub_jenis_surat_id']) ? (int) $_GET['sub_jenis_surat_id'] : (isset($_POST['sub_jenis_surat_id']) ? (int) $_POST['sub_jenis_surat_id'] : 0);
$subJenis = null;
if ($subJenisSuratId > 0) {
    foreach ($jenisSurat['sub_jenis'] as $sj) {
        if ((int) $sj['id'] === $subJenisSuratId) {
            $subJenis = $sj;
            break;
        }
    }
}
$subJenisSuratIdParam = $subJenis ? (int) $subJenis['id'] : null;

$pdo = Database::pdo();
$pesan = '';
$pesanTipe = 'info';

function auratKodeValidBlok($kode)
{
    return (bool) preg_match('/^[a-z][a-z0-9_]{1,49}$/', $kode);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

    if ($aksi === 'tambah_blok') {
        $kode = isset($_POST['kode']) ? trim($_POST['kode']) : '';
        $anchor = isset($_POST['nama_anchor_kolom']) ? trim($_POST['nama_anchor_kolom']) : '';
        $label = isset($_POST['label']) ? trim($_POST['label']) : '';
        $minimalBaris = isset($_POST['minimal_baris']) ? max(0, (int) $_POST['minimal_baris']) : 1;

        if (!auratKodeValidBlok($kode) || !auratKodeValidBlok($anchor)) {
            $pesan = 'Kode blok dan Nama Anchor Kolom wajib diisi (huruf kecil/angka/underscore, diawali huruf).';
            $pesanTipe = 'error';
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO blok_tabel_surat (jenis_surat_id, sub_jenis_surat_id, kode, nama_anchor_kolom, label, minimal_baris)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute(array($jenisSuratId, $subJenisSuratIdParam, $kode, $anchor, $label !== '' ? $label : null, $minimalBaris));
            } catch (\PDOException $e) {
                $pesan = 'Gagal membuat blok (kode mungkin sudah dipakai utk jenis surat/sub-jenis ini).';
                $pesanTipe = 'error';
            }
        }
    } elseif ($aksi === 'hapus_blok' && isset($_POST['id'])) {
        $pdo->prepare('DELETE FROM blok_tabel_surat WHERE id = ?')->execute(array((int) $_POST['id']));
    } elseif ($aksi === 'tambah_kolom' && isset($_POST['blok_tabel_surat_id'])) {
        $blokId = (int) $_POST['blok_tabel_surat_id'];
        $kode = isset($_POST['kode']) ? trim($_POST['kode']) : '';
        $label = isset($_POST['label']) ? trim($_POST['label']) : '';
        $sumber = isset($_POST['sumber']) ? $_POST['sumber'] : '';
        $tipe = (isset($_POST['tipe']) && $_POST['tipe'] === 'date') ? 'date' : 'text';
        $fieldPegawai = isset($_POST['field_pegawai']) && $_POST['field_pegawai'] !== '' ? $_POST['field_pegawai'] : null;
        $fungsiPasca = isset($_POST['fungsi_pasca']) && $_POST['fungsi_pasca'] !== '' ? $_POST['fungsi_pasca'] : null;
        $urutan = isset($_POST['urutan_kolom']) ? (int) $_POST['urutan_kolom'] : 0;

        $sumberValid = in_array($sumber, array('auto_nomor', 'pegawai_field', 'pegawai_fungsi', 'manual_per_baris'), true);
        $fieldValid = $sumber !== 'pegawai_field' || in_array($fieldPegawai, NilaiResolver::kolomPegawaiDiizinkan(), true);
        $fungsiValid = $sumber !== 'pegawai_fungsi' || in_array($fungsiPasca, NilaiResolver::daftarFungsiPasca(), true);

        if (!auratKodeValidBlok($kode) || $label === '' || !$sumberValid || !$fieldValid || !$fungsiValid) {
            $pesan = 'Data kolom tidak valid (periksa Kode, Label, Sumber, Kolom Pegawai, dan Fungsi).';
            $pesanTipe = 'error';
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO blok_tabel_surat_kolom (blok_tabel_surat_id, kode, label, sumber, tipe, field_pegawai, fungsi_pasca, urutan_kolom)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute(array(
                    $blokId, $kode, $label, $sumber,
                    $sumber === 'manual_per_baris' ? $tipe : 'text',
                    $sumber === 'pegawai_field' ? $fieldPegawai : null,
                    $sumber === 'pegawai_fungsi' ? $fungsiPasca : null,
                    $urutan,
                ));
            } catch (\PDOException $e) {
                $pesan = 'Gagal menambah kolom (kode mungkin sudah dipakai di blok ini).';
                $pesanTipe = 'error';
            }
        }
    } elseif ($aksi === 'hapus_kolom' && isset($_POST['id'])) {
        $pdo->prepare('DELETE FROM blok_tabel_surat_kolom WHERE id = ?')->execute(array((int) $_POST['id']));
    }

    $lokasi = 'blok_tabel.php?jenis_surat_id=' . $jenisSuratId . ($subJenisSuratIdParam ? '&sub_jenis_surat_id=' . $subJenisSuratIdParam : '');
    if ($pesan === '') {
        header('Location: ' . $lokasi);
        exit;
    }
}

$blokList = BlokTabelRepository::blokUntuk($jenisSuratId, $subJenisSuratIdParam);

$halamanAktif = 'admin_jenis_surat';
$judulHalaman = 'Blok Tabel — ' . $jenisSurat['nama'] . ($subJenis ? ' (' . $subJenis['label'] . ')' : '');
$breadcrumb   = 'Kelola Jenis Surat';
$subJudul     = 'Daftar berulang di dalam dokumen (mis. lampiran pegawai). Kode kolom harus sama persis dgn nama placeholder dasar di docx (tanpa "#N").';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<div class="note">
  <a href="jenis_surat.php?id=<?php echo $jenisSuratId; ?>">&larr; Kembali ke <?php echo htmlspecialchars((string) $jenisSurat['nama']); ?></a>
</div>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-<?php echo htmlspecialchars((string) $pesanTipe); ?>"><?php echo htmlspecialchars((string) $pesan); ?></div>
<?php endif; ?>

<?php foreach ($blokList as $blok): ?>
  <div class="form-card" style="margin-bottom:20px;">
    <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:8px;">
      Blok: <?php echo htmlspecialchars((string) $blok['kode']); ?>
      <?php if (!empty($blok['label'])): ?> — <?php echo htmlspecialchars((string) $blok['label']); ?><?php endif; ?>
    </h4>
    <p style="font-size:0.8rem; color:var(--ink-dim); margin-bottom:14px;">
      Anchor kolom: <code><?php echo htmlspecialchars((string) $blok['nama_anchor_kolom']); ?></code> · Minimal baris: <?php echo (int) $blok['minimal_baris']; ?>
      <form method="post" action="blok_tabel.php" style="display:inline; margin-left:10px;" onsubmit="return confirm('Hapus blok ini beserta semua kolomnya?');">
                <?php echo Csrf::field(); ?>
        <input type="hidden" name="aksi" value="hapus_blok">
        <input type="hidden" name="id" value="<?php echo (int) $blok['id']; ?>">
        <input type="hidden" name="jenis_surat_id" value="<?php echo $jenisSuratId; ?>">
        <?php if ($subJenisSuratIdParam): ?><input type="hidden" name="sub_jenis_surat_id" value="<?php echo $subJenisSuratIdParam; ?>"><?php endif; ?>
        <button type="submit" class="btn btn-secondary" style="padding:2px 10px; font-size:0.75rem;">Hapus Blok</button>
      </form>
    </p>

    <div class="table-wrap" style="margin-bottom:16px;">
      <table>
        <thead><tr><th>Kode</th><th>Label</th><th>Sumber</th><th>Kolom Pegawai / Fungsi</th><th>Urutan</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($blok['kolom'])): ?>
            <tr><td colspan="6" style="text-align:center; color:var(--ink-dim); font-style:italic;">Belum ada kolom.</td></tr>
          <?php else: foreach ($blok['kolom'] as $kol): ?>
            <tr>
              <td class="tnum"><?php echo htmlspecialchars((string) $kol['kode']); ?></td>
              <td><?php echo htmlspecialchars((string) $kol['label']); ?></td>
              <td><?php echo htmlspecialchars((string) $kol['sumber']); ?><?php if ($kol['sumber'] === 'manual_per_baris' && $kol['tipe'] === 'date'): ?> <span class="kind">tanggal</span><?php endif; ?></td>
              <td><?php echo htmlspecialchars((string) ($kol['sumber'] === 'pegawai_fungsi' ? $kol['fungsi_pasca'] : $kol['field_pegawai'])); ?></td>
              <td class="tnum"><?php echo (int) $kol['urutan_kolom']; ?></td>
              <td>
                <form method="post" action="blok_tabel.php" style="display:inline;">
                <?php echo Csrf::field(); ?>
                  <input type="hidden" name="aksi" value="hapus_kolom">
                  <input type="hidden" name="id" value="<?php echo (int) $kol['id']; ?>">
                  <input type="hidden" name="jenis_surat_id" value="<?php echo $jenisSuratId; ?>">
                  <?php if ($subJenisSuratIdParam): ?><input type="hidden" name="sub_jenis_surat_id" value="<?php echo $subJenisSuratIdParam; ?>"><?php endif; ?>
                  <button type="submit" class="btn btn-secondary">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <form method="post" action="blok_tabel.php">
                <?php echo Csrf::field(); ?>
      <input type="hidden" name="aksi" value="tambah_kolom">
      <input type="hidden" name="blok_tabel_surat_id" value="<?php echo (int) $blok['id']; ?>">
      <input type="hidden" name="jenis_surat_id" value="<?php echo $jenisSuratId; ?>">
      <?php if ($subJenisSuratIdParam): ?><input type="hidden" name="sub_jenis_surat_id" value="<?php echo $subJenisSuratIdParam; ?>"><?php endif; ?>
      <div class="grid-3">
        <div class="field"><label>Kode Kolom</label><input type="text" name="kode" pattern="[a-z][a-z0-9_]{1,49}" required></div>
        <div class="field"><label>Label</label><input type="text" name="label" required></div>
        <div class="field">
          <label>Sumber</label>
          <select name="sumber" class="aurat-sumber-kolom" required>
            <option value="auto_nomor">Nomor urut otomatis</option>
            <option value="pegawai_field">Satu kolom mentah dari data pegawai</option>
            <option value="pegawai_fungsi">Hasil fungsi dari baris pegawai (mis. Nama Bergelar)</option>
            <option value="manual_per_baris">Isian manual per baris</option>
          </select>
        </div>
        <div class="field aurat-field-pegawai" style="display:none;">
          <label>Kolom Pegawai</label>
          <select name="field_pegawai">
            <?php foreach (NilaiResolver::kolomPegawaiDiizinkan() as $kp): ?>
              <option value="<?php echo htmlspecialchars((string) $kp); ?>"><?php echo htmlspecialchars((string) $kp); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field aurat-fungsi-pegawai" style="display:none;">
          <label>Fungsi</label>
          <select name="fungsi_pasca">
            <?php foreach (NilaiResolver::daftarFungsiPasca() as $f): ?>
              <option value="<?php echo htmlspecialchars((string) $f); ?>"><?php echo htmlspecialchars((string) $f); ?></option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint">Pilih fungsi yang menerima SATU baris pegawai penuh, mis. nama_bergelar, pangkat_golongan, jabatan_satuan_kerja.</span>
        </div>
        <div class="field aurat-tipe-manual" style="display:none;">
          <label>Tipe Isian</label>
          <select name="tipe">
            <option value="text">Teks bebas</option>
            <option value="date">Tanggal (date picker)</option>
          </select>
        </div>
        <div class="field"><label>Urutan Kolom</label><input type="number" name="urutan_kolom" value="0"></div>
      </div>
      <button type="submit" class="btn btn-secondary">+ Tambah Kolom</button>
    </form>
  </div>
<?php endforeach; ?>

<div class="form-card">
  <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">+ Blok Tabel Baru</h4>
  <form method="post" action="blok_tabel.php">
                <?php echo Csrf::field(); ?>
    <input type="hidden" name="aksi" value="tambah_blok">
    <input type="hidden" name="jenis_surat_id" value="<?php echo $jenisSuratId; ?>">
    <?php if ($subJenisSuratIdParam): ?><input type="hidden" name="sub_jenis_surat_id" value="<?php echo $subJenisSuratIdParam; ?>"><?php endif; ?>
    <div class="grid-3">
      <div class="field"><label>Kode Blok</label><input type="text" name="kode" pattern="[a-z][a-z0-9_]{1,49}" placeholder="mis. daftar_anggota" required></div>
      <div class="field"><label>Nama Anchor Kolom (di docx)</label><input type="text" name="nama_anchor_kolom" pattern="[a-z][a-z0-9_]{1,49}" placeholder="mis. no" required></div>
      <div class="field"><label>Label</label><input type="text" name="label" placeholder="mis. Lampiran Daftar Pegawai"></div>
      <div class="field"><label>Minimal Baris</label><input type="number" name="minimal_baris" value="1" min="0"></div>
    </div>
    <button type="submit" class="btn btn-primary">+ Tambah Blok</button>
  </form>
</div>

<script>
(function(){
  document.querySelectorAll('.aurat-sumber-kolom').forEach(function(sel){
    var wrapField = sel.closest('form').querySelector('.aurat-field-pegawai');
    var wrapFungsi = sel.closest('form').querySelector('.aurat-fungsi-pegawai');
    var wrapTipe = sel.closest('form').querySelector('.aurat-tipe-manual');
    function terapkan(){
      wrapField.style.display = (sel.value === 'pegawai_field') ? '' : 'none';
      wrapFungsi.style.display = (sel.value === 'pegawai_fungsi') ? '' : 'none';
      wrapTipe.style.display = (sel.value === 'manual_per_baris') ? '' : 'none';
    }
    sel.addEventListener('change', terapkan);
    terapkan();
  });
})();
</script>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
