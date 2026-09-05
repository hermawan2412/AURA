<?php
// Ledger surat yang sudah diterbitkan (surat_diterbitkan) - 1 baris per
// generate sukses lewat surat/index.php (lihat SuratDiterbitkanRepository::
// catat(), dipanggil dari sana). Halaman ini CUMA baca + edit 2 field
// manual (berlaku_sampai, link_dokumentasi) - gak generate ulang dokumen.

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Database;
use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\SuratDiterbitkanRepository;

Auth::requirePengelolaAtauAdmin();

$pesan = '';
$pesanTipe = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

    if ($aksi === 'ubah_metadata' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $berlakuSampai = isset($_POST['berlaku_sampai']) ? trim($_POST['berlaku_sampai']) : '';
        $linkDokumentasi = isset($_POST['link_dokumentasi']) ? trim($_POST['link_dokumentasi']) : '';
        if ($berlakuSampai !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $berlakuSampai)) {
            $pesan = 'Format tanggal tidak valid.';
            $pesanTipe = 'error';
        } else {
            SuratDiterbitkanRepository::ubahMetadata($id, $berlakuSampai, $linkDokumentasi);
            header('Location: surat_diterbitkan.php');
            exit;
        }
    } elseif ($aksi === 'hapus' && isset($_POST['id'])) {
        SuratDiterbitkanRepository::hapus((int) $_POST['id']);
        header('Location: surat_diterbitkan.php');
        exit;
    }
}

$jenisSuratIdFilter = isset($_GET['jenis_surat_id']) ? (int) $_GET['jenis_surat_id'] : null;
$daftar = SuratDiterbitkanRepository::semua($jenisSuratIdFilter);
$idEdit = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editDetail = $idEdit > 0 ? SuratDiterbitkanRepository::muatById($idEdit) : null;

$semuaJenisSurat = Database::pdo()->query('SELECT id, nama FROM jenis_surat ORDER BY nama')->fetchAll();

$LABEL_STATUS = array(
    'aktif' => 'Aktif', 'segera' => 'Segera Kedaluwarsa', 'kedaluwarsa' => 'Kedaluwarsa',
);

$halamanAktif = 'admin_surat_diterbitkan';
$judulHalaman = 'Riwayat Surat Diterbitkan';
$breadcrumb   = 'Kelola Jenis Surat';
$subJudul     = 'Semua dokumen yang pernah digenerate, isian form-nya kerekam - berlaku_sampai & link dokumentasi diisi manual.';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-<?php echo htmlspecialchars((string) $pesanTipe); ?>"><?php echo htmlspecialchars((string) $pesan); ?></div>
<?php endif; ?>

<?php if ($editDetail): ?>

  <div class="note">
    <?php echo htmlspecialchars((string) $editDetail['jenis_nama']); ?><?php echo $editDetail['sub_jenis_label'] ? ' &mdash; ' . htmlspecialchars((string) $editDetail['sub_jenis_label']) : ''; ?>
    <?php if ($editDetail['nomor']): ?> &mdash; Nomor <?php echo htmlspecialchars((string) $editDetail['nomor']); ?><?php endif; ?>
    <br><a href="surat_diterbitkan.php">&larr; Kembali ke daftar</a>
  </div>

  <div class="form-card" style="margin-bottom:20px; max-width:560px;">
    <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Ubah Berlaku Sampai &amp; Link Dokumentasi</h4>
    <form method="post" action="surat_diterbitkan.php">
      <?php echo Csrf::field(); ?>
      <input type="hidden" name="aksi" value="ubah_metadata">
      <input type="hidden" name="id" value="<?php echo (int) $editDetail['id']; ?>">
      <div class="field">
        <label>Berlaku Sampai</label>
        <input type="date" name="berlaku_sampai" value="<?php echo htmlspecialchars((string) $editDetail['berlaku_sampai']); ?>">
      </div>
      <div class="field">
        <label>Link Dokumentasi (path NAS / URL)</label>
        <input type="text" name="link_dokumentasi" value="<?php echo htmlspecialchars((string) $editDetail['link_dokumentasi']); ?>" placeholder="mis. \\NAS\SK\2026\...">
      </div>
      <button type="submit" class="btn btn-primary">Simpan</button>
    </form>
  </div>

  <div class="form-card" style="max-width:560px;">
    <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Isian Form (lengkap)</h4>
    <?php $nilai = json_decode((string) $editDetail['nilai_lengkap'], true); ?>
    <table>
      <?php foreach ((is_array($nilai) ? $nilai : array()) as $kode => $nilaiField): ?>
        <tr><td class="tnum" style="vertical-align:top; white-space:nowrap;"><?php echo htmlspecialchars((string) $kode); ?></td>
            <td><?php echo nl2br(htmlspecialchars(is_scalar($nilaiField) ? (string) $nilaiField : json_encode($nilaiField, JSON_UNESCAPED_UNICODE))); ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>

