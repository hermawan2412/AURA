<?php
// Pengaturan aplikasi (1 baris, pola sama kayak RESTU) - kode_satker (basis
// nomor_surat_otomatis()) + identitas aplikasi (nama/subjudul login/nama
// instansi, dibaca lewat Aurat\Pengaturan di login.php & layout_atas.php).

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Database;

Auth::requireLogin();
if (!Auth::isAdmin()) {
    http_response_code(403);
    exit('Halaman ini khusus administrator.');
}

$pdo = Database::pdo();
$pesan = '';
$pesanTipe = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $kodeSatker    = isset($_POST['kode_satker']) ? trim($_POST['kode_satker']) : '';
    $namaAplikasi  = isset($_POST['nama_aplikasi']) ? trim($_POST['nama_aplikasi']) : '';
    $subjudulLogin = isset($_POST['subjudul_login']) ? trim($_POST['subjudul_login']) : '';
    $namaInstansi  = isset($_POST['nama_instansi']) ? trim($_POST['nama_instansi']) : '';
    $pdo->prepare('UPDATE pengaturan_aplikasi SET kode_satker = ?, nama_aplikasi = ?, subjudul_login = ?, nama_instansi = ?, updated_at = NOW() WHERE id = 1')
        ->execute(array(
            $kodeSatker !== '' ? $kodeSatker : null,
            $namaAplikasi !== '' ? $namaAplikasi : null,
            $subjudulLogin !== '' ? $subjudulLogin : null,
            $namaInstansi !== '' ? $namaInstansi : null,
        ));
    $pesan = 'Pengaturan disimpan.';
    $pesanTipe = 'success';
}

$pengaturan = $pdo->query('SELECT * FROM pengaturan_aplikasi WHERE id = 1')->fetch();

$halamanAktif = 'admin_pengaturan';
$judulHalaman = 'Pengaturan Aplikasi';
$breadcrumb   = 'Pengaturan';
$subJudul     = 'Setting yang berlaku untuk seluruh aplikasi, bukan per jenis surat.';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-<?php echo $pesanTipe === 'success' ? 'info' : htmlspecialchars((string) $pesanTipe); ?>"><?php echo htmlspecialchars((string) $pesan); ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:520px;">
  <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Identitas Aplikasi</h4>
  <p class="note" style="margin-bottom:16px;">
    Nama & teks yang tampil di halaman Masuk dan sidebar - kosongkan
    field mana pun buat balik ke nilai bawaan.
  </p>
  <form method="post" action="pengaturan.php">
    <?php echo Csrf::field(); ?>
    <input type="hidden" name="kode_satker" value="<?php echo htmlspecialchars((string) $pengaturan['kode_satker']); ?>">
    <div class="field">
      <label>Nama Aplikasi</label>
      <input type="text" name="nama_aplikasi" value="<?php echo htmlspecialchars((string) $pengaturan['nama_aplikasi']); ?>" placeholder="AURA">
    </div>
    <div class="field">
      <label>Subjudul Halaman Masuk</label>
      <input type="text" name="subjudul_login" value="<?php echo htmlspecialchars((string) $pengaturan['subjudul_login']); ?>" placeholder="Aplikasi Untuk suRAt">
    </div>
    <div class="field">
      <label>Nama Instansi</label>
      <input type="text" name="nama_instansi" value="<?php echo htmlspecialchars((string) $pengaturan['nama_instansi']); ?>" placeholder="Sekretariat · Bagian Kepegawaian">
    </div>
    <button type="submit" class="btn btn-primary">Simpan Identitas</button>
  </form>
</div>

<div class="form-card" style="max-width:520px; margin-top:20px;">
  <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Nomor Surat Otomatis</h4>
  <p class="note" style="margin-bottom:16px;">
    Kode satuan kerja (mis. "W15-A8") - SATU nilai, dipakai semua jenis
    surat yang nomornya disusun otomatis. Beda dari "Kode Klasifikasi"
    (per jenis surat, diatur di halaman Kelola Jenis Surat) dan kode
    penandatangan (otomatis dari jabatan yang tanda tangan).
  </p>
  <form method="post" action="pengaturan.php">
    <?php echo Csrf::field(); ?>
    <input type="hidden" name="nama_aplikasi" value="<?php echo htmlspecialchars((string) $pengaturan['nama_aplikasi']); ?>">
    <input type="hidden" name="subjudul_login" value="<?php echo htmlspecialchars((string) $pengaturan['subjudul_login']); ?>">
    <input type="hidden" name="nama_instansi" value="<?php echo htmlspecialchars((string) $pengaturan['nama_instansi']); ?>">
    <div class="field">
      <label>Kode Satuan Kerja</label>
      <input type="text" name="kode_satker" value="<?php echo htmlspecialchars((string) $pengaturan['kode_satker']); ?>" placeholder="mis. W15-A8">
    </div>
    <button type="submit" class="btn btn-primary">Simpan</button>
  </form>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
