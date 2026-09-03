<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Database;

Auth::requireAdmin();

$pdo = Database::pdo();
$pesan = '';
$pesanTipe = 'info';

function auratUsernameValid($username)
{
    return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9._]{2,49}$/', $username);
}

function auratJumlahAktif(PDO $pdo)
{
    return (int) $pdo->query('SELECT COUNT(*) FROM user_login WHERE status_aktif = 1')->fetchColumn();
}

function auratJumlahAdmin(PDO $pdo)
{
    return (int) $pdo->query('SELECT COUNT(*) FROM user_login WHERE is_admin = 1')->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

    if ($aksi === 'tambah') {
        $username      = isset($_POST['username']) ? trim($_POST['username']) : '';
        $namaTampilan  = isset($_POST['nama_tampilan']) ? trim($_POST['nama_tampilan']) : '';
        $password      = isset($_POST['password']) ? $_POST['password'] : '';
        $isAdmin       = !empty($_POST['is_admin']) ? 1 : 0;

        if (!auratUsernameValid($username) || $namaTampilan === '' || $password === '') {
            $pesan = 'Nama pengguna (huruf/angka/titik/underscore, diawali huruf), Nama Tampilan, dan Kata Sandi wajib diisi dengan benar.';
            $pesanTipe = 'error';
        } else {
            $stmt = $pdo->prepare('INSERT INTO user_login (username, password_hash, nama_tampilan, is_admin) VALUES (?, ?, ?, ?)');
            try {
                $stmt->execute(array($username, password_hash($password, PASSWORD_DEFAULT), $namaTampilan, $isAdmin));
                header('Location: user_login.php');
                exit;
            } catch (\PDOException $e) {
                $pesan = (strpos($e->getMessage(), 'uq_user_login_username') !== false)
                    ? 'Nama pengguna tersebut sudah dipakai.'
                    : 'Gagal menyimpan akun.';
                $pesanTipe = 'error';
            }
        }
    } elseif ($aksi === 'ubah' && isset($_POST['id'])) {
        $id           = (int) $_POST['id'];
        $namaTampilan = isset($_POST['nama_tampilan']) ? trim($_POST['nama_tampilan']) : '';

        if ($namaTampilan === '') {
            $pesan = 'Nama Tampilan wajib diisi.';
            $pesanTipe = 'error';
        } else {
            $pdo->prepare('UPDATE user_login SET nama_tampilan = ?, updated_at = NOW() WHERE id = ?')
                ->execute(array($namaTampilan, $id));
            $pesan = 'Perubahan disimpan.';
        }
    } elseif ($aksi === 'reset_password' && isset($_POST['id'])) {
        $id           = (int) $_POST['id'];
        $passwordBaru = isset($_POST['password_baru']) ? $_POST['password_baru'] : '';

        if ($passwordBaru === '') {
            $pesan = 'Kata sandi baru wajib diisi.';
            $pesanTipe = 'error';
        } else {
            $pdo->prepare('UPDATE user_login SET password_hash = ?, percobaan_gagal = 0, terkunci_hingga = NULL, updated_at = NOW() WHERE id = ?')
                ->execute(array(password_hash($passwordBaru, PASSWORD_DEFAULT), $id));
            $pesan = 'Kata sandi berhasil diganti.';
        }
    } elseif ($aksi === 'toggle_aktif' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        if ($id === (int) $_SESSION['user_id']) {
            $pesan = 'Tidak bisa menonaktifkan akun yang sedang dipakai login saat ini.';
            $pesanTipe = 'error';
        } else {
            $baris = $pdo->prepare('SELECT status_aktif FROM user_login WHERE id = ?');
            $baris->execute(array($id));
            $statusSaatIni = $baris->fetchColumn();

            if ((int) $statusSaatIni === 1 && auratJumlahAktif($pdo) <= 1) {
                $pesan = 'Tidak bisa menonaktifkan — minimal harus ada 1 akun aktif.';
                $pesanTipe = 'error';
            } else {
                $pdo->prepare('UPDATE user_login SET status_aktif = 1 - status_aktif, updated_at = NOW() WHERE id = ?')->execute(array($id));
            }
        }
    } elseif ($aksi === 'toggle_admin' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $baris = $pdo->prepare('SELECT is_admin FROM user_login WHERE id = ?');
        $baris->execute(array($id));
        $adminSaatIni = (int) $baris->fetchColumn();

        if ($adminSaatIni === 1 && $id === (int) $_SESSION['user_id']) {
            $pesan = 'Tidak bisa mencabut peran admin dari akun yang sedang dipakai login saat ini.';
            $pesanTipe = 'error';
        } elseif ($adminSaatIni === 1 && auratJumlahAdmin($pdo) <= 1) {
            $pesan = 'Tidak bisa mencabut peran admin — minimal harus ada 1 administrator.';
            $pesanTipe = 'error';
        } else {
            $pdo->prepare('UPDATE user_login SET is_admin = 1 - is_admin, updated_at = NOW() WHERE id = ?')->execute(array($id));
        }
    } elseif ($aksi === 'hapus' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $baris = $pdo->prepare('SELECT is_admin FROM user_login WHERE id = ?');
        $baris->execute(array($id));
        $adminSaatIni = (int) $baris->fetchColumn();

        if ($id === (int) $_SESSION['user_id']) {
            $pesan = 'Tidak bisa menghapus akun yang sedang dipakai login saat ini.';
            $pesanTipe = 'error';
        } elseif ($pdo->query('SELECT COUNT(*) FROM user_login')->fetchColumn() <= 1) {
            $pesan = 'Tidak bisa menghapus — minimal harus ada 1 akun.';
            $pesanTipe = 'error';
        } elseif ($adminSaatIni === 1 && auratJumlahAdmin($pdo) <= 1) {
            $pesan = 'Tidak bisa menghapus — minimal harus ada 1 administrator.';
            $pesanTipe = 'error';
        } else {
            $pdo->prepare('DELETE FROM user_login WHERE id = ?')->execute(array($id));
        }
    }
}

