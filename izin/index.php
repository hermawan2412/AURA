<?php
// Pola 1 — List / Index: permohonan izin milik pengguna.

require __DIR__ . '/_umum.php';

use Aurat\Csrf;
use Aurat\Izin\Permohonan;

$s = $db->prepare('SELECT * FROM izin_permohonan WHERE pemohon_user_id = ? ORDER BY tanggal DESC, jam_mulai DESC LIMIT 50');
$s->execute(array($userId));
$milikSaya = $s->fetchAll();

$s = $db->prepare('SELECT COUNT(*) FROM izin_tahap WHERE pemberi_user_id = ? AND status = \'menunggu\'');
$s->execute(array($userId));
$jumlahTugas = (int) $s->fetchColumn();

$s = $db->prepare('SELECT * FROM izin_notifikasi WHERE penerima_user_id = ? AND dibaca_at IS NULL ORDER BY id DESC LIMIT 5');
$s->execute(array($userId));
$notifikasi = $s->fetchAll();

$halamanAktif = 'izin';
$judulHalaman = 'Izin Keluar';
$breadcrumb = 'Izin';
$subJudul = 'Izin keluar sementara atau pulang sebelum waktunya. Pejabat memutus lewat AURA dan diberi tahu lewat WhatsApp.';
$aksiHalaman = ($jumlahTugas > 0 ? '<a class="btn btn-secondary" href="tugas.php">Menunggu keputusan Anda <span class="badge badge-segera">' . $jumlahTugas . '</span></a>' : '')
    . ($peng['mode'] === 'aktif' ? '<a class="btn btn-primary" href="form.php">' . ikonIzin('tambah', 18) . 'Ajukan izin</a>' : '');
require __DIR__ . '/../views/layout_atas.php';
?>

<div class="iz-page">
  <?php tampilKilat(); ?>
  <?php if ($peng['mode'] !== 'aktif'): ?>
    <div class="alert alert-error" role="alert">Modul izin <?php echo $peng['mode'] === 'pemeliharaan' ? 'sedang dalam pemeliharaan' : 'belum diaktifkan'; ?>. Hubungi pejabat secara langsung.</div>
  <?php endif; ?>

  <?php if ($notifikasi): ?>
  <div class="iz-kabar" role="status">
    <div>
      <b>Pemberitahuan baru</b>
      <ul>
        <?php foreach ($notifikasi as $n): ?>
          <li><a href="detail.php?id=<?php echo (int) $n['permohonan_id']; ?>"><?php echo h($n['pesan']); ?></a> <span class="redup"><?php echo h(waktuKantor($n['dibuat_at'], $peng['zona_waktu'])); ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <form method="post" action="aksi.php">
      <?php echo Csrf::field(); ?>
      <input type="hidden" name="aksi" value="baca_notifikasi">
      <button type="submit" class="btn btn-secondary btn-kecil">Tandai dibaca</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="iz-panel">
    <div class="iz-alat">
      <div class="iz-cari">
        <label for="cari">Cari</label>
        <input id="cari" type="search" placeholder="Tanggal, jenis, atau status — tekan /" data-cari-tabel="tabel-izin" data-cari-info="info-izin" autocomplete="off">
      </div>
    </div>
    <div class="iz-gulir">
      <table class="iz-tabel" id="tabel-izin">
        <thead><tr><th>Tanggal</th><th>Jam</th><th>Jenis</th><th>Status</th><th class="iz-panah"><span class="visually-hidden">Buka</span></th></tr></thead>
        <tbody>
        <?php if (!$milikSaya): ?>
          <tr><td colspan="5"><div class="iz-kosong"><b>Belum ada permohonan izin</b>Permohonan yang Anda ajukan akan tampil di sini.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($milikSaya as $i => $p): ?>
          <tr class="iz-baris" data-href="detail.php?id=<?php echo (int) $p['id']; ?>" style="--i:<?php echo $i; ?>">
            <td><a class="iz-utama" href="detail.php?id=<?php echo (int) $p['id']; ?>"><?php echo h(tanggalIzin($p)); ?></a></td>
            <td class="tnum"><?php echo h(jamIzin($p)); ?></td>
            <td><?php echo h(ucfirst(Permohonan::LABEL_JENIS[$p['jenis']])); ?></td>
            <td><?php echo badgeIzin($p['status']); ?></td>
            <td class="iz-panah"><?php echo ikonIzin('panah', 16); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="iz-kaki"><span id="info-izin" data-awal="<?php echo count($milikSaya); ?> permohonan terakhir"><?php echo count($milikSaya); ?> permohonan terakhir</span><span>Maks. 50 terbaru</span></div>
  </div>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
