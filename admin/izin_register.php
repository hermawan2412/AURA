<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Database;
use Aurat\Formatter;
use Aurat\Izin\Permohonan;

Auth::requireAdmin();
$db = Database::pdo();

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$f = array(
    'dari' => isset($_GET['dari']) && preg_match('/^\d{4}-\d\d-\d\d$/', $_GET['dari']) ? $_GET['dari'] : date('Y-m-01'),
    'sampai' => isset($_GET['sampai']) && preg_match('/^\d{4}-\d\d-\d\d$/', $_GET['sampai']) ? $_GET['sampai'] : date('Y-m-t'),
    'status' => isset($_GET['status']) && isset(Permohonan::LABEL_STATUS[$_GET['status']]) ? $_GET['status'] : '',
    'jenis' => isset($_GET['jenis']) && isset(Permohonan::LABEL_JENIS[$_GET['jenis']]) ? $_GET['jenis'] : '',
    'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : '',
);
$where = 'p.tanggal BETWEEN ? AND ?';
$arg = array($f['dari'], $f['sampai']);
foreach (array('status', 'jenis') as $k) {
    if ($f[$k] !== '') {
        $where .= " AND p.$k = ?";
        $arg[] = $f[$k];
    }
}
if ($f['q'] !== '') {
    $where .= ' AND p.pemohon_nama LIKE ?';
    $arg[] = '%' . $f['q'] . '%';
}
$sql = "SELECT p.*, d.nomor,
          (SELECT t.pemberi_nama FROM izin_tahap t WHERE t.permohonan_id = p.id AND t.status IN ('selesai','ditolak','menunggu') ORDER BY t.id DESC LIMIT 1) AS pemberi
        FROM izin_permohonan p LEFT JOIN izin_dokumen d ON d.permohonan_id = p.id WHERE $where ORDER BY p.tanggal DESC, p.jam_mulai DESC";

if (isset($_GET['csv'])) {
    $s = $db->prepare($sql);
    $s->execute($arg);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="register_izin_' . $f['dari'] . '_' . $f['sampai'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    // Lindungi dari formula spreadsheet; alasan sengaja tidak diekspor.
    $aman = function ($v) {
        $v = (string) $v;
        return $v !== '' && strpos("=+-@\t\r", $v[0]) !== false ? "'" . $v : $v;
    };
    fputcsv($out, array('ID', 'Nomor', 'Tanggal', 'Jam mulai', 'Jam kembali', 'Jenis', 'Pemohon', 'NIP', 'Jabatan', 'Pemberi', 'Status', 'Diajukan (UTC)', 'Diputus (UTC)'), ',', '"', '');
    while ($p = $s->fetch()) {
        fputcsv($out, array_map($aman, array($p['id'], $p['nomor'], $p['tanggal'], substr($p['jam_mulai'], 0, 5), $p['jam_kembali'] ? substr($p['jam_kembali'], 0, 5) : '',
            Permohonan::LABEL_JENIS[$p['jenis']], $p['pemohon_nama'], $p['pemohon_nip'], $p['pemohon_jabatan'],
            $p['pemberi_eksternal'] ?: $p['pemberi'], Permohonan::LABEL_STATUS[$p['status']], $p['diajukan_at'], $p['diputus_at'])), ',', '"', '');
    }
    exit;
}

$halaman = max(1, isset($_GET['hal']) ? (int) $_GET['hal'] : 1);
$s = $db->prepare("SELECT p.status, COUNT(*) n FROM izin_permohonan p WHERE $where GROUP BY p.status");
$s->execute($arg);
$jumlah = $s->fetchAll(PDO::FETCH_KEY_PAIR);
$total = array_sum($jumlah);
$s = $db->prepare($sql . ' LIMIT 50 OFFSET ' . (($halaman - 1) * 50));
$s->execute($arg);
$baris = $s->fetchAll();
$query = function (array $ubah) use ($f) {
    return h('?' . http_build_query(array_merge($f, $ubah)));
};

