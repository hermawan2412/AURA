<?php

require __DIR__ . '/_umum.php';

use Aurat\Izin\Permohonan;

$s = $db->prepare(
    'SELECT p.* FROM izin_tahap t JOIN izin_permohonan p ON p.id = t.permohonan_id
     WHERE t.pemberi_user_id = ? AND t.status = \'menunggu\' ORDER BY p.tanggal, p.jam_mulai'
);
$s->execute(array($userId));
$tugas = $s->fetchAll();

$halamanAktif = 'izin_tugas';
$judulHalaman = 'Permohonan Menunggu Keputusan';
$breadcrumb = 'Izin';
require __DIR__ . '/../views/layout_atas.php';
?>

<?php tampilKilat(); ?>
<div class="table-wrap" style="max-width:980px;">
  <table>
    <thead><tr><th>Pemohon</th><th>Tanggal</th><th>Jam</th><th>Jenis</th><th></th></tr></thead>
    <tbody>
    <?php if (!$tugas): ?>
      <tr><td colspan="5" class="redup">Tidak ada permohonan yang menunggu keputusan Anda.</td></tr>
    <?php endif; ?>
    <?php foreach ($tugas as $p): ?>
      <tr>
        <td><?php echo h($p['pemohon_nama']); ?><br><span class="redup"><?php echo h($p['pemohon_jabatan']); ?></span></td>
        <td><?php echo h(tanggalIzin($p)); ?></td>
        <td class="tnum"><?php echo h(jamIzin($p)); ?></td>
        <td><?php echo h(ucfirst(Permohonan::LABEL_JENIS[$p['jenis']])); ?></td>
        <td><a class="btn btn-primary" href="detail.php?id=<?php echo (int) $p['id']; ?>">Berikan izin</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
