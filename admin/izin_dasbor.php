<?php
// Pola 4 — Dashboard / Overview: sekilas kondisi modul izin. Hanya baca;
// semua tindakan berupa tautan ke detail/pengaturan/register.

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Database;
use Aurat\Formatter;
use Aurat\Izin\Dokumen;
use Aurat\Izin\Permohonan;

Auth::requireAdmin();
$db = Database::pdo();
$peng = Permohonan::pengaturan($db);
$tz = new DateTimeZone($peng['zona_waktu']);
$utc = new DateTimeZone('UTC');
$kini = new DateTimeImmutable('now', $utc);
$lokal = $kini->setTimezone($tz);
$hariIni = $lokal->format('Y-m-d');
$awalBulan = $lokal->format('Y-m-01');
$q = function ($sql, array $arg = array()) use ($db) {
    $s = $db->prepare($sql);
    $s->execute($arg);
    return $s;
};
function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
$jam = function ($t) {
    return str_replace(':', '.', substr((string) $t, 0, 5));
};
$sejak = function ($utcTeks) use ($kini) {
    $m = (int) floor(($kini->getTimestamp() - strtotime($utcTeks . ' UTC')) / 60);
    return $m < 60 ? $m . ' menit' : floor($m / 60) . ' jam ' . ($m % 60) . ' menit';
};

// ---------- Hari ini ----------
$izinHariIni = $q("SELECT id, pemohon_nama, jenis, jam_mulai, jam_kembali FROM izin_permohonan WHERE tanggal = ? AND status = 'disetujui' ORDER BY jam_mulai", array($hariIni))->fetchAll();
$jamKini = $lokal->format('H:i:s');
$sedangIzin = count(array_filter($izinHariIni, function ($p) use ($jamKini) {
    return $p['jam_mulai'] <= $jamKini && ($p['jam_kembali'] === null || $p['jam_kembali'] > $jamKini);
}));