$halamanAktif = 'admin_izin_register';
$judulHalaman = 'Register Izin';
$breadcrumb = 'Izin';
$rootAsset = '../';
require __DIR__ . '/../views/layout_atas.php';
?>

<form method="get" class="form-card" style="max-width:980px; margin-bottom:16px;">
  <div class="grid-3">
    <div class="field"><label for="dari">Dari</label><input id="dari" type="date" name="dari" value="<?php echo h($f['dari']); ?>"></div>
    <div class="field"><label for="sampai">Sampai</label><input id="sampai" type="date" name="sampai" value="<?php echo h($f['sampai']); ?>"></div>
    <div class="field"><label for="q">Nama pemohon</label><input id="q" name="q" value="<?php echo h($f['q']); ?>"></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Semua</option>
      <?php foreach (Permohonan::LABEL_STATUS as $k => $v): ?><option value="<?php echo $k; ?>" <?php echo $f['status'] === $k ? 'selected' : ''; ?>><?php echo h($v); ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="jenis">Jenis</label><select id="jenis" name="jenis"><option value="">Semua</option>
      <?php foreach (Permohonan::LABEL_JENIS as $k => $v): ?><option value="<?php echo $k; ?>" <?php echo $f['jenis'] === $k ? 'selected' : ''; ?>><?php echo h(ucfirst($v)); ?></option><?php endforeach; ?></select></div>
    <div class="field" style="display:flex; gap:8px; align-items:end;"><button class="btn btn-primary" type="submit">Terapkan</button>
      <a class="btn btn-secondary" href="<?php echo $query(array('csv' => 1)); ?>">Unduh CSV</a></div>
  </div>
</form>

<p class="note" style="margin-bottom:10px;">Total <?php echo $total; ?>
  <?php foreach ($jumlah as $st => $n): ?> · <?php echo h(Permohonan::LABEL_STATUS[$st]); ?> <?php echo (int) $n; ?><?php endforeach; ?></p>

<div class="table-wrap" style="max-width:980px;">
  <table>
    <thead><tr><th>Tanggal</th><th>Pemohon</th><th>Jenis / jam</th><th>Pemberi</th><th>Status</th><th>Nomor</th><th></th></tr></thead>
    <tbody>
    <?php if (!$baris): ?><tr><td colspan="7" class="note">Tidak ada data.</td></tr><?php endif; ?>
    <?php foreach ($baris as $p): ?>
      <tr>
        <td><?php echo h(Formatter::tanggalIndonesia($p['tanggal'])); ?></td>
        <td><?php echo h($p['pemohon_nama']); ?></td>
        <td><?php echo h(ucfirst(Permohonan::LABEL_JENIS[$p['jenis']])); ?><br><span class="note tnum"><?php echo h(substr($p['jam_mulai'], 0, 5) . ($p['jam_kembali'] ? '–' . substr($p['jam_kembali'], 0, 5) : '')); ?></span></td>
        <td><?php echo h($p['pemberi_eksternal'] ?: $p['pemberi']); ?></td>
        <td><?php echo h(Permohonan::LABEL_STATUS[$p['status']]); ?></td>
        <td><?php echo h($p['nomor']); ?></td>
        <td><a class="btn btn-secondary" href="../izin/detail.php?id=<?php echo (int) $p['id']; ?>">Lihat</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($total > 50): ?>
  <p style="margin-top:12px;">
    <?php if ($halaman > 1): ?><a class="btn btn-secondary" href="<?php echo $query(array('hal' => $halaman - 1)); ?>">Sebelumnya</a><?php endif; ?>
    <?php if ($halaman * 50 < $total): ?><a class="btn btn-secondary" href="<?php echo $query(array('hal' => $halaman + 1)); ?>">Berikutnya</a><?php endif; ?>
  </p>
<?php endif; ?>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
