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
    // Satu baris menu: ikon garis + label (+ lencana opsional). Ikon sama gaya dgn ikon jenis surat.
    $IKON_NAV = array(
        'rumah' => '<path d="M4 11l8-7 8 7v9H4z"/><path d="M10 20v-5h4v5"/>',
        'pintu' => '<path d="M6 21V4h10v17"/><path d="M4 21h16M13 12h.01"/><path d="M16 7l3 1v13"/>',
        'centang' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8.5 12.5l2.5 2.5 5-5.5"/>',
        'grafik' => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
        'daftar' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
        'gerigi' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8"/>',
        'orang' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c1-4 4-6 7-6s6 2 7 6"/>',
        'arsip' => '<rect x="3.5" y="4" width="17" height="5" rx="1"/><path d="M5 9v11h14V9M10 13h4"/>',
        'templat' => '<path d="M7 3.5h7l4 4V20.5H7z"/><path d="M14 3.5V8h4M10 13h5M10 16.5h5"/>',
        'kunci' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l8-8M16 7l2 2"/>',
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
          <svg class="icon-auto" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M12 3.5a8.5 8.5 0 0 1 0 17Z" fill="currentColor"/>
          </svg>
          <svg class="icon-light" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="12" cy="12" r="4.5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M12 2.5v2.5M12 19v2.5M21.5 12H19M5 12H2.5M18.5 5.5l-1.8 1.8M7.3 16.7l-1.8 1.8M18.5 18.5l-1.8-1.8M7.3 7.3 5.5 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          <svg class="icon-dark" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
          </svg>
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