// ---------- Perlu tindakan ----------
$menunggu = $q("SELECT p.id, p.pemohon_nama, p.status, p.diajukan_at, t.pemberi_nama FROM izin_permohonan p
                LEFT JOIN izin_tahap t ON t.permohonan_id = p.id AND t.status = 'menunggu'
                WHERE p.status IN ('menunggu_persetujuan','diajukan_ke_pta') ORDER BY p.diajukan_at")->fetchAll();
$batasLama = Permohonan::utc($kini->modify('-30 minutes'));
$lama = array_values(array_filter($menunggu, function ($p) use ($batasLama) {
    return $p['status'] === 'menunggu_persetujuan' && $p['diajukan_at'] < $batasLama;
}));
$waGagal = $q("SELECT o.permohonan_id, o.error, u.nama_tampilan FROM izin_wa_outbox o JOIN user_login u ON u.id = o.penerima_user_id
               WHERE o.status = 'gagal' AND o.dibuat_at >= ? ORDER BY o.id DESC LIMIT 10", array(Permohonan::utc($kini->modify('-7 days'))))->fetchAll();
$suratGagal = $q("SELECT d.permohonan_id, d.status, d.error, p.pemohon_nama FROM izin_dokumen d JOIN izin_permohonan p ON p.id = d.permohonan_id
                  WHERE d.status = 'gagal' OR (d.status = 'diproses' AND d.dibuat_at < ?)", array(Permohonan::utc($kini->modify('-5 minutes'))))->fetchAll();
$halangan = $q("SELECT p.id, p.pemohon_nama, t.pemberi_nama FROM izin_tahap t JOIN izin_permohonan p ON p.id = t.permohonan_id
                WHERE t.status = 'menunggu' AND t.pemberi_pegawai_id IN (
                  SELECT pegawai_id FROM izin_berhalangan WHERE dibatalkan_at IS NULL AND mulai <= ? AND selesai > ?)",
    array(Permohonan::utc($kini), Permohonan::utc($kini)))->fetchAll();
$tanpaKelompok = (int) $q("SELECT COUNT(*) FROM pegawai p JOIN user_login u ON u.nip = p.nip AND u.status_aktif = 1
                           LEFT JOIN izin_pemetaan_pegawai m ON m.pegawai_id = p.id WHERE p.status_aktif = 1 AND m.pegawai_id IS NULL")->fetchColumn();
$tanpaNomor = (int) $q("SELECT COUNT(*) FROM izin_pemetaan_pegawai m JOIN pegawai p ON p.id = m.pegawai_id
                        WHERE p.status_aktif = 1 AND (p.no_telp IS NULL OR p.no_telp = '')")->fetchColumn();
$jumlahMasalah = count($lama) + count($waGagal) + count($suratGagal) + count($halangan) + ($tanpaKelompok ? 1 : 0) + ($tanpaNomor ? 1 : 0);

// ---------- Bulan ini ----------
$perStatus = $q("SELECT status, COUNT(*) FROM izin_permohonan WHERE tanggal BETWEEN ? AND ? GROUP BY status", array($awalBulan, $lokal->format('Y-m-t')))->fetchAll(PDO::FETCH_KEY_PAIR);
$perJenis = $q("SELECT jenis, COUNT(*) FROM izin_permohonan WHERE tanggal BETWEEN ? AND ? GROUP BY jenis", array($awalBulan, $lokal->format('Y-m-t')))->fetchAll(PDO::FETCH_KEY_PAIR);
$totalBulan = array_sum($perStatus);
// Rata-rata hanya untuk yang benar-benar diputus pejabat AURA (bukan PTA, bukan yang masih menunggu).
$rata = $q("SELECT AVG(TIMESTAMPDIFF(MINUTE, diajukan_at, diputus_at)), COUNT(*) FROM izin_permohonan
            WHERE tanggal BETWEEN ? AND ? AND status IN ('disetujui','ditolak','dicabut') AND pemberi_eksternal IS NULL AND diputus_at IS NOT NULL",
    array($awalBulan, $lokal->format('Y-m-t')))->fetch(PDO::FETCH_NUM);

// ---------- Grafik: izin disetujui per hari kerja, 30 hari terakhir ----------
$mulaiGrafik = $lokal->modify('-29 days')->format('Y-m-d');
$harian = $q("SELECT tanggal, COUNT(*) FROM izin_permohonan WHERE status = 'disetujui' AND tanggal BETWEEN ? AND ? GROUP BY tanggal",
    array($mulaiGrafik, $hariIni))->fetchAll(PDO::FETCH_KEY_PAIR);
$hari = array();
for ($d = new DateTimeImmutable($mulaiGrafik, $tz); $d->format('Y-m-d') <= $hariIni; $d = $d->modify('+1 day')) {
    $hari[] = array('t' => $d->format('Y-m-d'), 'n' => isset($harian[$d->format('Y-m-d')]) ? (int) $harian[$d->format('Y-m-d')] : 0, 'libur' => (int) $d->format('N') >= 6);
}
$maks = max(1, max(array_column($hari, 'n')));

// ---------- Kesehatan sistem ----------
$antre = $q("SELECT COUNT(*), MIN(dibuat_at) FROM izin_wa_outbox WHERE status = 'antre'")->fetch(PDO::FETCH_NUM);
$detak = @filemtime(Dokumen::direktori() . '/.cron-wa');
$cronSehat = $detak && $kini->getTimestamp() - $detak < 180;

$LABEL_MODE = array('nonaktif' => 'Nonaktif', 'aktif' => 'Aktif', 'pemeliharaan' => 'Pemeliharaan');

$halamanAktif = 'admin_izin_dasbor';
$judulHalaman = 'Dasbor Izin';
$breadcrumb = 'Izin';
$subJudul = 'Sekilas kondisi modul izin: siapa sedang izin, apa yang perlu ditindaklanjuti, dan rekap bulan berjalan.';
$rootAsset = '../';
$cssTambahan = 'assets/css/izin.css';
$jsTambahan = 'assets/js/izin.js';
require __DIR__ . '/../views/layout_atas.php';

function ikonStatus($baik)
{
    return $baik
        ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>'
        : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 7v6M12 17h.01"/></svg>';
}
?>

<div class="iz-page iz-dasbor">
  <?php if ($peng['mode'] !== 'aktif'): ?>
    <div class="alert alert-error" role="alert">Modul izin dalam mode <b><?php echo h($LABEL_MODE[$peng['mode']]); ?></b> — pegawai belum bisa mengajukan izin. <a href="izin_pengaturan.php#umum">Buka pengaturan</a></div>
  <?php endif; ?>

  <!-- KPI (maks. 5) -->
  <div class="iz-kpi iz-anim">
    <div class="iz-kpi-ubin"><span>Sedang izin sekarang</span><b class="tnum"><?php echo $sedangIzin; ?></b><small><?php echo count($izinHariIni); ?> izin disetujui hari ini</small></div>
    <a class="iz-kpi-ubin" href="izin_register.php?status=menunggu_persetujuan&amp;dari=<?php echo h($mulaiGrafik); ?>&amp;sampai=<?php echo h($lokal->modify('+30 days')->format('Y-m-d')); ?>"><span>Menunggu keputusan</span><b class="tnum"><?php echo count($menunggu); ?></b><small><?php echo count($lama); ?> lebih dari 30 menit</small></a>
    <div class="iz-kpi-ubin <?php echo $jumlahMasalah ? 'iz-kpi--awas' : ''; ?>"><span>Perlu tindakan</span><b class="tnum"><?php echo $jumlahMasalah; ?></b><small><?php echo $jumlahMasalah ? 'lihat daftar di bawah' : 'tidak ada masalah'; ?></small></div>
    <a class="iz-kpi-ubin" href="izin_register.php"><span>Izin bulan ini</span><b class="tnum"><?php echo $totalBulan; ?></b><small><?php echo isset($perStatus['disetujui']) ? (int) $perStatus['disetujui'] : 0; ?> disetujui</small></a>
    <div class="iz-kpi-ubin"><span>Rata-rata waktu keputusan</span><b class="tnum"><?php echo $rata[1] ? (int) round($rata[0]) . '<em> mnt</em>' : '–'; ?></b><small><?php echo (int) $rata[1]; ?> keputusan bulan ini</small></div>
  </div>

  <!-- Grafik utama -->
  <section class="iz-panel iz-bagian iz-grafik-kotak" aria-labelledby="h-grafik">
    <h2 id="h-grafik">Izin disetujui per hari · 30 hari terakhir</h2>
    <div class="iz-grafik" role="img" aria-label="Grafik batang jumlah izin disetujui per hari, 30 hari terakhir. Rincian ada di tabel di bawah grafik.">
      <div class="iz-grafik-sumbu" aria-hidden="true"><span><?php echo $maks; ?></span><span>0</span></div>
      <div class="iz-grafik-batang">
        <?php foreach ($hari as $i => $d): ?>
          <div class="iz-batang-kolom<?php echo $d['libur'] ? ' iz-libur' : ''; ?><?php echo $d['t'] === $hariIni ? ' iz-kini' : ''; ?>" style="--i:<?php echo $i; ?>"
               data-tip="<?php echo h(Formatter::tanggalIndonesia($d['t'])); ?> · <?php echo $d['n']; ?> izin">
            <span class="iz-batang" style="height:<?php echo $d['n'] ? max(4, round($d['n'] / $maks * 100)) : 0; ?>%"></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="iz-grafik-label" aria-hidden="true"><span><?php echo h(Formatter::tanggalIndonesia($mulaiGrafik)); ?></span><span>Hari ini</span></div>
      <div class="iz-tip" hidden></div>
    </div>
    <details class="iz-tabel-data"><summary>Lihat sebagai tabel</summary>
      <div class="iz-gulir"><table class="iz-tabel"><thead><tr><th>Tanggal</th><th>Izin disetujui</th></tr></thead><tbody>
        <?php foreach (array_reverse($hari) as $d): ?><tr><td><?php echo h(Formatter::tanggalIndonesia($d['t'])); ?></td><td class="tnum"><?php echo $d['n']; ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </details>
  </section>

  <div class="iz-dua">
    <!-- Hari ini -->
    <section class="iz-panel iz-bagian" aria-labelledby="h-hari">
      <h2 id="h-hari">Izin hari ini</h2>
      <?php if (!$izinHariIni): ?><p class="redup">Belum ada izin disetujui hari ini.</p><?php endif; ?>
      <ul class="iz-daftar-garis">
        <?php foreach ($izinHariIni as $p):
            $keadaan = $jamKini < $p['jam_mulai'] ? 'Terjadwal' : ($p['jam_kembali'] && $jamKini >= $p['jam_kembali'] ? 'Rentang selesai' : 'Dalam rentang izin'); ?>
          <li><a href="../izin/detail.php?id=<?php echo (int) $p['id']; ?>"><?php echo h($p['pemohon_nama']); ?></a>
            <span class="redup"><?php echo $p['jenis'] === 'pulang_awal' ? 'Pulang awal ' . $jam($p['jam_mulai']) : 'Keluar ' . $jam($p['jam_mulai']) . '–' . $jam($p['jam_kembali']); ?> · <?php echo h($keadaan); ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <!-- Perlu tindakan -->
    <section class="iz-panel iz-bagian" aria-labelledby="h-tindak">
      <h2 id="h-tindak">Perlu tindakan</h2>
      <?php if (!$jumlahMasalah): ?><p class="iz-baik"><?php echo ikonStatus(true); ?> Tidak ada yang perlu ditindaklanjuti.</p><?php endif; ?>
      <ul class="iz-daftar-garis">
        <?php foreach ($lama as $p): ?>
          <li><a href="../izin/detail.php?id=<?php echo (int) $p['id']; ?>"><?php echo h($p['pemohon_nama']); ?></a>
            <span class="redup">menunggu <?php echo h($p['pemberi_nama']); ?> sejak <?php echo h($sejak($p['diajukan_at'])); ?> lalu</span></li>
        <?php endforeach; ?>
        <?php foreach ($halangan as $p): ?>
          <li><a href="../izin/detail.php?id=<?php echo (int) $p['id']; ?>"><?php echo h($p['pemohon_nama']); ?></a>
            <span class="redup"><?php echo h($p['pemberi_nama']); ?> sedang berhalangan — alihkan</span></li>
        <?php endforeach; ?>
        <?php foreach ($suratGagal as $d): ?>
          <li><a href="../izin/detail.php?id=<?php echo (int) $d['permohonan_id']; ?>">Surat <?php echo h($d['pemohon_nama']); ?></a>
            <span class="redup"><?php echo $d['status'] === 'gagal' ? 'gagal dibuat' : 'tertahan diproses'; ?> — buat ulang dari detail</span></li>
        <?php endforeach; ?>
        <?php foreach ($waGagal as $w): ?>
          <li><a href="../izin/detail.php?id=<?php echo (int) $w['permohonan_id']; ?>">WA ke <?php echo h($w['nama_tampilan']); ?></a>
            <span class="redup">gagal: <?php echo h($w['error']); ?></span></li>
        <?php endforeach; ?>
        <?php if ($tanpaKelompok): ?>
          <li><a href="izin_pengaturan.php#pemetaan"><?php echo $tanpaKelompok; ?> pegawai berakun belum punya kelompok</a><span class="redup">belum bisa mengajukan izin</span></li>
        <?php endif; ?>
        <?php if ($tanpaNomor): ?>
          <li><a href="izin_pengaturan.php#pemetaan"><?php echo $tanpaNomor; ?> pegawai tanpa nomor WA</a><span class="redup">tidak menerima pemberitahuan WhatsApp</span></li>
        <?php endif; ?>
      </ul>
    </section>

    <!-- Bulan ini -->
    <section class="iz-panel iz-bagian" aria-labelledby="h-bulan">
      <h2 id="h-bulan">Rekap bulan ini</h2>
      <?php if (!$totalBulan): ?><p class="redup">Belum ada permohonan bulan ini.</p><?php else: ?>
      <p class="iz-sub">Per status</p>
      <dl class="iz-dl iz-dl--rapat">
        <?php foreach (Permohonan::LABEL_STATUS as $k => $v): if (empty($perStatus[$k])) { continue; } ?>
          <dt><?php echo h($v); ?></dt><dd class="tnum"><?php echo (int) $perStatus[$k]; ?></dd>
        <?php endforeach; ?>
      </dl>
      <p class="iz-sub">Per jenis</p>
      <dl class="iz-dl iz-dl--rapat">
        <?php foreach (Permohonan::LABEL_JENIS as $k => $v): ?>
          <dt><?php echo h(ucfirst($v)); ?></dt><dd class="tnum"><?php echo isset($perJenis[$k]) ? (int) $perJenis[$k] : 0; ?></dd>
        <?php endforeach; ?>
      </dl>
      <?php endif; ?>
      <p style="margin-top:16px;"><a href="izin_register.php">Buka register lengkap</a></p>
    </section>

    <!-- Kesehatan sistem -->
    <section class="iz-panel iz-bagian" aria-labelledby="h-sistem">
      <h2 id="h-sistem">Kesehatan sistem</h2>
      <ul class="iz-daftar-garis">
        <li><span class="<?php echo $peng['mode'] === 'aktif' ? 'iz-baik' : 'iz-awas'; ?>"><?php echo ikonStatus($peng['mode'] === 'aktif'); ?> Mode modul: <?php echo h($LABEL_MODE[$peng['mode']]); ?></span></li>
        <li><span class="<?php echo $peng['wa_aktif'] && $peng['wa_token'] ? 'iz-baik' : 'iz-awas'; ?>"><?php echo ikonStatus($peng['wa_aktif'] && $peng['wa_token']); ?> WhatsApp: <?php echo $peng['wa_aktif'] ? ($peng['wa_token'] ? 'aktif' : 'aktif tapi token kosong') : 'nonaktif'; ?></span></li>
        <li><span class="<?php echo $cronSehat ? 'iz-baik' : 'iz-awas'; ?>"><?php echo ikonStatus($cronSehat); ?> Cron WA terakhir jalan: <?php echo $detak ? h($sejak(gmdate('Y-m-d H:i:s', $detak))) . ' lalu' : 'belum pernah'; ?></span></li>
        <li><span class="<?php echo $antre[0] && $antre[1] < Permohonan::utc($kini->modify('-5 minutes')) ? 'iz-awas' : 'iz-baik'; ?>"><?php echo ikonStatus(!($antre[0] && $antre[1] < Permohonan::utc($kini->modify('-5 minutes')))); ?> Antrean WA: <?php echo (int) $antre[0]; ?> pesan<?php echo $antre[0] ? ', tertua ' . h($sejak($antre[1])) : ''; ?></span></li>
        <li><span class="<?php echo $peng['token_portal'] ? 'iz-baik' : 'iz-awas'; ?>"><?php echo ikonStatus((bool) $peng['token_portal']); ?> Token portal: <?php echo $peng['token_portal'] ? 'sudah dibuat' : 'belum dibuat'; ?></span></li>
      </ul>
    </section>
  </div>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
