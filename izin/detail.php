<?php
// Pola 2 — Detail / Record: satu permohonan izin.

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
$putus = null; // tahap terakhir yang sudah diputus (selesai/ditolak)
foreach ($tahap as $t) {
    if ($t['status'] === 'menunggu') {
        $aktif = $t;
    }
    if (in_array($t['status'], array('selesai', 'ditolak'), true)) {
        $putus = $t;
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
$bolehBatal = $isPemohon && $p['status'] === 'menunggu_persetujuan';
$bolehCabut = $aktifMode && $p['status'] === 'disetujui'
    && $putus && (int) $putus['pemberi_user_id'] === $userId;
$bolehAlih = $aktifMode && $isAdmin && $p['status'] === 'menunggu_persetujuan';
$bolehSalin = $aktifMode && $isPemohon && in_array($p['status'], array('ditolak', 'dibatalkan', 'dicabut'), true);

$wa = array();
if ($isPemohon || $isAdmin) {
    $s = $db->prepare('SELECT o.status, o.error, u.nama_tampilan FROM izin_wa_outbox o JOIN user_login u ON u.id = o.penerima_user_id WHERE o.permohonan_id = ? ORDER BY o.id');
    $s->execute(array($id));
    $wa = $s->fetchAll();
}

$kini = new DateTimeImmutable('now', new DateTimeZone($p['zona_waktu']));
$jamLewat = $p['status'] === 'menunggu_persetujuan'
    && $kini->format('Y-m-d H:i:s') > $p['tanggal'] . ' ' . $p['jam_mulai'];
$namaJenis = ucfirst(Permohonan::LABEL_JENIS[$p['jenis']]);
$LABEL_AKSI = array('ajukan' => 'mengajukan', 'setujui' => 'menyetujui', 'tolak' => 'menolak', 'batalkan' => 'membatalkan',
    'cabut' => 'mencabut', 'alihkan' => 'mengalihkan ke pejabat pengganti');

// Ringkasan status di bagian atas panel.
$zona = $p['zona_waktu'];
switch ($p['status']) {
    case 'disetujui':
        $ringkas = array('ok', 'centang', 'Izin disetujui', 'Oleh ' . ($putus ? $putus['pemberi_nama'] : '-') . ' · ' . waktuKantor($p['diputus_at'], $zona));
        break;
    case 'menunggu_persetujuan':
        $ringkas = array('tunggu', 'jam', 'Menunggu keputusan', $aktif ? $aktif['pemberi_nama'] . ' (' . $aktif['pemberi_jabatan'] . ')' : '');
        break;
    default:
        $ringkas = array('stop', 'silang', Permohonan::LABEL_STATUS[$p['status']], waktuKantor($p['diputus_at'], $zona) . ($p['catatan_keputusan'] ? ' · ' . $p['catatan_keputusan'] : ''));
}

// Tombol utama di kepala halaman (aksi yang paling mungkin dilakukan peran ini).
$tombol = '';
if ($bolehPutus) {
    $tombol .= '<button type="button" class="btn btn-secondary" data-buka="dlg-tolak">Tolak</button>'
        . '<form method="post" action="aksi.php" style="margin:0">' . Csrf::field()
        . '<input type="hidden" name="aksi" value="setujui"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="versi" value="' . (int) $p['versi'] . '">'
        . '<button type="submit" class="btn btn-primary">' . ikonIzin('centang', 18) . 'Setujui izin</button></form>';
}
if ($dok && $dok['status'] === 'siap' && !$bolehPutus) {
    $tombol .= '<a class="btn btn-primary" href="unduh.php?id=' . $id . '">' . ikonIzin('unduh', 18) . 'Unduh surat</a>';
}
if ($bolehSalin) {
    $tombol .= '<a class="btn btn-secondary" href="form.php?salin=' . $id . '">Ajukan ulang</a>';
}

$halamanAktif = $bolehPutus ? 'izin_tugas' : 'izin';
$judulHalaman = $p['pemohon_nama'];
$breadcrumb = 'Izin · #' . $id;
$aksiHalaman = $tombol;
require __DIR__ . '/../views/layout_atas.php';

/** Dialog konfirmasi bernama untuk aksi yang butuh alasan. */
function dialogAksi($idDialog, $aksi, array $p, $judul, $penjelasan, $labelIsian, $labelTombol, $kelasTombol = 'btn-bahaya')
{
    ?>
    <dialog class="iz-dialog" id="<?php echo h($idDialog); ?>" aria-labelledby="<?php echo h($idDialog); ?>-judul">
      <form method="post" action="aksi.php">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="aksi" value="<?php echo h($aksi); ?>">
        <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
        <input type="hidden" name="versi" value="<?php echo (int) $p['versi']; ?>">
        <h2 id="<?php echo h($idDialog); ?>-judul"><?php echo h($judul); ?></h2>
        <p><?php echo h($penjelasan); ?></p>
        <div class="field">
          <label for="<?php echo h($idDialog); ?>-isi"><?php echo h($labelIsian); ?> <span class="req">*</span></label>
          <textarea id="<?php echo h($idDialog); ?>-isi" name="catatan" rows="3" required minlength="3" maxlength="2000"></textarea>
        </div>
        <div class="iz-tombol-bawah">
          <button type="button" class="btn btn-secondary" data-tutup>Batal</button>
          <button type="submit" class="btn <?php echo h($kelasTombol); ?>"><?php echo h($labelTombol); ?></button>
        </div>
      </form>
    </dialog>
    <?php
}
?>

<div class="iz-page">
  <div class="iz-kepala">
    <div class="iz-meta">
      <?php echo badgeIzin($p['status']); ?>
      <span><?php echo h($namaJenis); ?></span>
      <span><?php echo h(tanggalIzin($p)); ?>, <span class="tnum"><?php echo h(jamIzin($p)); ?></span></span>
      <span><?php echo h($p['pemohon_jabatan']); ?></span>
    </div>
  </div>

  <?php tampilKilat(); ?>
  <?php if ($p['status'] === 'dicabut'): ?>
    <div class="alert alert-error" role="alert">Izin ini sudah dicabut. Surat yang pernah diunduh tidak berlaku lagi.</div>
  <?php endif; ?>
  <?php if ($jamLewat): ?>
    <div class="alert alert-error" role="alert">Jam mulai yang dimohon sudah lewat. Keputusan tetap dicatat dengan waktu sebenarnya.</div>
  <?php endif; ?>

  <div class="iz-panel iz-anim">
    <div class="iz-status iz-status--<?php echo $ringkas[0]; ?>">
      <span class="iz-status-ikon"><?php echo ikonIzin($ringkas[1]); ?></span>
      <div><b><?php echo h($ringkas[2]); ?></b><span><?php echo h($ringkas[3]); ?></span></div>
    </div>

    <section class="iz-bagian" aria-labelledby="h-rincian">
      <h2 id="h-rincian">Rincian</h2>
      <dl class="iz-dl">
        <dt>Pemohon</dt><dd><?php echo h($p['pemohon_nama']); ?><br><span class="redup">NIP <?php echo h($p['pemohon_nip']); ?></span></dd>
        <dt>Jenis izin</dt><dd><?php echo h($namaJenis); ?></dd>
        <dt>Waktu</dt><dd><?php echo h(tanggalIzin($p)); ?>, <span class="tnum"><?php echo h(jamIzin($p)); ?></span></dd>
        <dt>Keperluan</dt><dd class="iz-teks"><?php echo nl2br(h($p['alasan'])); ?></dd>
        <dt>Pemberi izin</dt><dd><?php echo h($putus ? $putus['pemberi_nama'] : ($aktif ? $aktif['pemberi_nama'] : '-')); ?></dd>
        <?php if ($p['mengetahui_nama']): ?>
          <dt>Mengetahui</dt><dd><?php echo h($p['mengetahui_nama']); ?> <span class="redup">(pemberitahuan)</span></dd>
        <?php endif; ?>
        <?php if ($p['catatan_keputusan'] && $p['status'] === 'disetujui'): ?>
          <dt>Catatan</dt><dd class="iz-teks"><?php echo nl2br(h($p['catatan_keputusan'])); ?></dd>
        <?php endif; ?>
      </dl>
    </section>

    <section class="iz-bagian" aria-labelledby="h-surat">
      <h2 id="h-surat">Surat</h2>
      <?php if ($dok && $dok['status'] === 'siap'): ?>
        <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
          <div style="display:flex; gap:12px; align-items:center;">
            <span class="iz-status-ikon"><?php echo ikonIzin('dokumen'); ?></span>
            <div>Nomor register <b class="tnum"><?php echo h($dok['nomor']); ?></b><br><span class="redup">PDF · tanda tangan basah pejabat tetap diperlukan</span></div>
          </div>
          <?php if ($bolehPutus): ?><a class="btn btn-secondary" href="unduh.php?id=<?php echo $id; ?>"><?php echo ikonIzin('unduh', 18); ?>Unduh PDF</a><?php endif; ?>
        </div>
      <?php elseif ($dok): ?>
        <p class="redup"><?php echo $dok['status'] === 'gagal' ? 'Surat gagal dibuat. Keputusan tetap tersimpan.' : 'Izin sudah diputus, surat sedang disiapkan.'; ?></p>
        <?php if ($isAdmin): ?>
          <?php if ($dok['error']): ?><p class="redup"><?php echo h($dok['error']); ?></p><?php endif; ?>
          <form method="post" action="aksi.php" style="margin-top:8px;"><?php echo Csrf::field(); ?>
            <input type="hidden" name="aksi" value="buat_ulang_surat"><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="versi" value="<?php echo (int) $p['versi']; ?>">
            <button type="submit" class="btn btn-secondary btn-kecil">Buat ulang surat</button></form>
        <?php endif; ?>
      <?php else: ?>
        <p class="redup"><?php echo $p['status'] === 'disetujui' ? 'Izin disetujui, surat sedang disiapkan.' : 'Surat tersedia setelah izin disetujui.'; ?></p>
      <?php endif; ?>
    </section>

    <section class="iz-bagian" aria-labelledby="h-riwayat">
      <h2 id="h-riwayat">Riwayat</h2>
      <ol class="iz-waktu">
        <?php foreach ($riwayat as $r): ?>
          <li><small><?php echo h(waktuKantor($r['dibuat_at'], $zona)); ?></small>
            <?php echo h($r['aktor_nama']); ?> <span class="redup"><?php echo h(isset($LABEL_AKSI[$r['aksi']]) ? $LABEL_AKSI[$r['aksi']] : str_replace('_', ' ', $r['aksi'])); ?></span>
            <?php if ($r['alasan']): ?><br><span class="redup"><?php echo h($r['alasan']); ?></span><?php endif; ?></li>
        <?php endforeach; ?>
        <?php if ($aktif): ?>
          <li class="iz-kini"><small>Sekarang</small>Menunggu keputusan <?php echo h($aktif['pemberi_nama']); ?></li>
        <?php endif; ?>
      </ol>
    </section>

    <?php if ($wa): ?>
    <section class="iz-bagian" aria-labelledby="h-wa">
      <h2 id="h-wa">Pemberitahuan WhatsApp</h2>
      <?php $labelWa = array('antre' => 'Dalam antrean', 'terkirim' => 'Diterima Fonnte', 'gagal' => 'Gagal', 'dibatalkan' => 'Tidak dikirim (tidak relevan lagi)'); ?>
      <ul class="iz-wa">
        <?php foreach ($wa as $w): ?>
          <li><span><?php echo h($w['nama_tampilan']); ?></span><span class="redup"><?php echo h($labelWa[$w['status']]); ?><?php echo $w['status'] === 'gagal' && $w['error'] ? ' — ' . h($w['error']) : ''; ?></span></li>
        <?php endforeach; ?>
      </ul>
      <p class="redup" style="margin-top:12px;">Bila WhatsApp gagal atau sangat mendesak, hubungi pejabat langsung. Keputusan tetap dicatat di AURA.</p>
    </section>
    <?php endif; ?>

    <?php if ($bolehBatal || $bolehCabut || $bolehAlih): ?>
    <section class="iz-bagian" aria-labelledby="h-lain">
      <h2 id="h-lain">Tindakan lain</h2>
      <div class="iz-aksi">
        <?php if ($bolehAlih): ?><button type="button" class="btn btn-secondary btn-kecil" data-buka="dlg-alih">Alihkan ke pejabat pengganti</button><?php endif; ?>
        <?php if ($bolehBatal): ?><button type="button" class="btn btn-secondary btn-kecil" data-buka="dlg-batal">Batalkan permohonan</button><?php endif; ?>
        <?php if ($bolehCabut): ?><button type="button" class="btn btn-secondary btn-kecil" data-buka="dlg-cabut">Cabut izin</button><?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</div>

<?php
$siapa = $p['pemohon_nama'];
if ($bolehPutus) {
    dialogAksi('dlg-tolak', 'tolak', $p, 'Tolak izin ' . $siapa . '?', 'Pemohon akan diberi tahu beserta alasan ini.', 'Alasan penolakan', 'Tolak izin');
}
if ($bolehBatal) {
    dialogAksi('dlg-batal', 'batalkan', $p, 'Batalkan permohonan ini?', 'Pejabat yang sedang memproses akan diberi tahu.', 'Alasan pembatalan', 'Batalkan permohonan');
}
if ($bolehCabut) {
    dialogAksi('dlg-cabut', 'cabut', $p, 'Cabut izin ' . $siapa . '?', 'Izin tidak berlaku lagi dan hilang dari kotak hijau portal. Pemohon diberi tahu.', 'Alasan pencabutan', 'Cabut izin');
}
if ($bolehAlih) {
    dialogAksi('dlg-alih', 'alihkan', $p, 'Alihkan permohonan ' . $siapa . '?', 'Rute dihitung ulang dari data pejabat dan pejabat berhalangan saat ini. Pejabat lama kehilangan hak memutus.', 'Alasan pengalihan', 'Alihkan', 'btn-primary');
}
require __DIR__ . '/../views/layout_bawah.php';
