<?php

require __DIR__ . '/_umum.php';

use Aurat\Csrf;
use Aurat\Izin\Permohonan;

$s = $db->prepare('SELECT * FROM izin_permohonan WHERE pemohon_user_id = ? ORDER BY tanggal DESC, jam_mulai DESC LIMIT 50');
$s->execute(array($userId));
$milikSaya = $s->fetchAll();

$s = $db->prepare('SELECT COUNT(*) FROM izin_tahap WHERE pemberi_user_id = ? AND status = \'menunggu\'');
$s->execute(array($userId));
$jumlahTugas = (int) $s->fetchColumn();

$s = $db->prepare('SELECT * FROM izin_notifikasi WHERE penerima_user_id = ? AND dibaca_at IS NULL ORDER BY id DESC LIMIT 20');
$s->execute(array($userId));
$notifikasi = $s->fetchAll();

$halamanAktif = 'izin';
$judulHalaman = 'Izin Keluar';
$breadcrumb = 'Izin';
$subJudul = 'Izin keluar sementara atau pulang sebelum waktunya. Persetujuan dilakukan pejabat melalui AURA.';
require __DIR__ . '/../views/layout_atas.php';
?>

<?php tampilKilat(); ?>
<?php if ($peng['mode'] !== 'aktif'): ?>
  <div class="alert alert-error">Modul izin <?php echo $peng['mode'] === 'pemeliharaan' ? 'sedang dalam pemeliharaan' : 'belum diaktifkan'; ?>. Hubungi pejabat secara langsung.</div>
<?php endif; ?>

<div class="toolbar">
  <div>
    <?php if ($jumlahTugas > 0): ?>
      <a class="btn btn-secondary" href="tugas.php">Permohonan menunggu keputusan Anda (<?php echo $jumlahTugas; ?>)</a>
    <?php endif; ?>
  </div>
  <?php if ($peng['mode'] === 'aktif'): ?>
    <a class="btn btn-primary" href="form.php">Ajukan izin</a>
  <?php endif; ?>
</div>

<?php if ($notifikasi): ?>
<div class="form-card" style="max-width:980px; margin-bottom:20px;">
  <h2 style="font-family:var(--display); font-size:1rem; margin-bottom:12px;">Pemberitahuan baru</h2>
  <ul style="list-style:none; padding:0; margin:0 0 12px;">
    <?php foreach ($notifikasi as $n): ?>
      <li style="padding:6px 0;"><a href="detail.php?id=<?php echo (int) $n['permohonan_id']; ?>"><?php echo h($n['pesan']); ?></a>
        <span class="note"> · <?php echo h(waktuKantor($n['dibuat_at'], $peng['zona_waktu'])); ?></span></li>
    <?php endforeach; ?>
  </ul>
  <form method="post" action="aksi.php">
    <?php echo Csrf::field(); ?>
    <input type="hidden" name="aksi" value="baca_notifikasi">
    <button type="submit" class="btn btn-secondary">Tandai semua dibaca</button>
  </form>
</div>
<?php endif; ?>

<div class="table-wrap" style="max-width:980px;">
  <table>
    <thead><tr><th>Tanggal</th><th>Jam</th><th>Jenis</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if (!$milikSaya): ?>
      <tr><td colspan="5" class="note">Belum ada permohonan izin.</td></tr>
    <?php endif; ?>
    <?php foreach ($milikSaya as $p): ?>
      <tr>
        <td><?php echo h(tanggalIzin($p)); ?></td>
        <td class="tnum"><?php echo h(jamIzin($p)); ?></td>
        <td><?php echo h(ucfirst(Permohonan::LABEL_JENIS[$p['jenis']])); ?></td>
        <td><?php echo badgeIzin($p['status']); ?></td>
        <td><a class="btn btn-secondary" href="detail.php?id=<?php echo (int) $p['id']; ?>">Lihat</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