<?php else: ?>

  <div class="toolbar">
    <form method="get" action="surat_diterbitkan.php" style="display:flex; gap:8px; align-items:center;">
      <select name="jenis_surat_id" onchange="this.form.submit()">
        <option value="">Semua Jenis Surat</option>
        <?php foreach ($semuaJenisSurat as $js): ?>
          <option value="<?php echo (int) $js['id']; ?>"<?php echo $jenisSuratIdFilter === (int) $js['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $js['nama']); ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <span></span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Jenis Surat</th><th>Nomor</th><th>Tanggal</th><th>Ringkasan</th><th>Berlaku Sampai</th><th>Status</th><th>Dibuat</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (count($daftar) === 0): ?>
          <tr><td colspan="8" style="text-align:center; color:var(--ink-dim); font-style:italic;">Belum ada surat yang diterbitkan.</td></tr>
        <?php else: foreach ($daftar as $sd):
          $status = SuratDiterbitkanRepository::statusBerlaku($sd['berlaku_sampai']);
        ?>
          <tr>
            <td><?php echo htmlspecialchars((string) $sd['jenis_nama']); ?><?php echo $sd['sub_jenis_label'] ? '<br><span style="color:var(--ink-dim); font-size:0.78rem;">' . htmlspecialchars((string) $sd['sub_jenis_label']) . '</span>' : ''; ?></td>
            <td class="tnum"><?php echo $sd['nomor'] ? htmlspecialchars((string) $sd['nomor']) : '&mdash;'; ?></td>
            <td class="tnum"><?php echo $sd['tanggal_dokumen'] ? htmlspecialchars((string) $sd['tanggal_dokumen']) : '&mdash;'; ?></td>
            <td><?php echo $sd['ringkasan'] ? htmlspecialchars((string) $sd['ringkasan']) : '&mdash;'; ?></td>
            <td class="tnum"><?php echo $sd['berlaku_sampai'] ? htmlspecialchars((string) $sd['berlaku_sampai']) : '&mdash;'; ?></td>
            <td><?php if ($status): ?><span class="badge badge-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($LABEL_STATUS[$status]); ?></span><?php else: ?><span class="badge badge-belum-diisi">Belum diisi</span><?php endif; ?></td>
            <td style="font-size:0.78rem; color:var(--ink-dim);"><?php echo htmlspecialchars(substr((string) $sd['created_at'], 0, 16)); ?></td>
            <td>
              <a class="btn btn-secondary" href="surat_diterbitkan.php?edit=<?php echo (int) $sd['id']; ?>">Ubah</a>
              <form method="post" action="surat_diterbitkan.php" style="display:inline;" onsubmit="return confirm('Hapus baris riwayat ini? Dokumen yang sudah diunduh tidak ikut terhapus, cuma catatannya di sini.');">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?php echo (int) $sd['id']; ?>">
                <button type="submit" class="btn btn-secondary">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
