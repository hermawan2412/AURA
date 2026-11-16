<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Database;
use Aurat\Formatter;
use Aurat\Izin\Permohonan;
use Aurat\Izin\Routing;
use Aurat\Izin\Waktu;

Auth::requireAdmin();
$db = Database::pdo();
$userId = (int) $_SESSION['user_id'];
$kini = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$HARI = array(1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu');
$JABATAN = array('ketua' => 'Ketua', 'wakil' => 'Wakil Ketua', 'panitera' => 'Panitera', 'sekretaris' => 'Sekretaris');

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$peng = Permohonan::pengaturan($db);
$galat = '';
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $bagian = isset($_POST['bagian']) ? $_POST['bagian'] : '';
    $tz = new DateTimeZone($peng['zona_waktu']);
    try {
        $db->beginTransaction();
        switch ($bagian) {
            case 'umum':
                $mode = in_array($_POST['mode'], array('nonaktif', 'aktif', 'pemeliharaan'), true) ? $_POST['mode'] : 'nonaktif';
                $zona = trim((string) $_POST['zona_waktu']);
                if (!in_array($zona, DateTimeZone::listIdentifiers(DateTimeZone::ASIA), true)) {
                    throw new RuntimeException('Zona waktu tidak dikenal.');
                }
                if ($mode === 'aktif') {
                    $pj = Permohonan::pejabat($db);
                    foreach ($JABATAN as $k => $label) {
                        if (empty($pj[$k]) || !$pj[$k]['user_aktif']) {
                            throw new RuntimeException('Belum bisa diaktifkan: ' . $label . ' belum diisi atau tidak punya akun aktif.');
                        }
                    }
                }
                $token = trim((string) $_POST['wa_token']);
                $db->prepare('UPDATE izin_pengaturan SET mode = ?, zona_waktu = ?, izin_lisan_aktif = ?, wa_aktif = ?, wa_token = IF(? = \'\', wa_token, ?), updated_at = ? WHERE id = 1')
                    ->execute(array($mode, $zona, empty($_POST['izin_lisan_aktif']) ? 0 : 1, empty($_POST['wa_aktif']) ? 0 : 1, $token, $token, Permohonan::utc($kini)));
                if (!empty($_POST['token_portal_baru'])) {
                    $db->prepare('UPDATE izin_pengaturan SET token_portal = ? WHERE id = 1')->execute(array(bin2hex(random_bytes(24))));
                }
                Permohonan::auditKonfigurasi($db, $userId, 'ubah_pengaturan', array('mode' => $mode, 'zona' => $zona, 'wa_aktif' => !empty($_POST['wa_aktif']), 'token_wa_diganti' => $token !== '', 'token_portal_baru' => !empty($_POST['token_portal_baru'])), $kini);
                break;

            case 'jadwal':
                $jadwal = array();
                foreach ($HARI as $n => $label) {
                    $m = isset($_POST['mulai'][$n]) ? $_POST['mulai'][$n] : '';
                    $s = isset($_POST['selesai'][$n]) ? $_POST['selesai'][$n] : '';
                    if ($m === '' && $s === '') {
                        continue;
                    }
                    if (!preg_match('/^\d\d:\d\d$/', $m) || !preg_match('/^\d\d:\d\d$/', $s) || $m >= $s) {
                        throw new RuntimeException('Jam kerja ' . $label . ' tidak valid.');
                    }
                    $jadwal[$n] = array('mulai' => $m, 'selesai' => $s);
                }
                $db->prepare('UPDATE izin_pengaturan SET jadwal_kerja = ?, updated_at = ? WHERE id = 1')->execute(array(json_encode($jadwal), Permohonan::utc($kini)));
                Permohonan::auditKonfigurasi($db, $userId, 'ubah_jadwal', $jadwal, $kini);
                break;

            case 'libur_tambah':
                $tgl = (string) $_POST['tanggal'];
                $ket = trim((string) $_POST['keterangan']);
                if (!preg_match('/^\d{4}-\d\d-\d\d$/', $tgl) || $ket === '') {
                    throw new RuntimeException('Tanggal dan keterangan libur wajib diisi.');
                }
                $db->prepare('REPLACE INTO izin_hari_libur (tanggal, keterangan) VALUES (?, ?)')->execute(array($tgl, mb_substr($ket, 0, 150)));
                Permohonan::auditKonfigurasi($db, $userId, 'tambah_libur', array('tanggal' => $tgl, 'keterangan' => $ket), $kini);
                break;

            case 'libur_hapus':
                $db->prepare('DELETE FROM izin_hari_libur WHERE tanggal = ?')->execute(array((string) $_POST['tanggal']));
                Permohonan::auditKonfigurasi($db, $userId, 'hapus_libur', array('tanggal' => (string) $_POST['tanggal']), $kini);
                break;

            case 'pejabat':
                foreach ($JABATAN as $k => $label) {
                    $pid = isset($_POST['pejabat'][$k]) ? (int) $_POST['pejabat'][$k] : 0;
                    if ($pid > 0) {
                        $db->prepare('REPLACE INTO izin_pejabat (kode, pegawai_id, updated_by, updated_at) VALUES (?, ?, ?, ?)')
                            ->execute(array($k, $pid, $userId, Permohonan::utc($kini)));
                    } else {
                        $db->prepare('DELETE FROM izin_pejabat WHERE kode = ?')->execute(array($k));
                    }
                }
                Permohonan::auditKonfigurasi($db, $userId, 'ubah_pejabat', isset($_POST['pejabat']) ? $_POST['pejabat'] : array(), $kini);
                break;

            case 'pemetaan':
                $ubah = array();
                foreach ((array) (isset($_POST['kelompok']) ? $_POST['kelompok'] : array()) as $pid => $kel) {
                    $pid = (int) $pid;
                    if ($kel === '') {
                        $db->prepare('DELETE FROM izin_pemetaan_pegawai WHERE pegawai_id = ?')->execute(array($pid));
                    } elseif (in_array($kel, Routing::KELOMPOK, true)) {
                        $db->prepare('INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_by, updated_at) VALUES (?,?,?,?)
                                      ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)')
                            ->execute(array($pid, $kel, $userId, Permohonan::utc($kini)));
                    }
                    $ubah[$pid] = $kel;
                }
                Permohonan::auditKonfigurasi($db, $userId, 'ubah_pemetaan', $ubah, $kini);
                break;

            case 'berhalangan_tambah':
                $mulai = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) $_POST['mulai'], $tz);
                $selesai = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) $_POST['selesai'], $tz);
                $jenis = in_array($_POST['jenis'], array('cuti', 'dinas_luar', 'lainnya'), true) ? $_POST['jenis'] : null;
                if (!$mulai || !$selesai || $selesai <= $mulai || !$jenis) {
                    throw new RuntimeException('Periode atau jenis berhalangan tidak valid.');
                }
                $db->prepare('INSERT INTO izin_berhalangan (pegawai_id, mulai, selesai, jenis, catatan, dibuat_oleh, dibuat_at) VALUES (?,?,?,?,?,?,?)')
                    ->execute(array((int) $_POST['pegawai_id'], Permohonan::utc($mulai), Permohonan::utc($selesai), $jenis, trim((string) $_POST['catatan']) ?: null, $userId, Permohonan::utc($kini)));
                Permohonan::auditKonfigurasi($db, $userId, 'tambah_berhalangan', array('pegawai_id' => (int) $_POST['pegawai_id'], 'mulai' => $_POST['mulai'], 'selesai' => $_POST['selesai'], 'jenis' => $jenis), $kini);
                break;

            case 'berhalangan_batal':
                $db->prepare('UPDATE izin_berhalangan SET dibatalkan_oleh = ?, dibatalkan_at = ? WHERE id = ? AND dibatalkan_at IS NULL')
                    ->execute(array($userId, Permohonan::utc($kini), (int) $_POST['id']));
                Permohonan::auditKonfigurasi($db, $userId, 'batal_berhalangan', array('id' => (int) $_POST['id']), $kini);
                break;

            default:
                throw new RuntimeException('Bagian tidak dikenal.');
        }
        $db->commit();
        $_SESSION['izin_kilat'] = array('Tersimpan.', 'info');
        header('Location: izin_pengaturan.php#' . preg_replace('/[^a-z]/', '', explode('_', $bagian)[0]));
        exit;
    } catch (RuntimeException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $galat = $e->getMessage();
    }
}

