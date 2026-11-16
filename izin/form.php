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

// Pola 3 — Form (halaman): kolom sempit, label di atas isian.
$halamanAktif = 'izin';
$judulHalaman = 'Ajukan Izin';
$breadcrumb = 'Izin';
$subJudul = 'Identitas dan pejabat tujuan diisi otomatis. Pejabat langsung diberi tahu lewat WhatsApp.';
require __DIR__ . '/../views/layout_atas.php';
$tujuan = null;
if ($rute && !isset($rute['error'])) {
    $tujuan = $rute['pemberi'] === 'pta'
        ? array('nama' => Permohonan::PEMBERI_PTA, 'jabatan' => 'Di luar AURA — surat dicetak untuk dikirim')
        : $pejabat[$rute['pemberi']];
}
?>

<div class="iz-page iz-page--sempit">
  <?php if ($galat !== ''): ?><div class="alert alert-error" role="alert"><?php echo h($galat); ?></div><?php endif; ?>
  <?php if ($peng['mode'] !== 'aktif'): ?><div class="alert alert-error" role="alert">Modul izin belum aktif atau sedang pemeliharaan.</div><?php endif; ?>

  <div class="iz-anim">
    <?php if (isset($rute['error'])): ?>
      <div class="alert alert-error" role="alert"><?php echo h($rute['error']); ?></div>
    <?php elseif ($tujuan): ?>
      <div class="iz-rute">
        <span class="iz-inisial" aria-hidden="true"><?php echo h(inisial($tujuan['nama'])); ?></span>
        <div><small>Diajukan kepada</small><b><?php echo h($tujuan['nama']); ?></b> <span class="redup"><?php echo h($tujuan['jabatan']); ?></span>
          <?php if ($rute['mengetahui']): ?><small>Pemberitahuan juga ke <?php echo h($pejabat[$rute['mengetahui']]['nama']); ?> (mengetahui)</small><?php endif; ?></div>
      </div>
    <?php endif; ?>

    <form method="post" action="form.php" id="form-izin" class="iz-panel iz-bagian">
      <?php echo Csrf::field(); ?>
      <input type="hidden" name="asal_permohonan_id" value="<?php echo h($isian['asal_permohonan_id']); ?>">
      <?php if ($pem): ?>
        <p class="redup" style="margin:0 0 16px;">Pemohon: <b style="color:var(--ink)"><?php echo h($pem['nama']); ?></b> · <?php echo h($pem['jabatan']); ?></p>
      <?php endif; ?>

      <fieldset class="iz-pilihan">
        <legend>Jenis izin <span class="req">*</span></legend>
        <label><input type="radio" name="jenis" value="keluar_sementara" <?php echo $isian['jenis'] === 'keluar_sementara' ? 'checked' : ''; ?>><b>Keluar sementara</b><span>Kembali ke kantor di hari yang sama</span></label>
        <label><input type="radio" name="jenis" value="pulang_awal" <?php echo $isian['jenis'] === 'pulang_awal' ? 'checked' : ''; ?>><b>Pulang awal</b><span>Pulang sebelum jam kerja berakhir</span></label>
      </fieldset>

      <label class="iz-saklar" for="mulai-sekarang">
        <span><b>Mulai sekarang</b><span>Jam mulai dicatat saat permohonan dikirim</span></span>
        <input type="checkbox" role="switch" name="mulai_sekarang" value="1" id="mulai-sekarang" <?php echo $isian['mulai_sekarang'] === '1' ? 'checked' : ''; ?>>
      </label>

      <div class="grid-2" id="blok-waktu-manual">
        <div class="field">
          <label for="tanggal">Tanggal <span class="req">*</span></label>
          <input type="date" id="tanggal" name="tanggal" min="<?php echo h($hariIni); ?>" value="<?php echo h($isian['tanggal'] !== '' ? $isian['tanggal'] : $hariIni); ?>">
        </div>
        <div class="field">
          <label for="jam_mulai">Jam mulai <span class="req">*</span></label>
          <input type="time" id="jam_mulai" name="jam_mulai" value="<?php echo h($isian['jam_mulai']); ?>">
        </div>
      </div>

      <div class="field" id="blok-jam-kembali">
        <label for="jam_kembali">Rencana jam kembali <span class="req">*</span></label>
        <input type="time" id="jam_kembali" name="jam_kembali" value="<?php echo h($isian['jam_kembali']); ?>">
      </div>

      <div class="field">
        <label for="alasan">Keperluan <span class="req">*</span></label>
        <textarea id="alasan" name="alasan" rows="3" minlength="10" maxlength="2000" required aria-describedby="alasan-bantu"><?php echo h($isian['alasan']); ?></textarea>
        <span class="redup" id="alasan-bantu">Hanya terlihat oleh Anda, pejabat terkait, dan admin. Tidak dikirim lewat WhatsApp.</span>
      </div>

      <div class="iz-tombol-bawah">
        <a class="btn btn-secondary" href="index.php">Batal</a>
        <button type="submit" class="btn btn-primary" <?php echo $bisaKirim ? '' : 'disabled'; ?>>Kirim permohonan</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
