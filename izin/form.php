<?php

require __DIR__ . '/_umum.php';

use Aurat\Csrf;
use Aurat\Izin\Dokumen;
use Aurat\Izin\IzinException;
use Aurat\Izin\Permohonan;
use Aurat\Izin\Routing;

$galat = '';
$isian = array('jenis' => 'keluar_sementara', 'mulai_sekarang' => '1', 'tanggal' => '', 'jam_mulai' => '', 'jam_kembali' => '', 'alasan' => '', 'asal_permohonan_id' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    foreach ($isian as $k => $v) {
        $isian[$k] = isset($_POST[$k]) ? (string) $_POST[$k] : '';
    }
    try {
        $id = Permohonan::ajukan($db, $userId, array(
            'jenis' => $isian['jenis'],
            'mulai_sekarang' => $isian['mulai_sekarang'] === '1',
            'tanggal' => $isian['tanggal'],
            'jam_mulai' => $isian['jam_mulai'],
            'jam_kembali' => $isian['jam_kembali'],
            'alasan' => $isian['alasan'],
            'asal_permohonan_id' => $isian['asal_permohonan_id'],
        ), sekarangUtc());
        if (Permohonan::muat($db, $id)['status'] === 'diajukan_ke_pta') {
            Dokumen::terbitkan($db, $id, sekarangUtc()); // surat ke KPTA dibutuhkan saat itu juga
        }
        kilat('Permohonan terkirim. Pejabat sudah diberi tahu.');
        header('Location: detail.php?id=' . $id);
        exit;
    } catch (IzinException $e) {
        $galat = $e->getMessage();
    }
} elseif (isset($_GET['salin'])) {
    $asal = Permohonan::muat($db, (int) $_GET['salin']);
    if ($asal && (int) $asal['pemohon_user_id'] === $userId) {
        $isian['jenis'] = $asal['jenis'];
        $isian['alasan'] = $asal['alasan'];
        $isian['asal_permohonan_id'] = (string) $asal['id'];
    }
}

// Pratinjau rute dari server - pemohon tidak bisa memilih pejabat.
$rute = null;
$pejabat = array();
try {
    $pem = Permohonan::identitas($db, $userId);
    $pejabat = Permohonan::pejabat($db);
    $rute = Routing::tentukan((string) $pem['kelompok'], $pejabat, Permohonan::berhalangan($db, sekarangUtc()), $pem['pegawai_id']);
} catch (IzinException $e) {
    $pem = null;
    $rute = array('error' => $e->getMessage());
}
$hariIni = (new DateTimeImmutable('now', new DateTimeZone($peng['zona_waktu'])))->format('Y-m-d');
$bisaKirim = $peng['mode'] === 'aktif' && $rute !== null && !isset($rute['error']);

$halamanAktif = 'izin';
$judulHalaman = 'Ajukan Izin';
$breadcrumb = 'Izin';
require __DIR__ . '/../views/layout_atas.php';
?>

<?php if ($galat !== ''): ?><div class="alert alert-error" role="alert"><?php echo h($galat); ?></div><?php endif; ?>
<?php if ($peng['mode'] !== 'aktif'): ?><div class="alert alert-error">Modul izin belum aktif atau sedang pemeliharaan.</div><?php endif; ?>

<div class="form-card" style="max-width:560px;">
  <?php if ($pem): ?>
    <p class="note" style="margin-bottom:12px;"><b><?php echo h($pem['nama']); ?></b> · NIP <?php echo h($pem['nip']); ?><br><?php echo h($pem['jabatan']); ?></p>
  <?php endif; ?>

  <?php if (isset($rute['error'])): ?>
    <div class="alert alert-error"><?php echo h($rute['error']); ?></div>
  <?php elseif ($rute): ?>
    <div class="alert alert-info">
      Diajukan kepada: <b><?php echo $rute['pemberi'] === 'pta' ? h(Permohonan::PEMBERI_PTA) . '</b> (di luar AURA; surat dicetak untuk dikirim)' : h($pejabat[$rute['pemberi']]['nama']) . '</b> (' . h($pejabat[$rute['pemberi']]['jabatan']) . ')'; ?>
      <?php if ($rute['mengetahui']): ?><br>Pemberitahuan kepada: <?php echo h($pejabat[$rute['mengetahui']]['nama']); ?> (mengetahui)<?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="form.php" id="form-izin">
    <?php echo Csrf::field(); ?>
    <input type="hidden" name="asal_permohonan_id" value="<?php echo h($isian['asal_permohonan_id']); ?>">

    <fieldset class="field" style="border:0; padding:0;">
      <legend>Jenis izin <span class="req">*</span></legend>
      <label><input type="radio" name="jenis" value="keluar_sementara" <?php echo $isian['jenis'] === 'keluar_sementara' ? 'checked' : ''; ?>> Keluar sementara (kembali ke kantor)</label><br>
      <label><input type="radio" name="jenis" value="pulang_awal" <?php echo $isian['jenis'] === 'pulang_awal' ? 'checked' : ''; ?>> Pulang sebelum waktunya</label>
    </fieldset>

    <div class="field">
      <label><input type="checkbox" name="mulai_sekarang" value="1" id="mulai-sekarang" <?php echo $isian['mulai_sekarang'] === '1' ? 'checked' : ''; ?>> Mulai sekarang</label>
      <p class="note">Jam mulai dicatat saat permohonan dikirim.</p>
    </div>

    <div class="grid-2" id="blok-waktu-manual">
      <div class="field">
        <label for="tanggal">Tanggal</label>
        <input type="date" id="tanggal" name="tanggal" min="<?php echo h($hariIni); ?>" value="<?php echo h($isian['tanggal'] !== '' ? $isian['tanggal'] : $hariIni); ?>">
      </div>
      <div class="field">
        <label for="jam_mulai">Jam mulai</label>
        <input type="time" id="jam_mulai" name="jam_mulai" value="<?php echo h($isian['jam_mulai']); ?>">
      </div>
    </div>

    <div class="field" id="blok-jam-kembali">
      <label for="jam_kembali">Rencana jam kembali <span class="req">*</span></label>
      <input type="time" id="jam_kembali" name="jam_kembali" value="<?php echo h($isian['jam_kembali']); ?>">
    </div>

    <div class="field">
      <label for="alasan">Keperluan <span class="req">*</span></label>
      <textarea id="alasan" name="alasan" rows="3" minlength="10" maxlength="2000" required><?php echo h($isian['alasan']); ?></textarea>
      <p class="note">Hanya terlihat oleh Anda, pejabat terkait, dan admin. Tidak dikirim lewat WhatsApp.</p>
    </div>

    <button type="submit" class="btn btn-primary btn-block" <?php echo $bisaKirim ? '' : 'disabled'; ?>>Kirim permohonan</button>
  </form>
</div>

<script>
(function () {
  var f = document.getElementById('form-izin');
  function atur() {
    var sekarang = document.getElementById('mulai-sekarang').checked;
    var keluar = f.querySelector('input[name=jenis]:checked').value === 'keluar_sementara';
    document.getElementById('blok-waktu-manual').hidden = sekarang;
    document.getElementById('blok-jam-kembali').hidden = !keluar;
    document.getElementById('jam_kembali').required = keluar;
    document.getElementById('jam_mulai').required = !sekarang;
  }
  f.addEventListener('change', atur);
  atur();
})();
</script>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
