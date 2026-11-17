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
use Aurat\Database;
use Aurat\Pengaturan;
use Aurat\Surat\IconLibrary;
use Aurat\Surat\JenisSuratRepository;

// Modul izin (db/037): saat mode != nonaktif, menu surat izin lama diganti modul.
$izinMode = 'nonaktif';
$izinTugas = 0;
$izinPejabat = false;
try {
    $izinMode = (string) Database::pdo()->query('SELECT mode FROM izin_pengaturan WHERE id = 1')->fetchColumn();
    $st = Database::pdo()->prepare('SELECT COUNT(*) FROM izin_tahap WHERE pemberi_user_id = ? AND status = \'menunggu\'');
    $st->execute(array(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0));
    $izinTugas = (int) $st->fetchColumn();
    $izinPejabat = !Auth::isAdmin() && \Aurat\Izin\Permohonan::pejabatAktif(Database::pdo(), isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
} catch (\PDOException $e) {
    // migrasi 037 belum jalan - menu lama saja
}
$izinTampil = $izinMode !== '' && $izinMode !== 'nonaktif';

$menuSurat = array();
foreach (JenisSuratRepository::semua(true) as $js) {
    if (!Auth::bolehAksesJenisSurat($js['kode'])) {
        continue; // peran 'pengguna' - sembunyikan jenis surat yg gak boleh diakses
    }
    if ($izinTampil && $js['kode'] === 'izin_keluar_kantor') {
        continue;
    }
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
<title><?php echo htmlspecialchars((string) $judulHalaman); ?> — <?php echo htmlspecialchars(Pengaturan::namaAplikasi()); ?></title>
<link rel="icon" type="image/png" href="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>assets/img/favicon.png">
<link rel="stylesheet" href="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>assets/css/style.css">
<?php if (!empty($cssTambahan)): ?><link rel="stylesheet" href="<?php echo isset($rootAsset) ? $rootAsset : ''; ?><?php echo htmlspecialchars((string) $cssTambahan); ?>"><?php endif; ?>
<script>(function(){try{var m=localStorage.getItem('aura-theme');if(m==='light'||m==='dark')document.documentElement.setAttribute('data-theme',m);}catch(e){}})();</script>
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <div class="brand">
      <img class="brand-mark" src="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>assets/img/logo-mark.png" alt="<?php echo htmlspecialchars(Pengaturan::namaAplikasi()); ?>">
      <div class="brand-text"><?php echo htmlspecialchars(Pengaturan::namaAplikasi()); ?><small><?php echo htmlspecialchars(Pengaturan::namaInstansi()); ?></small></div>
    </div>

    <?php
    // Satu baris menu: ikon garis + label (+ lencana opsional). Ikon dari Lucide v1.66.0 (lucide.dev, ISC).
    $IKON_NAV = array(
        'rumah' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'pintu' => '<path d="M10 21H2"/><path d="M10 3H7a2 2 0 00-2 2v16"/><path d="M14 12h.01"/><path d="M19 21V5a2 2 0 00-1.675-1.974l-6.163-1.013A1 1 0 0010 3v18a1 1 0 001.124.992z"/><path d="M22 21h-3"/>',
        'centang' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="m16 9-5.5 5.5L8 12"/>',
        'grafik' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'daftar' => '<path d="M3 5h.01"/><path d="M3 12h.01"/><path d="M3 19h.01"/><path d="M8 5h13"/><path d="M8 12h13"/><path d="M8 19h13"/>',
        'gerigi' => '<path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/>',
        'orang' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'arsip' => '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
        'templat' => '<rect width="18" height="7" x="3" y="3" rx="1"/><rect width="9" height="7" x="3" y="14" rx="1"/><rect width="5" height="7" x="16" y="14" rx="1"/>',
        'kunci' => '<path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>',
    );
    $navItem = function ($kode, $label, $href, $ikon, $lencana = 0) use ($halamanAktif, $IKON_NAV) {
        echo '<a class="nav-item ' . ($halamanAktif === $kode ? 'active' : '') . '" href="' . (isset($GLOBALS['rootAsset']) ? $GLOBALS['rootAsset'] : '') . $href . '">'
            . '<span class="nav-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $IKON_NAV[$ikon] . '</svg></span>' . htmlspecialchars($label)
            . ($lencana > 0 ? ' <span class="badge badge-segera">' . (int) $lencana . '</span>' : '') . '</a>';
    };
    ?>
    <nav>
      <div class="nav-group">
        <span class="nav-label">Menu</span>
        <?php $navItem('dashboard', 'Dasbor', 'index.php', 'rumah'); ?>
        <?php if ($izinTampil) { $navItem('izin', 'Izin Keluar', 'izin/index.php', 'pintu'); } ?>
        <?php if ($izinTugas > 0) { $navItem('izin_tugas', 'Tugas Izin', 'izin/tugas.php', 'centang', $izinTugas); } ?>
        <?php if (($izinPejabat || Auth::isAdmin()) && $izinTampil) { $navItem('admin_izin_dasbor', 'Dasbor Izin', 'admin/izin_dasbor.php', 'grafik'); } ?>
      </div>
      <?php if ($menuSurat): ?>
      <div class="nav-group">
        <span class="nav-label">Buat Surat</span>
        <?php foreach ($menuSurat as $m): ?>
        <a class="nav-item <?php echo $halamanAktif === $m['kode'] ? 'active' : ''; ?>" href="<?php echo isset($rootAsset) ? $rootAsset : ''; ?><?php echo $m['href']; ?>">
          <span class="nav-icon"><?php echo IconLibrary::render($m['icon']); ?></span>
          <?php echo htmlspecialchars((string) $m['label']); ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if (Auth::isAdmin()): ?>
      <div class="nav-group">
        <span class="nav-label">Izin</span>
        <?php $navItem('admin_izin_register', 'Register Izin', 'admin/izin_register.php', 'daftar'); ?>
        <?php $navItem('admin_izin_pengaturan', 'Pengaturan Izin', 'admin/izin_pengaturan.php', 'gerigi'); ?>
      </div>
      <?php endif; ?>
      <?php if (Auth::isPengelolaAtauAdmin()): ?>
      <div class="nav-group">
        <span class="nav-label">Data &amp; Arsip</span>
        <?php $navItem('pegawai', 'Data Pegawai', 'pegawai.php', 'orang'); ?>
        <?php $navItem('admin_surat_diterbitkan', 'Riwayat Surat', 'admin/surat_diterbitkan.php', 'arsip'); ?>
      </div>
      <div class="nav-group">
        <span class="nav-label">Pengaturan</span>
        <?php $navItem('admin_jenis_surat', 'Jenis Surat', 'admin/jenis_surat.php', 'templat'); ?>
        <?php if (Auth::isAdmin()) { $navItem('admin_user_login', 'Pengguna', 'admin/user_login.php', 'kunci'); $navItem('admin_pengaturan', 'Aplikasi', 'admin/pengaturan.php', 'gerigi'); } ?>
      </div>
      <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-footer-row">
        <div class="sidebar-user">
          <b><?php echo htmlspecialchars(Auth::namaTampilan()); ?></b>
          <span><?php echo htmlspecialchars(array('admin' => 'Administrator', 'pengelola' => 'Pengelola Surat', 'pengguna' => 'Pegawai')[(string) Auth::peran()] ?? ''); ?></span>
        </div>
        <button type="button" class="theme-toggle" data-mode="auto" title="Tema" aria-label="Ganti tema">
          <svg class="icon-auto" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2v2"/><path d="M14.837 16.385a6 6 0 1 1-7.223-7.222c.624-.147.97.66.715 1.248a4 4 0 0 0 5.26 5.259c.589-.255 1.396.09 1.248.715"/><path d="M16 12a4 4 0 0 0-4-4"/><path d="m19 5-1.256 1.256"/><path d="M20 12h2"/></svg>
          <svg class="icon-light" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
          <svg class="icon-dark" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"/></svg>
        </button>
      </div>
      <form method="post" action="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>logout.php">
        <?php echo Csrf::field(); ?>
        <button type="submit" class="logout-link">Keluar</button>
      </form>
    </div>
  </aside>

  <main class="main">
    <div class="topbar"<?php if (!empty($aksiHalaman)): ?> style="display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap;"<?php endif; ?>>
      <?php if (!empty($aksiHalaman)): ?><div><?php endif; ?>
      <?php if (!empty($breadcrumb)): ?><div class="crumb"><?php echo htmlspecialchars((string) $breadcrumb); ?></div><?php endif; ?>
      <h1><?php echo htmlspecialchars((string) $judulHalaman); ?></h1>
      <?php if (!empty($subJudul)): ?><p><?php echo htmlspecialchars((string) $subJudul); ?></p><?php endif; ?>
      <?php if (!empty($aksiHalaman)): ?></div><div class="iz-aksi"><?php echo $aksiHalaman; /* HTML dari halaman pemanggil, sudah di-escape di sana */ ?></div><?php endif; ?>
    </div>
