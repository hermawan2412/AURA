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
            $p['pemberi'], Permohonan::LABEL_STATUS[$p['status']], $p['diajukan_at'], $p['diputus_at'])), ',', '"', '');
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

// Pola 1 — List / Index: register seluruh izin (cari + satu kontrol Filter + ekspor di toolbar).
$halamanAktif = 'admin_izin_register';
$judulHalaman = 'Register Izin';
$breadcrumb = 'Izin';
$subJudul = 'Seluruh permohonan izin per periode. Alasan pribadi tidak ikut diekspor.';
$rootAsset = '../';
$cssTambahan = 'assets/css/izin.css';
$jsTambahan = 'assets/js/izin.js';
require __DIR__ . '/../views/layout_atas.php';
$chip = array();
if ($f['status'] !== '') { $chip[] = 'Status: ' . Permohonan::LABEL_STATUS[$f['status']]; }
if ($f['jenis'] !== '') { $chip[] = 'Jenis: ' . ucfirst(Permohonan::LABEL_JENIS[$f['jenis']]); }
$jumlahHalaman = max(1, (int) ceil($total / 50));
?>

<div class="iz-page">
  <form method="get" class="iz-panel">
    <div class="iz-alat">
      <div class="iz-cari">
        <label for="q">Cari nama pemohon</label>
        <input id="q" name="q" type="search" value="<?php echo h($f['q']); ?>" placeholder="Ketik lalu Enter">
      </div>
      <div class="iz-saring">
        <div class="field"><label for="dari">Dari</label><input id="dari" type="date" name="dari" value="<?php echo h($f['dari']); ?>"></div>
        <div class="field"><label for="sampai">Sampai</label><input id="sampai" type="date" name="sampai" value="<?php echo h($f['sampai']); ?>"></div>
        <details style="position:relative;">
          <summary class="btn btn-secondary">Filter<?php echo $chip ? ' (' . count($chip) . ')' : ''; ?></summary>
          <div class="iz-panel" style="position:absolute; right:0; z-index:5; margin-top:8px; padding:16px; width:260px; box-shadow:var(--shadow-md);">
            <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Semua</option>
              <?php foreach (Permohonan::LABEL_STATUS as $k => $v): ?><option value="<?php echo $k; ?>" <?php echo $f['status'] === $k ? 'selected' : ''; ?>><?php echo h($v); ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="jenis">Jenis</label><select id="jenis" name="jenis"><option value="">Semua</option>
              <?php foreach (Permohonan::LABEL_JENIS as $k => $v): ?><option value="<?php echo $k; ?>" <?php echo $f['jenis'] === $k ? 'selected' : ''; ?>><?php echo h(ucfirst($v)); ?></option><?php endforeach; ?></select></div>
            <button class="btn btn-primary btn-block" type="submit">Terapkan</button>
          </div>
        </details>
        <button class="btn btn-secondary" type="submit">Tampilkan</button>
        <a class="btn btn-secondary" href="<?php echo $query(array('csv' => 1)); ?>"><?php echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 4v11M7.5 10.5L12 15l4.5-4.5M5 19.5h14"/></svg>'; ?>Ekspor CSV</a>
      </div>
    </div>
    <?php if ($chip || $f['q'] !== ''): ?>
      <div class="iz-alat" style="justify-content:flex-start; gap:8px;">
        <?php foreach ($chip as $c): ?><span class="iz-chip"><?php echo h($c); ?></span><?php endforeach; ?>
        <?php if ($f['q'] !== ''): ?><span class="iz-chip">Nama: <?php echo h($f['q']); ?></span><?php endif; ?>
        <a class="redup" href="izin_register.php?dari=<?php echo h($f['dari']); ?>&amp;sampai=<?php echo h($f['sampai']); ?>">Hapus filter</a>
      </div>
    <?php endif; ?>

    <div class="iz-gulir">
      <table class="iz-tabel">
        <thead><tr><th>Tanggal</th><th>Pemohon</th><th>Jenis / jam</th><th>Pemberi</th><th>Status</th><th>Nomor</th><th class="iz-panah"><span class="visually-hidden">Buka</span></th></tr></thead>
        <tbody>
        <?php if (!$baris): ?><tr><td colspan="7"><div class="iz-kosong"><b>Tidak ada izin pada periode ini</b>Ubah tanggal atau filter.</div></td></tr><?php endif; ?>
        <?php foreach ($baris as $i => $p): ?>
          <tr class="iz-baris" data-href="../izin/detail.php?id=<?php echo (int) $p['id']; ?>" style="--i:<?php echo $i; ?>">
            <td><a class="iz-utama" href="../izin/detail.php?id=<?php echo (int) $p['id']; ?>"><?php echo h(Formatter::tanggalIndonesia($p['tanggal'])); ?></a></td>
            <td><?php echo h($p['pemohon_nama']); ?></td>
            <td><?php echo h(ucfirst(Permohonan::LABEL_JENIS[$p['jenis']])); ?><br><span class="redup tnum"><?php echo h(str_replace(':', '.', substr($p['jam_mulai'], 0, 5)) . ($p['jam_kembali'] ? '–' . str_replace(':', '.', substr($p['jam_kembali'], 0, 5)) : '')); ?></span></td>
            <td><?php echo h($p['pemberi']); ?></td>
            <td><?php echo h(Permohonan::LABEL_STATUS[$p['status']]); ?></td>
            <td class="tnum"><?php echo h($p['nomor']); ?></td>
            <td class="iz-panah"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="iz-kaki">
      <span>Total <?php echo $total; ?><?php foreach ($jumlah as $st => $n): ?> · <?php echo h(Permohonan::LABEL_STATUS[$st]); ?> <?php echo (int) $n; ?><?php endforeach; ?></span>
      <span class="iz-aksi">
        <?php if ($halaman > 1): ?><a class="btn btn-secondary btn-kecil" href="<?php echo $query(array('hal' => $halaman - 1)); ?>">Sebelumnya</a><?php else: ?><span class="btn btn-secondary btn-kecil" aria-disabled="true" style="opacity:.5;">Sebelumnya</span><?php endif; ?>
        <span>Hal. <?php echo $halaman; ?> / <?php echo $jumlahHalaman; ?></span>
        <?php if ($halaman < $jumlahHalaman): ?><a class="btn btn-secondary btn-kecil" href="<?php echo $query(array('hal' => $halaman + 1)); ?>">Berikutnya</a><?php else: ?><span class="btn btn-secondary btn-kecil" aria-disabled="true" style="opacity:.5;">Berikutnya</span><?php endif; ?>
      </span>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
