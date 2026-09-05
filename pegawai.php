<?php

require __DIR__ . '/src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Database;

Auth::requirePengelolaAtauAdmin();

$pdo = Database::pdo();
$pesan = '';
$pesanTipe = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $nip          = isset($_POST['nip']) ? trim($_POST['nip']) : '';
    $namaLengkap  = isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : '';
    $gelarDepan   = isset($_POST['gelar_depan']) ? trim($_POST['gelar_depan']) : '';
    $gelarBelakang = isset($_POST['gelar_belakang']) ? trim($_POST['gelar_belakang']) : '';
    $pangkat      = isset($_POST['pangkat']) ? trim($_POST['pangkat']) : '';
    $golongan     = isset($_POST['golongan_ruang']) ? trim($_POST['golongan_ruang']) : '';
    $jabatan      = isset($_POST['jabatan']) ? trim($_POST['jabatan']) : '';
    $unitKerja    = isset($_POST['unit_kerja']) ? trim($_POST['unit_kerja']) : '';
    $tmt          = isset($_POST['tmt']) ? trim($_POST['tmt']) : '';

    if ($nip === '' || $namaLengkap === '') {
        $pesan = 'NIP dan Nama Lengkap wajib diisi.';
        $pesanTipe = 'error';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO pegawai (nip, nama_lengkap, gelar_depan, gelar_belakang, pangkat, golongan_ruang, jabatan, unit_kerja, tmt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        try {
            $stmt->execute(array(
                $nip, $namaLengkap,
                $gelarDepan !== '' ? $gelarDepan : null,
                $gelarBelakang !== '' ? $gelarBelakang : null,
                $pangkat !== '' ? $pangkat : null,
                $golongan !== '' ? $golongan : null,
                $jabatan !== '' ? $jabatan : null,
                $unitKerja !== '' ? $unitKerja : null,
                $tmt !== '' ? $tmt : null,
            ));
            $pesan = 'Pegawai "' . $namaLengkap . '" berhasil ditambahkan.';
            $pesanTipe = 'info';
        } catch (\PDOException $e) {
            $pesan = (strpos($e->getMessage(), 'uq_pegawai_nip') !== false)
                ? 'NIP tersebut sudah terdaftar.'
                : 'Gagal menyimpan data pegawai.';
            $pesanTipe = 'error';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'ubah' && isset($_POST['id'])) {
    $id           = (int) $_POST['id'];
    $nip          = isset($_POST['nip']) ? trim($_POST['nip']) : '';
    $namaLengkap  = isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : '';
    $gelarDepan   = isset($_POST['gelar_depan']) ? trim($_POST['gelar_depan']) : '';
    $gelarBelakang = isset($_POST['gelar_belakang']) ? trim($_POST['gelar_belakang']) : '';
    $pangkat      = isset($_POST['pangkat']) ? trim($_POST['pangkat']) : '';
    $golongan     = isset($_POST['golongan_ruang']) ? trim($_POST['golongan_ruang']) : '';
    $jabatan      = isset($_POST['jabatan']) ? trim($_POST['jabatan']) : '';
    $unitKerja    = isset($_POST['unit_kerja']) ? trim($_POST['unit_kerja']) : '';
    $tmt          = isset($_POST['tmt']) ? trim($_POST['tmt']) : '';
    $statusAktif  = !empty($_POST['status_aktif']) ? 1 : 0;

    if ($nip === '' || $namaLengkap === '') {
        $pesan = 'NIP dan Nama Lengkap wajib diisi.';
        $pesanTipe = 'error';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE pegawai SET nip=?, nama_lengkap=?, gelar_depan=?, gelar_belakang=?, pangkat=?, golongan_ruang=?, jabatan=?, unit_kerja=?, tmt=?, status_aktif=?, updated_at=NOW()
             WHERE id=?'
        );
        try {
            $stmt->execute(array(
                $nip, $namaLengkap,
                $gelarDepan !== '' ? $gelarDepan : null,
                $gelarBelakang !== '' ? $gelarBelakang : null,
                $pangkat !== '' ? $pangkat : null,
                $golongan !== '' ? $golongan : null,
                $jabatan !== '' ? $jabatan : null,
                $unitKerja !== '' ? $unitKerja : null,
                $tmt !== '' ? $tmt : null,
                $statusAktif,
                $id,
            ));
            $pesan = 'Pegawai "' . $namaLengkap . '" berhasil diperbarui.';
            $pesanTipe = 'info';
        } catch (\PDOException $e) {
            $pesan = (strpos($e->getMessage(), 'uq_pegawai_nip') !== false)
                ? 'NIP tersebut sudah dipakai pegawai lain.'
                : 'Gagal menyimpan perubahan.';
            $pesanTipe = 'error';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'hapus' && isset($_POST['id'])) {
    $stmt = $pdo->prepare('DELETE FROM pegawai WHERE id = ?');
    $stmt->execute(array((int) $_POST['id']));
    $pesan = 'Pegawai dihapus.';
    $pesanTipe = 'info';
}

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : '';

$idEdit = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editPegawai = null;
if ($idEdit > 0) {
    $stmt = $pdo->prepare('SELECT * FROM pegawai WHERE id = ?');
    $stmt->execute(array($idEdit));
    $editPegawai = $stmt->fetch();
    if (!$editPegawai) {
        $pesan = 'Pegawai yang mau diubah tidak ditemukan (mungkin sudah dihapus).';
        $pesanTipe = 'error';
    }
}

if ($cari !== '') {
    $stmt = $pdo->prepare(
        'SELECT * FROM pegawai
         WHERE nama_lengkap LIKE ? OR nip LIKE ? OR unit_kerja LIKE ?
         ORDER BY nama_lengkap ASC'
    );
    $like = '%' . $cari . '%';
    $stmt->execute(array($like, $like, $like));
} else {
    $stmt = $pdo->query('SELECT * FROM pegawai ORDER BY nama_lengkap ASC');
}
$daftarPegawai = $stmt->fetchAll();

$halamanAktif = 'pegawai';
$judulHalaman = 'Data Pegawai';
$breadcrumb   = 'Kelola';
$subJudul     = 'Sumber data mandiri — dapat ditambah atau diubah kapan pun sesuai kebutuhan jenis surat baru.';

require __DIR__ . '/views/layout_atas.php';
?>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-<?php echo $pesanTipe; ?>"><?php echo htmlspecialchars((string) $pesan); ?></div>
<?php endif; ?>

<div class="toolbar">
  <form method="get" action="pegawai.php" style="flex:1; max-width:320px;">
    <input class="search-input" type="text" name="cari" value="<?php echo htmlspecialchars((string) $cari); ?>" placeholder="Cari nama, NIP, atau unit kerja&hellip;">
  </form>
  <button type="button" class="btn btn-primary" onclick="document.getElementById('formTambah').style.display='block'">+ Tambah Pegawai</button>
</div>

<div class="form-card" id="formTambah" style="display:none; margin-bottom:20px;">
  <form method="post" action="pegawai.php">
    <?php echo Csrf::field(); ?>
    <input type="hidden" name="aksi" value="tambah">
    <div class="grid-3">
      <div class="field"><label>NIP <span class="req">*</span></label><input type="text" name="nip" required></div>
      <div class="field"><label>Nama Lengkap <span class="req">*</span></label><input type="text" name="nama_lengkap" required></div>
      <div class="field"><label>Gelar Depan</label><input type="text" name="gelar_depan"></div>
      <div class="field"><label>Gelar Belakang</label><input type="text" name="gelar_belakang"></div>
      <div class="field"><label>Pangkat</label><input type="text" name="pangkat"></div>
      <div class="field"><label>Golongan/Ruang</label><input type="text" name="golongan_ruang" placeholder="mis. III/b"></div>
      <div class="field"><label>Jabatan</label><input type="text" name="jabatan"></div>
      <div class="field"><label>Unit Kerja</label><input type="text" name="unit_kerja"></div>
      <div class="field"><label>TMT (Mulai Kerja)</label><input type="date" name="tmt"></div>
    </div>
    <div style="display:flex; gap:10px; margin-top:8px;">
      <button type="submit" class="btn btn-primary">Simpan Pegawai</button>
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('formTambah').style.display='none'">Batal</button>
    </div>
  </form>
</div>

<?php if ($editPegawai): ?>
<div class="form-card" id="formUbah" style="margin-bottom:20px;">
  <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Ubah Pegawai</h4>
  <form method="post" action="pegawai.php">
    <?php echo Csrf::field(); ?>
    <input type="hidden" name="aksi" value="ubah">
    <input type="hidden" name="id" value="<?php echo (int) $editPegawai['id']; ?>">
    <div class="grid-3">
      <div class="field"><label>NIP <span class="req">*</span></label><input type="text" name="nip" value="<?php echo htmlspecialchars((string) $editPegawai['nip']); ?>" required></div>
      <div class="field"><label>Nama Lengkap <span class="req">*</span></label><input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars((string) $editPegawai['nama_lengkap']); ?>" required></div>
      <div class="field"><label>Gelar Depan</label><input type="text" name="gelar_depan" value="<?php echo htmlspecialchars((string) $editPegawai['gelar_depan']); ?>"></div>
      <div class="field"><label>Gelar Belakang</label><input type="text" name="gelar_belakang" value="<?php echo htmlspecialchars((string) $editPegawai['gelar_belakang']); ?>"></div>
      <div class="field"><label>Pangkat</label><input type="text" name="pangkat" value="<?php echo htmlspecialchars((string) $editPegawai['pangkat']); ?>"></div>
      <div class="field"><label>Golongan/Ruang</label><input type="text" name="golongan_ruang" value="<?php echo htmlspecialchars((string) $editPegawai['golongan_ruang']); ?>" placeholder="mis. III/b"></div>
      <div class="field"><label>Jabatan</label><input type="text" name="jabatan" value="<?php echo htmlspecialchars((string) $editPegawai['jabatan']); ?>"></div>
      <div class="field"><label>Unit Kerja</label><input type="text" name="unit_kerja" value="<?php echo htmlspecialchars((string) $editPegawai['unit_kerja']); ?>"></div>
      <div class="field"><label>TMT (Mulai Kerja)</label><input type="date" name="tmt" value="<?php echo htmlspecialchars((string) $editPegawai['tmt']); ?>"></div>
    </div>
    <div class="field" style="flex-direction:row; align-items:center; gap:8px;">
      <input type="checkbox" name="status_aktif" id="statusAktifEdit" value="1" style="width:auto;" <?php echo ((int) $editPegawai['status_aktif'] === 1) ? 'checked' : ''; ?>>
      <label for="statusAktifEdit" style="margin:0;">Aktif</label>
    </div>
    <div style="display:flex; gap:10px; margin-top:8px;">
      <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      <a class="btn btn-secondary" href="pegawai.php<?php echo $cari !== '' ? '?cari=' . urlencode($cari) : ''; ?>">Batal</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Nama</th><th>NIP</th><th>Jabatan</th><th>Golongan</th><th>Unit Kerja</th><th>TMT</th><th></th></tr>
    </thead>
    <tbody>
      <?php if (count($daftarPegawai) === 0): ?>
        <tr><td colspan="7" style="text-align:center; color:var(--ink-dim); font-style:italic;">Tidak ada pegawai yang cocok.</td></tr>
      <?php else: foreach ($daftarPegawai as $p): ?>
        <tr>
          <td><?php echo htmlspecialchars(trim($p['gelar_depan'] . ' ' . $p['nama_lengkap'] . ($p['gelar_belakang'] ? ', ' . $p['gelar_belakang'] : ''))); ?><?php echo ((int) $p['status_aktif'] !== 1) ? ' <span style="color:var(--ink-dim); font-size:0.75rem;">(nonaktif)</span>' : ''; ?></td>
          <td class="tnum"><?php echo htmlspecialchars((string) $p['nip']); ?></td>
          <td><?php echo htmlspecialchars((string) $p['jabatan']); ?></td>
          <td><?php echo htmlspecialchars((string) $p['golongan_ruang']); ?></td>
          <td><?php echo htmlspecialchars((string) $p['unit_kerja']); ?></td>
          <td><?php echo $p['tmt'] ? htmlspecialchars((string) $p['tmt']) : '<span style="color:var(--ink-dim); font-style:italic;">belum diisi</span>'; ?></td>
          <td style="white-space:nowrap;">
            <a class="btn btn-secondary" style="padding:4px 10px; font-size:0.78rem;" href="pegawai.php?edit=<?php echo (int) $p['id']; ?><?php echo $cari !== '' ? '&cari=' . urlencode($cari) : ''; ?>">Ubah</a>
            <form method="post" action="pegawai.php" style="display:inline;" onsubmit="return confirm('Hapus data pegawai &quot;<?php echo htmlspecialchars(addslashes($p['nama_lengkap'])); ?>&quot;? Tindakan ini tidak bisa dibatalkan.');">
              <?php echo Csrf::field(); ?>
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
              <button type="submit" class="btn btn-secondary" style="padding:4px 10px; font-size:0.78rem;">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/views/layout_bawah.php'; ?>