$peng = Permohonan::pengaturan($db);
$jadwal = Waktu::jadwalDariJson($peng['jadwal_kerja']);
$libur = $db->query('SELECT * FROM izin_hari_libur WHERE tanggal >= CURDATE() - INTERVAL 30 DAY ORDER BY tanggal')->fetchAll();
$pegawai = $db->query(
    'SELECT p.id, p.nama_lengkap, p.gelar_depan, p.gelar_belakang, p.jabatan, p.no_telp, m.kelompok, u.id AS user_id
     FROM pegawai p LEFT JOIN izin_pemetaan_pegawai m ON m.pegawai_id = p.id LEFT JOIN user_login u ON u.nip = p.nip AND u.status_aktif = 1
     WHERE p.status_aktif = 1 ORDER BY p.nama_lengkap'
)->fetchAll();
$pejabat = Permohonan::pejabat($db);
$berhalangan = $db->query(
    'SELECT b.*, p.nama_lengkap FROM izin_berhalangan b JOIN pegawai p ON p.id = b.pegawai_id
     WHERE b.dibatalkan_at IS NULL AND b.selesai > UTC_TIMESTAMP() ORDER BY b.mulai'
)->fetchAll();
// Tugas tertunda yang pemberinya sedang berhalangan -> perlu dialihkan.
$s = $db->prepare(
    'SELECT p.id, p.pemohon_nama, t.pemberi_nama FROM izin_tahap t JOIN izin_permohonan p ON p.id = t.permohonan_id
     WHERE t.status = \'menunggu\' AND t.pemberi_pegawai_id IN (
        SELECT pegawai_id FROM izin_berhalangan WHERE dibatalkan_at IS NULL AND mulai <= ? AND selesai > ?)'
);
$s->execute(array(Permohonan::utc($kini), Permohonan::utc($kini)));
$perluAlih = $s->fetchAll();
$tzKantor = new DateTimeZone($peng['zona_waktu']);
$lokal = function ($utc) use ($tzKantor) {
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tzKantor)->format('d-m-Y H.i');
};
$tanpaPemetaan = count(array_filter($pegawai, function ($p) { return $p['user_id'] && !$p['kelompok']; }));

