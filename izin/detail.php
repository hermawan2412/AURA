<?php

require __DIR__ . '/_umum.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Izin\Dokumen;
use Aurat\Izin\Permohonan;

$p = Permohonan::muat($db, isset($_GET['id']) ? (int) $_GET['id'] : 0);
if (!$p || !Permohonan::bolehLihat($db, $p, $userId, Auth::isAdmin())) {
    http_response_code(404);
    exit('Permohonan tidak ditemukan.');
}
$id = (int) $p['id'];
$tahap = Permohonan::tahap($db, $id);
$aktif = null;
$pemberiTerakhir = null;
foreach ($tahap as $t) {
    if ($t['status'] === 'menunggu') {
        $aktif = $t;
    }
    if ($t['status'] === 'selesai') {
        $pemberiTerakhir = (int) $t['pemberi_user_id'];
    }
}
$dok = Dokumen::muat($db, $id);
$s = $db->prepare('SELECT * FROM izin_audit WHERE permohonan_id = ? ORDER BY id');
$s->execute(array($id));
$riwayat = $s->fetchAll();

$isPemohon = (int) $p['pemohon_user_id'] === $userId;
$isAdmin = Auth::isAdmin();
$aktifMode = $peng['mode'] === 'aktif';
$bolehPutus = $aktifMode && $aktif && (int) $aktif['pemberi_user_id'] === $userId;
$bolehBatal = $isPemohon && in_array($p['status'], array('menunggu_persetujuan', 'diajukan_ke_pta'), true);
$bolehPta = $aktifMode && $isPemohon && $p['status'] === 'diajukan_ke_pta';
$bolehCabut = $aktifMode && $p['status'] === 'disetujui'
    && ($p['pemberi_eksternal'] !== null ? $isPemohon : $pemberiTerakhir === $userId);
$bolehAlih = $aktifMode && $isAdmin && $p['status'] === 'menunggu_persetujuan';

$wa = array();
if ($isPemohon || $isAdmin) {
    $s = $db->prepare('SELECT o.status, o.error, o.peristiwa, u.nama_tampilan FROM izin_wa_outbox o JOIN user_login u ON u.id = o.penerima_user_id WHERE o.permohonan_id = ? ORDER BY o.id');
    $s->execute(array($id));
    $wa = $s->fetchAll();
}

$kini = new DateTimeImmutable('now', new DateTimeZone($p['zona_waktu']));
$jamLewat = in_array($p['status'], array('menunggu_persetujuan', 'diajukan_ke_pta'), true)
    && $kini->format('Y-m-d H:i:s') > $p['tanggal'] . ' ' . $p['jam_mulai'];

$halamanAktif = $bolehPutus ? 'izin_tugas' : 'izin';
$judulHalaman = 'Izin ' . Permohonan::LABEL_JENIS[$p['jenis']];
$breadcrumb = 'Izin · #' . $id;
require __DIR__ . '/../views/layout_atas.php';

function formAksi($aksi, $p, $label, $kelas, $isiTambahan = '')
{
    echo '<form method="post" action="aksi.php" style="margin-top:12px;">' . Csrf::field()
        . '<input type="hidden" name="aksi" value="' . h($aksi) . '">'
        . '<input type="hidden" name="id" value="' . (int) $p['id'] . '">'
        . '<input type="hidden" name="versi" value="' . (int) $p['versi'] . '">'
        . $isiTambahan
        . '<button type="submit" class="btn ' . $kelas . '">' . h($label) . '</button></form>';
}
function isianAlasan($nama, $label, $wajib)
{
    return '<div class="field"><label for="' . h($nama) . '">' . h($label) . ($wajib ? ' <span class="req">*</span>' : '') . '</label>'
        . '<textarea id="' . h($nama) . '" name="catatan" rows="2" maxlength="2000"' . ($wajib ? ' required minlength="3"' : '') . '></textarea></div>';
}
?>

<?php tampilKilat(); ?>
<?php if ($p['status'] === 'dicabut'): ?>
  <div class="alert alert-error">Izin ini sudah DICABUT. Surat yang pernah diunduh tidak berlaku.</div>
<?php endif; ?>
<?php if ($jamLewat): ?>
  <div class="alert alert-error">Jam mulai yang dimohon telah lewat. Keputusan tetap dicatat dengan waktu sebenarnya.</div>
<?php endif; ?>

