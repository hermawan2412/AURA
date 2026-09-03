<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\TemplateUpload;

Auth::requireLogin();

$jenisSuratId = isset($_GET['jenis_surat_id']) ? (int) $_GET['jenis_surat_id'] : (isset($_POST['jenis_surat_id']) ? (int) $_POST['jenis_surat_id'] : 0);
$jenisSurat = $jenisSuratId > 0 ? JenisSuratRepository::muatById($jenisSuratId) : null;

if (!$jenisSurat) {
    http_response_code(404);
    exit('Jenis surat tidak ditemukan.');
}

$subJenisSuratId = isset($_GET['sub_jenis_surat_id']) ? (int) $_GET['sub_jenis_surat_id'] : (isset($_POST['sub_jenis_surat_id']) ? (int) $_POST['sub_jenis_surat_id'] : 0);
$subJenis = null;
if ($jenisSurat['kategori'] === 'dua_dokumen') {
    foreach ($jenisSurat['sub_jenis'] as $sj) {
        if ((int) $sj['id'] === $subJenisSuratId) {
            $subJenis = $sj;
            break;
        }
    }
    if (!$subJenis) {
        http_response_code(400);
        exit('Sub-jenis surat wajib dipilih dan valid (jenis surat ini punya beberapa sub-jenis).');
    }
}
$subJenisSuratIdParam = $subJenis ? (int) $subJenis['id'] : null;

$pesan = '';
$pesanTipe = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

    if ($aksi === 'unggah') {
        try {
            if (!isset($_FILES['template'])) {
                throw new RuntimeException('Tidak ada berkas yang dipilih.');
            }
            $disimpan = TemplateUpload::simpan($_FILES['template']);
            $templateSuratId = TemplateSuratRepository::simpanVersiBaru(
                $jenisSuratId, $subJenisSuratIdParam, $disimpan['nama_berkas'], $disimpan['nama_asli'],
                isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null
            );
            header('Location: template_variabel.php?template_surat_id=' . $templateSuratId);
            exit;
        } catch (RuntimeException $e) {
            $pesan = $e->getMessage();
            $pesanTipe = 'error';
        }
    } elseif ($aksi === 'aktifkan_versi' && isset($_POST['template_surat_id'])) {
        try {
            TemplateSuratRepository::aktifkanVersi((int) $_POST['template_surat_id']);
        } catch (Exception $e) {
            $pesan = 'Gagal mengaktifkan versi: ' . $e->getMessage();
            $pesanTipe = 'error';
        }
        $lokasi = 'template_surat.php?jenis_surat_id=' . $jenisSuratId . ($subJenisSuratIdParam ? '&sub_jenis_surat_id=' . $subJenisSuratIdParam : '');
        header('Location: ' . $lokasi);
        exit;
    } elseif ($aksi === 'hapus_versi' && isset($_POST['template_surat_id'])) {
        try {
            TemplateSuratRepository::hapus((int) $_POST['template_surat_id']);
        } catch (Exception $e) {
            $pesan = $e->getMessage();
            $pesanTipe = 'error';
        }
        $lokasi = 'template_surat.php?jenis_surat_id=' . $jenisSuratId . ($subJenisSuratIdParam ? '&sub_jenis_surat_id=' . $subJenisSuratIdParam : '');
        header('Location: ' . $lokasi);
        exit;
    }
}

$templateAktif = TemplateSuratRepository::templateUntuk($jenisSuratId, $subJenisSuratIdParam);
$riwayat = TemplateSuratRepository::riwayat($jenisSuratId, $subJenisSuratIdParam);

$halamanAktif = 'admin_jenis_surat';
$judulHalaman = 'Template — ' . $jenisSurat['nama'] . ($subJenis ? ' (' . $subJenis['label'] . ')' : '');
$breadcrumb   = 'Kelola Jenis Surat';
$subJudul     = 'Unggah berkas .docx dengan placeholder ${nama_variabel}. Versi lama tetap tersimpan dan bisa diaktifkan kembali.';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<div class="note">
  <a href="jenis_surat.php?id=<?php echo $jenisSuratId; ?>">&larr; Kembali ke <?php echo htmlspecialchars((string) $jenisSurat['nama']); ?></a>
</div>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-<?php echo htmlspecialchars((string) $pesanTipe); ?>"><?php echo htmlspecialchars((string) $pesan); ?></div>
<?php endif; ?>