$daftarUser = $pdo->query('SELECT * FROM user_login ORDER BY username')->fetchAll();

$halamanAktif = 'admin_user_login';
$judulHalaman = 'Kelola Pengguna';
$breadcrumb   = 'Kelola Pengguna';
$subJudul     = 'Tambah, ubah nama tampilan, reset kata sandi, atau nonaktifkan akun login administrator.';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-<?php echo htmlspecialchars((string) $pesanTipe); ?>"><?php echo htmlspecialchars((string) $pesan); ?></div>
<?php endif; ?>

<div class="toolbar">
  <span></span>
  <button type="button" class="btn btn-primary" onclick="document.getElementById('formTambah').style.display='block'">+ Pengguna Baru</button>
</div>

<div class="form-card" id="formTambah" style="display:none; margin-bottom:20px;">
  <form method="post" action="user_login.php">
      <?php echo Csrf::field(); ?>
    <input type="hidden" name="aksi" value="tambah">
    <div class="grid-2">
      <div class="field">
        <label>Nama Pengguna <span class="req">*</span></label>
        <input type="text" name="username" placeholder="mis. admin.kepegawaian" pattern="[a-zA-Z][a-zA-Z0-9._]{2,49}" required>
      </div>
      <div class="field">
        <label>Nama Tampilan <span class="req">*</span></label>
        <input type="text" name="nama_tampilan" placeholder="mis. Dian Puspitasari" required>
      </div>
      <div class="field">
        <label>Kata Sandi <span class="req">*</span></label>
        <input type="password" name="password" autocomplete="new-password" required>
      </div>
    </div>
    <div class="field" style="flex-direction:row; align-items:center; gap:8px;">
      <input type="checkbox" name="is_admin" id="isAdminBaru" value="1" style="width:auto;">
      <label for="isAdminBaru" style="margin:0;">Jadikan administrator (bisa membuka Kelola Pengguna)</label>
    </div>
    <div style="display:flex; gap:10px;">
      <button type="submit" class="btn btn-primary">Simpan</button>
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('formTambah').style.display='none'">Batal</button>
    </div>
  </form>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Nama Pengguna</th><th>Nama Tampilan</th><th>Peran</th><th>Status</th><th>Login Terakhir</th><th></th></tr>
    </thead>
    <tbody>
      <?php if (count($daftarUser) === 0): ?>
        <tr><td colspan="6" style="text-align:center; color:var(--ink-dim); font-style:italic;">Belum ada pengguna.</td></tr>
      <?php else: foreach ($daftarUser as $u): ?>
        <tr>
          <td class="tnum"><?php echo htmlspecialchars((string) $u['username']); ?><?php echo ((int) $u['id'] === (int) $_SESSION['user_id']) ? ' <span style="font-size:0.7rem; color:var(--ink-dim);">(Anda)</span>' : ''; ?></td>
          <td><?php echo htmlspecialchars((string) $u['nama_tampilan']); ?></td>
          <td><?php if ((int) $u['is_admin'] === 1): ?><span class="kind" style="align-self:flex-start;">Administrator</span><?php else: ?>Pengguna<?php endif; ?></td>
          <td><?php echo ((int) $u['status_aktif'] === 1) ? 'Aktif' : 'Nonaktif'; ?></td>
          <td><?php echo $u['login_terakhir_at'] ? htmlspecialchars((string) $u['login_terakhir_at']) : '—'; ?></td>
          <td style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('resetPass_<?php echo (int) $u['id']; ?>').style.display='flex'">Reset Sandi</button>
            <form method="post" action="user_login.php" style="display:inline;">
                <?php echo Csrf::field(); ?>
              <input type="hidden" name="aksi" value="toggle_admin">
              <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
              <button type="submit" class="btn btn-secondary"><?php echo ((int) $u['is_admin'] === 1) ? 'Cabut Admin' : 'Jadikan Admin'; ?></button>
            </form>
            <form method="post" action="user_login.php" style="display:inline;">
                <?php echo Csrf::field(); ?>
              <input type="hidden" name="aksi" value="toggle_aktif">
              <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
              <button type="submit" class="btn btn-secondary"><?php echo ((int) $u['status_aktif'] === 1) ? 'Nonaktifkan' : 'Aktifkan'; ?></button>
            </form>
            <form method="post" action="user_login.php" style="display:inline;" onsubmit="return confirm('Hapus akun ini? Tidak bisa dibatalkan.');">
                <?php echo Csrf::field(); ?>
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
              <button type="submit" class="btn btn-secondary">Hapus</button>
            </form>
          </td>
        </tr>
        <tr id="resetPass_<?php echo (int) $u['id']; ?>" style="display:none;">
          <td colspan="6">
            <form method="post" action="user_login.php" style="display:flex; gap:10px; align-items:flex-end; max-width:480px;">
                <?php echo Csrf::field(); ?>
              <input type="hidden" name="aksi" value="reset_password">
              <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
              <div class="field" style="flex:1; margin-bottom:0;">
                <label>Kata sandi baru untuk <?php echo htmlspecialchars((string) $u['username']); ?></label>
                <input type="password" name="password_baru" autocomplete="new-password" required>
              </div>
              <button type="submit" class="btn btn-primary">Ganti</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