<div class="grid-2" style="max-width:980px; align-items:start;">
  <div class="form-card">
    <p style="margin-bottom:8px;"><?php echo badgeIzin($p['status']); ?></p>
    <p><b><?php echo h($p['pemohon_nama']); ?></b><br><span class="redup"><?php echo h($p['pemohon_jabatan']); ?></span></p>
    <p style="margin-top:10px;"><?php echo h(tanggalIzin($p)); ?>, <span class="tnum"><?php echo h(jamIzin($p)); ?></span></p>
    <p style="margin-top:10px;"><span class="redup">Keperluan</span><br><?php echo nl2br(h($p['alasan'])); ?></p>
    <?php if ($p['catatan_keputusan']): ?>
      <p style="margin-top:10px;"><span class="redup">Catatan keputusan</span><br><?php echo nl2br(h($p['catatan_keputusan'])); ?></p>
    <?php endif; ?>
    <?php if ($p['mengetahui_nama']): ?>
      <p class="redup" style="margin-top:10px;">Mengetahui (pemberitahuan): <?php echo h($p['mengetahui_nama']); ?></p>
    <?php endif; ?>
    <?php if ($p['pemberi_eksternal']): ?>
      <p class="redup" style="margin-top:10px;">Pemberi izin: <?php echo h($p['pemberi_eksternal']); ?> (di luar AURA). Hasil dicatat oleh pemohon.</p>
    <?php endif; ?>

    <?php if ($bolehPutus): ?>
      <?php formAksi('setujui', $p, 'Setujui', 'btn-primary btn-block'); ?>
      <details style="margin-top:12px;"><summary>Tolak permohonan</summary>
        <?php formAksi('tolak', $p, 'Tolak', 'btn-secondary', isianAlasan('alasan-tolak', 'Alasan penolakan', true)); ?>
      </details>
    <?php endif; ?>
    <?php if ($bolehPta): ?>
      <details open style="margin-top:12px;"><summary>Catat jawaban KPTA</summary>
        <?php formAksi('pta_setuju', $p, 'KPTA menyetujui', 'btn-primary', isianAlasan('catatan-pta-ok', 'Catatan (mis. nomor/tanggal jawaban)', true)); ?>
        <?php formAksi('pta_tolak', $p, 'KPTA menolak', 'btn-secondary', isianAlasan('catatan-pta-no', 'Catatan', true)); ?>
      </details>
    <?php endif; ?>
    <?php if ($bolehBatal): ?>
      <details style="margin-top:12px;"><summary>Batalkan permohonan</summary>
        <?php formAksi('batalkan', $p, 'Batalkan', 'btn-secondary', isianAlasan('alasan-batal', 'Alasan pembatalan', true)); ?>
      </details>
    <?php endif; ?>
    <?php if ($bolehCabut): ?>
      <details style="margin-top:12px;"><summary>Cabut izin</summary>
        <?php formAksi('cabut', $p, 'Cabut izin', 'btn-secondary', isianAlasan('alasan-cabut', 'Alasan pencabutan', true)); ?>
      </details>
    <?php endif; ?>
    <?php if ($bolehAlih): ?>
      <details style="margin-top:12px;"><summary>Alihkan ke pejabat pengganti (admin)</summary>
        <p class="redup">Rute dihitung ulang dari data pejabat & berhalangan saat ini.</p>
        <?php formAksi('alihkan', $p, 'Alihkan', 'btn-secondary', isianAlasan('alasan-alih', 'Alasan pengalihan', true)); ?>
      </details>
    <?php endif; ?>
    <?php if ($isPemohon && in_array($p['status'], array('ditolak', 'dibatalkan', 'dicabut'), true) && $aktifMode): ?>
      <a class="btn btn-secondary" style="margin-top:12px;" href="form.php?salin=<?php echo $id; ?>">Salin jadi permohonan baru</a>
    <?php endif; ?>
  </div>

  <div>
    <div class="form-card">
      <h2 style="font-family:var(--display); font-size:1rem; margin-bottom:10px;">Surat</h2>
      <?php if (!$dok): ?>
        <p class="redup"><?php echo $p['status'] === 'disetujui' ? 'Izin disetujui, surat sedang disiapkan.' : 'Surat tersedia setelah izin disetujui.'; ?></p>
      <?php elseif ($dok['status'] === 'siap'): ?>
        <p>Nomor register <?php echo h($dok['nomor']); ?></p>
        <a class="btn btn-primary" style="margin-top:8px;" href="unduh.php?id=<?php echo $id; ?>">Unduh PDF</a>
      <?php else: ?>
        <p class="redup">Izin <?php echo h(strtolower(Permohonan::LABEL_STATUS[$p['status']])); ?>, surat <?php echo $dok['status'] === 'gagal' ? 'gagal dibuat' : 'sedang disiapkan'; ?>.</p>
        <?php if ($isAdmin): ?>
          <?php if ($dok['error']): ?><p class="redup"><?php echo h($dok['error']); ?></p><?php endif; ?>
          <?php formAksi('buat_ulang_surat', $p, 'Buat ulang surat', 'btn-secondary'); ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="form-card" style="margin-top:16px;">
      <h2 style="font-family:var(--display); font-size:1rem; margin-bottom:10px;">Riwayat</h2>
      <ul style="list-style:none; padding:0; margin:0;">
        <?php foreach ($riwayat as $r): ?>
          <li style="padding:5px 0; border-bottom:1px solid var(--border);">
            <span class="redup"><?php echo h(waktuKantor($r['dibuat_at'], $p['zona_waktu'])); ?></span><br>
            <?php echo h($r['aktor_nama']); ?> — <?php echo h(str_replace('_', ' ', $r['aksi'])); ?>
            <?php if ($r['alasan']): ?><br><span class="redup"><?php echo h($r['alasan']); ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
        <?php if ($aktif): ?>
          <li style="padding:5px 0;">Menunggu keputusan <?php echo h($aktif['pemberi_nama']); ?></li>
        <?php endif; ?>
      </ul>
    </div>

    <?php if ($wa): ?>
    <div class="form-card" style="margin-top:16px;">
      <h2 style="font-family:var(--display); font-size:1rem; margin-bottom:10px;">WhatsApp</h2>
      <ul style="list-style:none; padding:0; margin:0;">
        <?php $labelWa = array('antre' => 'dalam antrean', 'terkirim' => 'diterima Fonnte', 'gagal' => 'gagal', 'dibatalkan' => 'dibatalkan (tidak relevan lagi)'); ?>
        <?php foreach ($wa as $w): ?>
          <li class="redup" style="padding:3px 0;"><?php echo h($w['nama_tampilan']); ?>: <?php echo h($labelWa[$w['status']]); ?><?php echo $w['status'] === 'gagal' && $w['error'] ? ' — ' . h($w['error']) : ''; ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="redup" style="margin-top:8px;">Bila WA gagal atau mendesak, hubungi pejabat langsung. Keputusan tetap dicatat di AURA.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
