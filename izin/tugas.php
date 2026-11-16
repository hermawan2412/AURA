<?php
// Pola 1 — List / Index: permohonan yang menunggu keputusan pengguna ini.

require __DIR__ . '/_umum.php';

use Aurat\Izin\Permohonan;

$s = $db->prepare(
    'SELECT p.* FROM izin_tahap t JOIN izin_permohonan p ON p.id = t.permohonan_id
     WHERE t.pemberi_user_id = ? AND t.status = \'menunggu\' ORDER BY p.tanggal, p.jam_mulai'
);
$s->execute(array($userId));
$tugas = $s->fetchAll();

$halamanAktif = 'izin_tugas';
$judulHalaman = 'Menunggu Keputusan';
$breadcrumb = 'Izin';
$subJudul = 'Permohonan izin yang perlu Anda setujui atau tolak, urut dari yang paling dekat waktunya.';
require __DIR__ . '/../views/layout_atas.php';
?>

<div class="iz-page">
  <?php tampilKilat(); ?>
  <div class="iz-panel">
    <div class="iz-alat">
      <div class="iz-cari">
        <label for="cari">Cari</label>
        <input id="cari" type="search" placeholder="Nama pemohon atau tanggal — tekan /" data-cari-tabel="tabel-tugas" data-cari-info="info-tugas" autocomplete="off">
      </div>
    </div>
    <div class="iz-gulir">
      <table class="iz-tabel" id="tabel-tugas">
        <thead><tr><th>Pemohon</th><th>Tanggal</th><th>Jam</th><th>Jenis</th><th class="iz-panah"><span class="visually-hidden">Buka</span></th></tr></thead>
        <tbody>
        <?php if (!$tugas): ?>
          <tr><td colspan="5"><div class="iz-kosong"><b>Tidak ada yang menunggu</b>Permohonan baru akan muncul di sini dan dikabarkan lewat WhatsApp.</div></td></tr>
        <?php endif; ?>
        <?php foreach ($tugas as $i => $p): ?>
          <tr class="iz-baris" data-href="detail.php?id=<?php echo (int) $p['id']; ?>" style="--i:<?php echo $i; ?>">
            <td><a class="iz-utama" href="detail.php?id=<?php echo (int) $p['id']; ?>"><?php echo h($p['pemohon_nama']); ?></a><br><span class="redup"><?php echo h($p['pemohon_jabatan']); ?></span></td>
            <td><?php echo h(tanggalIzin($p)); ?></td>
            <td class="tnum"><?php echo h(jamIzin($p)); ?></td>
            <td><?php echo h(ucfirst(Permohonan::LABEL_JENIS[$p['jenis']])); ?></td>
            <td class="iz-panah"><?php echo ikonIzin('panah', 16); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="iz-kaki"><span id="info-tugas" data-awal="<?php echo count($tugas); ?> menunggu"><?php echo count($tugas); ?> menunggu</span></div>
  </div>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