$halamanAktif = 'admin_izin_pengaturan';
$judulHalaman = 'Pengaturan Izin';
$breadcrumb = 'Izin';
$subJudul = 'Pejabat, kelompok pegawai, jam kerja, pejabat berhalangan, dan WhatsApp untuk modul izin.';
$rootAsset = '../';
require __DIR__ . '/../views/layout_atas.php';
$kilat = isset($_SESSION['izin_kilat']) ? $_SESSION['izin_kilat'] : null;
unset($_SESSION['izin_kilat']);
?>

<?php if ($galat !== ''): ?><div class="alert alert-error" role="alert"><?php echo h($galat); ?></div><?php endif; ?>
<?php if ($kilat): ?><div class="alert alert-info" role="status"><?php echo h($kilat[0]); ?></div><?php endif; ?>
<?php if ($perluAlih): ?>
  <div class="alert alert-error">Permohonan menunggu pejabat yang sedang berhalangan — buka lalu pilih “Alihkan”:
    <?php foreach ($perluAlih as $a): ?><a href="../izin/detail.php?id=<?php echo (int) $a['id']; ?>">#<?php echo (int) $a['id']; ?> <?php echo h($a['pemohon_nama']); ?> (ke <?php echo h($a['pemberi_nama']); ?>)</a> <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="form-card" id="umum" style="max-width:640px;">
  <h2 style="font-family:var(--display); font-size:1rem; margin-bottom:12px;">Status modul &amp; WhatsApp</h2>
  <form method="post">
    <?php echo Csrf::field(); ?><input type="hidden" name="bagian" value="umum">
    <div class="grid-2">
      <div class="field"><label for="mode">Mode</label>
        <select id="mode" name="mode">
          <?php foreach (array('nonaktif' => 'Nonaktif (jalur surat lama)', 'aktif' => 'Aktif', 'pemeliharaan' => 'Pemeliharaan (baca saja)') as $k => $v): ?>
            <option value="<?php echo $k; ?>" <?php echo $peng['mode'] === $k ? 'selected' : ''; ?>><?php echo h($v); ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label for="zona">Zona waktu kantor</label>
        <input id="zona" name="zona_waktu" value="<?php echo h($peng['zona_waktu']); ?>"></div>
    </div>
    <p class="note">Mode aktif/pemeliharaan menutup pembuatan surat izin lewat menu surat lama.</p>
    <div class="field"><label><input type="checkbox" name="wa_aktif" value="1" <?php echo $peng['wa_aktif'] ? 'checked' : ''; ?>> Kirim WhatsApp (Fonnte)</label></div>
    <div class="field"><label for="wa_token">Token Fonnte <?php echo $peng['wa_token'] ? '(tersimpan — kosongkan bila tidak diganti)' : ''; ?></label>
      <input id="wa_token" name="wa_token" type="password" autocomplete="off"></div>
    <div class="field"><label><input type="checkbox" name="izin_lisan_aktif" value="1" <?php echo $peng['izin_lisan_aktif'] ? 'checked' : ''; ?>> Izin darurat/lisan (belum dipakai di v1)</label></div>
    <div class="field"><label><input type="checkbox" name="token_portal_baru" value="1"> Buat token portal baru</label>
      <p class="note">Token portal: <?php echo $peng['token_portal'] ? '<code>' . h($peng['token_portal']) . '</code> — salin ke konfigurasi portal' : 'belum ada'; ?></p></div>
    <button class="btn btn-primary" type="submit">Simpan</button>
  </form>
