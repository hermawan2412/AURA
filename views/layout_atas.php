<?php
/**
 * Partial header + sidebar. Halaman pemanggil wajib set:
 *   $halamanAktif  (mis. 'dashboard', 'pegawai', 'cuti')
 *   $judulHalaman
 *   $breadcrumb     (opsional)
 * dan sudah memanggil Auth::requireLogin() sebelum include ini.
 */
use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Surat\IconLibrary;
use Aurat\Surat\JenisSuratRepository;

$menuSurat = array();
foreach (JenisSuratRepository::semua(true) as $js) {
    $menuSurat[] = array(
        'kode'  => $js['kode'],
        'label' => $js['nama'],
        'icon'  => $js['icon'],
        'href'  => 'surat/index.php?kode=' . urlencode($js['kode']),
    );
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars((string) $judulHalaman); ?> — AURA</title>
<link rel="stylesheet" href="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>assets/css/style.css">
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <div class="brand">
      <img class="brand-mark" src="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>assets/img/logo-mark.png" alt="AURA">
      <div class="brand-text">AURA<small>Bagian Kepegawaian</small></div>
    </div>

    <nav>
      <div class="nav-group">
        <span class="nav-label">Menu</span>
        <a class="nav-item <?php echo $halamanAktif === 'dashboard' ? 'active' : ''; ?>" href="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>index.php">Dasbor</a>
      </div>
      <div class="nav-group">
        <span class="nav-label">Buat Surat</span>
        <?php foreach ($menuSurat as $m): ?>
        <a class="nav-item <?php echo $halamanAktif === $m['kode'] ? 'active' : ''; ?>" href="<?php echo isset($rootAsset) ? $rootAsset : ''; ?><?php echo $m['href']; ?>">
          <span class="nav-icon"><?php echo IconLibrary::render($m['icon']); ?></span>
          <?php echo htmlspecialchars((string) $m['label']); ?>
        </a>
        <?php endforeach; ?>
      </div>
      <div class="nav-group">
        <span class="nav-label">Kelola</span>
        <a class="nav-item <?php echo $halamanAktif === 'pegawai' ? 'active' : ''; ?>" href="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>pegawai.php">Data Pegawai</a>
        <a class="nav-item <?php echo $halamanAktif === 'admin_jenis_surat' ? 'active' : ''; ?>" href="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>admin/jenis_surat.php">Kelola Jenis Surat</a>
        <?php if (Auth::isAdmin()): ?>
        <a class="nav-item <?php echo $halamanAktif === 'admin_user_login' ? 'active' : ''; ?>" href="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>admin/user_login.php">Kelola Pengguna</a>
        <?php endif; ?>
      </div>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <b><?php echo htmlspecialchars(Auth::namaTampilan()); ?></b>
        <span>Administrator Kepegawaian</span>
      </div>
      <form method="post" action="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>logout.php">
        <?php echo Csrf::field(); ?>
        <button type="submit" class="logout-link">Keluar</button>
      </form>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <?php if (!empty($breadcrumb)): ?><div class="crumb"><?php echo htmlspecialchars((string) $breadcrumb); ?></div><?php endif; ?>
      <h1><?php echo htmlspecialchars((string) $judulHalaman); ?></h1>
      <?php if (!empty($subJudul)): ?><p><?php echo htmlspecialchars((string) $subJudul); ?></p><?php endif; ?>
    </div>