<div class="form-card" style="margin-bottom:20px;">
  <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">
    <?php echo $templateAktif ? 'Ganti Template (unggah versi baru)' : 'Unggah Template Pertama'; ?>
  </h4>
  <?php if ($templateAktif): ?>
    <p style="font-size:0.85rem; color:var(--ink-dim); margin-bottom:16px;">
      Aktif saat ini: <b><?php echo htmlspecialchars((string) $templateAktif['nama_asli']); ?></b> (versi <?php echo (int) $templateAktif['versi']; ?>)
      — <a href="template_variabel.php?template_surat_id=<?php echo (int) $templateAktif['id']; ?>">Kelola variabelnya</a>
    </p>
  <?php endif; ?>
  <form method="post" action="template_surat.php" enctype="multipart/form-data">
      <?php echo Csrf::field(); ?>
    <input type="hidden" name="aksi" value="unggah">
    <input type="hidden" name="jenis_surat_id" value="<?php echo $jenisSuratId; ?>">
    <?php if ($subJenisSuratIdParam): ?><input type="hidden" name="sub_jenis_surat_id" value="<?php echo $subJenisSuratIdParam; ?>"><?php endif; ?>
    <div class="field">
      <label>Berkas .docx <span class="req">*</span></label>
      <input type="file" name="template" accept=".docx" required>
    </div>
    <button type="submit" class="btn btn-primary">Unggah &amp; Deteksi Variabel</button>
  </form>
</div>

<div class="form-card">
  <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Riwayat Versi</h4>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Versi</th><th>Nama Berkas Asli</th><th>Diunggah</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($riwayat)): ?>
          <tr><td colspan="5" style="text-align:center; color:var(--ink-dim); font-style:italic;">Belum ada template diunggah.</td></tr>
        <?php else: foreach ($riwayat as $t): ?>
          <tr>
            <td class="tnum"><?php echo (int) $t['versi']; ?></td>
            <td><?php echo htmlspecialchars((string) $t['nama_asli']); ?></td>
            <td class="tnum"><?php echo htmlspecialchars((string) $t['diunggah_at']); ?></td>
            <td><?php echo ((int) $t['status_aktif'] === 1) ? 'Aktif' : 'Nonaktif'; ?></td>
            <td>
              <a href="template_variabel.php?template_surat_id=<?php echo (int) $t['id']; ?>">Variabel</a>
              <a href="template_unduh.php?id=<?php echo (int) $t['id']; ?>" style="margin-left:8px;">Unduh</a>
              <?php if ((int) $t['status_aktif'] !== 1): ?>
                <form method="post" action="template_surat.php" style="display:inline; margin-left:8px;" onsubmit="return confirm('Aktifkan kembali versi ini? Versi yang sekarang aktif akan dinonaktifkan.');">
                    <?php echo Csrf::field(); ?>
                  <input type="hidden" name="aksi" value="aktifkan_versi">
                  <input type="hidden" name="jenis_surat_id" value="<?php echo $jenisSuratId; ?>">
                  <?php if ($subJenisSuratIdParam): ?><input type="hidden" name="sub_jenis_surat_id" value="<?php echo $subJenisSuratIdParam; ?>"><?php endif; ?>
                  <input type="hidden" name="template_surat_id" value="<?php echo (int) $t['id']; ?>">
                  <button type="submit" class="btn btn-secondary">Aktifkan</button>
                </form>
                <form method="post" action="template_surat.php" style="display:inline; margin-left:8px;" onsubmit="return confirm('Hapus versi ini secara permanen? Berkas dan pemetaan variabelnya ikut terhapus dan tidak bisa dikembalikan.');">
                    <?php echo Csrf::field(); ?>
                  <input type="hidden" name="aksi" value="hapus_versi">
                  <input type="hidden" name="jenis_surat_id" value="<?php echo $jenisSuratId; ?>">
                  <?php if ($subJenisSuratIdParam): ?><input type="hidden" name="sub_jenis_surat_id" value="<?php echo $subJenisSuratIdParam; ?>"><?php endif; ?>
                  <input type="hidden" name="template_surat_id" value="<?php echo (int) $t['id']; ?>">
                  <button type="submit" class="btn btn-secondary">Hapus</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