</div>

<div class="form-card" id="pejabat" style="max-width:640px; margin-top:20px;">
  <h2 style="font-family:var(--display); font-size:1rem; margin-bottom:12px;">Pejabat</h2>
  <form method="post">
    <?php echo Csrf::field(); ?><input type="hidden" name="bagian" value="pejabat">
    <?php foreach ($JABATAN as $k => $label): ?>
      <div class="field"><label for="pj-<?php echo $k; ?>"><?php echo h($label); ?></label>
        <select id="pj-<?php echo $k; ?>" name="pejabat[<?php echo $k; ?>]">
          <option value="0">— belum diisi —</option>
          <?php foreach ($pegawai as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>" <?php echo isset($pejabat[$k]) && (int) $pejabat[$k]['pegawai_id'] === (int) $p['id'] ? 'selected' : ''; ?>><?php echo h(Formatter::namaBergelar($p)); ?><?php echo $p['user_id'] ? '' : ' (tanpa akun)'; ?></option>
          <?php endforeach; ?>
        </select></div>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit">Simpan pejabat</button>
  </form>
</div>

<div class="form-card" id="berhalangan" style="max-width:640px; margin-top:20px;">
  <h2 style="font-family:var(--display); font-size:1rem; margin-bottom:12px;">Pejabat berhalangan</h2>
  <?php if ($berhalangan): ?>
    <ul style="list-style:none; padding:0; margin:0 0 12px;">
      <?php foreach ($berhalangan as $b): ?>
        <li style="padding:4px 0;"><?php echo h($b['nama_lengkap']); ?> — <?php echo h(str_replace('_', ' ', $b['jenis'])); ?>, <?php echo h($lokal($b['mulai'])); ?> s.d. <?php echo h($lokal($b['selesai'])); ?>
          <form method="post" style="display:inline;"><?php echo Csrf::field(); ?><input type="hidden" name="bagian" value="berhalangan_batal"><input type="hidden" name="id" value="<?php echo (int) $b['id']; ?>"><button class="btn btn-secondary" type="submit">Batalkan</button></form></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <form method="post">
    <?php echo Csrf::field(); ?><input type="hidden" name="bagian" value="berhalangan_tambah">
    <div class="field"><label for="bh-pegawai">Pejabat</label>
      <select id="bh-pegawai" name="pegawai_id" required>
        <?php foreach ($pejabat as $k => $pj): ?><option value="<?php echo (int) $pj['pegawai_id']; ?>"><?php echo h($JABATAN[$k] . ' — ' . $pj['nama']); ?></option><?php endforeach; ?>
      </select></div>
    <div class="grid-2">
      <div class="field"><label for="bh-mulai">Mulai</label><input id="bh-mulai" type="datetime-local" name="mulai" required></div>
      <div class="field"><label for="bh-selesai">Selesai</label><input id="bh-selesai" type="datetime-local" name="selesai" required></div>
    </div>
    <div class="grid-2">
      <div class="field"><label for="bh-jenis">Jenis</label><select id="bh-jenis" name="jenis"><option value="cuti">Cuti</option><option value="dinas_luar">Dinas luar</option><option value="lainnya">Lainnya</option></select></div>
      <div class="field"><label for="bh-catatan">Catatan</label><input id="bh-catatan" name="catatan" maxlength="255"></div>
    </div>
    <button class="btn btn-primary" type="submit">Catat berhalangan</button>
  </form>
</div>

<div class="form-card" id="jadwal" style="max-width:640px; margin-top:20px;">
  <h2 style="font-family:var(--display); font-size:1rem; margin-bottom:12px;">Jam kerja</h2>
  <p class="note" style="margin-bottom:10px;">Kosongkan kedua jam untuk hari libur. Pastikan sesuai jam kerja resmi sebelum modul diaktifkan.</p>
  <form method="post">
    <?php echo Csrf::field(); ?><input type="hidden" name="bagian" value="jadwal">
    <?php foreach ($HARI as $n => $label): ?>
      <div class="grid-3" style="align-items:end;">
        <div class="field"><span><?php echo h($label); ?></span></div>
        <div class="field"><label for="jm-<?php echo $n; ?>">Masuk</label><input id="jm-<?php echo $n; ?>" type="time" name="mulai[<?php echo $n; ?>]" value="<?php echo isset($jadwal[$n]) ? h($jadwal[$n]['mulai']) : ''; ?>"></div>
        <div class="field"><label for="js-<?php echo $n; ?>">Pulang</label><input id="js-<?php echo $n; ?>" type="time" name="selesai[<?php echo $n; ?>]" value="<?php echo isset($jadwal[$n]) ? h($jadwal[$n]['selesai']) : ''; ?>"></div>
      </div>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit">Simpan jam kerja</button>
  </form>

  <h3 style="font-size:.95rem; margin:20px 0 8px;">Hari libur</h3>
  <ul style="list-style:none; padding:0; margin:0 0 12px;">
    <?php foreach ($libur as $l): ?>
      <li style="padding:3px 0;"><?php echo h(Formatter::tanggalIndonesia($l['tanggal'])); ?> — <?php echo h($l['keterangan']); ?>
        <form method="post" style="display:inline;"><?php echo Csrf::field(); ?><input type="hidden" name="bagian" value="libur_hapus"><input type="hidden" name="tanggal" value="<?php echo h($l['tanggal']); ?>"><button class="btn btn-secondary" type="submit">Hapus</button></form></li>
    <?php endforeach; ?>
  </ul>
  <form method="post" class="grid-3" style="align-items:end;">
    <?php echo Csrf::field(); ?><input type="hidden" name="bagian" value="libur_tambah">
    <div class="field"><label for="lb-tgl">Tanggal</label><input id="lb-tgl" type="date" name="tanggal" required></div>
    <div class="field"><label for="lb-ket">Keterangan</label><input id="lb-ket" name="keterangan" required maxlength="150"></div>
    <div class="field"><button class="btn btn-secondary" type="submit">Tambah libur</button></div>
  </form>
</div>

<div class="form-card" id="pemetaan" style="max-width:980px; margin-top:20px;">
  <h2 style="font-family:var(--display); font-size:1rem; margin-bottom:12px;">Kelompok pegawai</h2>
  <p class="note" style="margin-bottom:10px;">Menentukan rute persetujuan. <?php echo $tanpaPemetaan ? $tanpaPemetaan . ' pegawai berakun belum dipetakan dan belum bisa mengajukan izin.' : 'Semua pegawai berakun sudah dipetakan.'; ?></p>
  <form method="post">
    <?php echo Csrf::field(); ?><input type="hidden" name="bagian" value="pemetaan">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Pegawai</th><th>Jabatan</th><th>Akun</th><th>No. WA</th><th>Kelompok</th></tr></thead>
        <tbody>
        <?php foreach ($pegawai as $p): ?>
          <tr>
            <td><?php echo h(Formatter::namaBergelar($p)); ?></td>
            <td><?php echo h($p['jabatan']); ?></td>
            <td><?php echo $p['user_id'] ? 'Ada' : '<span class="note">Tidak ada</span>'; ?></td>
            <td><?php echo $p['no_telp'] ? 'Ada' : '<span class="note">Kosong</span>'; ?></td>
            <td><select name="kelompok[<?php echo (int) $p['id']; ?>]" aria-label="Kelompok <?php echo h($p['nama_lengkap']); ?>">
              <option value="">— belum —</option>
              <?php foreach (Routing::KELOMPOK as $k): ?><option value="<?php echo $k; ?>" <?php echo $p['kelompok'] === $k ? 'selected' : ''; ?>><?php echo h(ucfirst($k)); ?></option><?php endforeach; ?>
            </select></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button class="btn btn-primary" type="submit" style="margin-top:12px;">Simpan kelompok</button>
  </form>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
