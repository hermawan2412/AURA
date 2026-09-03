<?php

require __DIR__ . '/src/bootstrap.php';

use Aurat\Auth;
use Aurat\Database;
use Aurat\Surat\IconLibrary;
use Aurat\Surat\JenisSuratRepository;
use Aurat\Surat\TemplateSuratRepository;

Auth::requireLogin();

$halamanAktif = 'dashboard';
$judulHalaman = 'Pilih jenis surat';
$breadcrumb   = 'Dasbor';
$subJudul     = 'Isi data pada formulir, lalu unduh dokumennya langsung sebagai berkas Word (.docx) siap cetak. Tidak ada berkas yang tersimpan di server.';

$semuaJenisSurat = JenisSuratRepository::semua(true);

$jumlahPegawai = (int) Database::pdo()->query('SELECT COUNT(*) FROM pegawai WHERE status_aktif = 1')->fetchColumn();

require __DIR__ . '/views/layout_atas.php';
?>
<div class="card-grid">
  <?php foreach ($semuaJenisSurat as $js): ?>
    <?php
      $tersedia = (bool) TemplateSuratRepository::templateUntuk($js['id']);
      if (!$tersedia && $js['kategori'] === 'dua_dokumen') {
          foreach (JenisSuratRepository::subJenisUntuk($js['id']) as $sj) {
              if (TemplateSuratRepository::templateUntuk($js['id'], $sj['id'])) {
                  $tersedia = true;
                  break;
              }
          }
      }
    ?>
    <?php if ($tersedia): ?>
    <a class="letter-card" href="surat/index.php?kode=<?php echo urlencode($js['kode']); ?>">
    <?php else: ?>
    <div class="letter-card" style="opacity:.6;">
    <?php endif; ?>
      <span class="letter-icon"><?php echo IconLibrary::render($js['icon']); ?></span>
      <span class="kind"><?php echo $js['kategori'] === 'dua_dokumen' ? 'Body + Lampiran' : 'Satu dokumen'; ?></span>
      <h3><?php echo htmlspecialchars((string) $js['nama']); ?></h3>
      <p><?php echo $tersedia ? 'Klik untuk mulai mengisi.' : 'Segera hadir — template belum dipasang.'; ?></p>
      <?php if ($tersedia): ?><span class="go">Buat surat &rarr;</span><?php endif; ?>
    <?php echo $tersedia ? '</a>' : '</div>'; ?>
  <?php endforeach; ?>
</div>

<div style="margin-top:40px;">
  <h2 style="font-size:0.98rem; margin-bottom:14px;">Data pendukung</h2>
  <div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,240px));">
    <a class="letter-card" href="pegawai.php">
      <span class="kind"><?php echo $jumlahPegawai; ?> pegawai</span>
      <h3>Data Pegawai</h3>
      <p>Kelola nama, NIP, jabatan, golongan, dan unit kerja.</p>
      <span class="go">Buka &rarr;</span>
    </a>
  </div>
</div>
<?php require __DIR__ . '/views/layout_bawah.php'; ?>
