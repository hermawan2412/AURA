<?php
// Pengaturan aplikasi (1 baris, pola sama kayak RESTU) - baru ada 1 field
// sekarang (kode_satker, basis nomor_surat_otomatis()), tempat nampung
// setting app-wide lain di masa depan kalau perlu.

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
    $kodeSatker = isset($_POST['kode_satker']) ? trim($_POST['kode_satker']) : '';
    $pdo->prepare('UPDATE pengaturan_aplikasi SET kode_satker = ?, updated_at = NOW() WHERE id = 1')
        ->execute(array($kodeSatker !== '' ? $kodeSatker : null));
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
  <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Nomor Surat Otomatis</h4>
  <p class="note" style="margin-bottom:16px;">
    Kode satuan kerja (mis. "W15-A8") - SATU nilai, dipakai semua jenis
    surat yang nomornya disusun otomatis. Beda dari "Kode Klasifikasi"
    (per jenis surat, diatur di halaman Kelola Jenis Surat) dan kode
    penandatangan (otomatis dari jabatan yang tanda tangan).
  </p>
  <form method="post" action="pengaturan.php">
    <?php echo Csrf::field(); ?>
    <div class="field">
      <label>Kode Satuan Kerja</label>
      <input type="text" name="kode_satker" value="<?php echo htmlspecialchars((string) $pengaturan['kode_satker']); ?>" placeholder="mis. W15-A8">
    </div>
    <button type="submit" class="btn btn-primary">Simpan</button>
  </form>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
