<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Csrf;
use Aurat\Database;
use Aurat\Surat\IconLibrary;
use Aurat\Surat\JenisSuratRepository;

Auth::requirePengelolaAtauAdmin();

$pdo = Database::pdo();
$pesan = '';
$pesanTipe = 'info';

function auratKodeValid($kode)
{
    return (bool) preg_match('/^[a-z][a-z0-9_]{1,49}$/', $kode);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

    if ($aksi === 'tambah') {
        $kode            = isset($_POST['kode']) ? trim($_POST['kode']) : '';
        $nama            = isset($_POST['nama']) ? trim($_POST['nama']) : '';
        $deskripsi       = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
        $kategori        = (isset($_POST['kategori']) && $_POST['kategori'] === 'dua_dokumen') ? 'dua_dokumen' : 'single_dokumen';
        $icon            = isset($_POST['icon']) && IconLibrary::ada($_POST['icon']) ? $_POST['icon'] : IconLibrary::DEFAULT_SLUG;
        $kopSurat        = isset($_POST['kop_surat']) && trim($_POST['kop_surat']) !== '' ? trim($_POST['kop_surat']) : 'standar';
        $polaNamaUnduhan = isset($_POST['pola_nama_unduhan']) ? trim($_POST['pola_nama_unduhan']) : '';
        $urutanTampil    = isset($_POST['urutan_tampil']) ? (int) $_POST['urutan_tampil'] : 0;

        if (!auratKodeValid($kode) || $nama === '') {
            $pesan = 'Kode (huruf kecil/angka/underscore, diawali huruf) dan Nama wajib diisi dengan benar.';
            $pesanTipe = 'error';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO jenis_surat (kode, nama, deskripsi, kategori, icon, kop_surat, pola_nama_unduhan, status_aktif, urutan_tampil, dibuat_oleh)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
            );
            try {
                $stmt->execute(array(
                    $kode, $nama, $deskripsi !== '' ? $deskripsi : null, $kategori, $icon, $kopSurat,
                    $polaNamaUnduhan !== '' ? $polaNamaUnduhan : null, $urutanTampil,
                    isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
                ));
                header('Location: jenis_surat.php?id=' . (int) $pdo->lastInsertId());
                exit;
            } catch (\PDOException $e) {
                $pesan = (strpos($e->getMessage(), 'uq_jenis_surat_kode') !== false)
                    ? 'Kode tersebut sudah dipakai jenis surat lain.'
                    : 'Gagal menyimpan jenis surat.';
                $pesanTipe = 'error';
            }
        }
    } elseif ($aksi === 'ubah' && isset($_POST['id'])) {
        $id              = (int) $_POST['id'];
        $nama            = isset($_POST['nama']) ? trim($_POST['nama']) : '';
        $deskripsi       = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
        $icon            = isset($_POST['icon']) && IconLibrary::ada($_POST['icon']) ? $_POST['icon'] : IconLibrary::DEFAULT_SLUG;
        $kopSurat        = isset($_POST['kop_surat']) && trim($_POST['kop_surat']) !== '' ? trim($_POST['kop_surat']) : 'standar';
        $polaNamaUnduhan = isset($_POST['pola_nama_unduhan']) ? trim($_POST['pola_nama_unduhan']) : '';
        $urutanTampil    = isset($_POST['urutan_tampil']) ? (int) $_POST['urutan_tampil'] : 0;
        $variabelNomor     = isset($_POST['variabel_nomor_kode']) ? trim($_POST['variabel_nomor_kode']) : '';
        $variabelTanggal   = isset($_POST['variabel_tanggal_kode']) ? trim($_POST['variabel_tanggal_kode']) : '';
        $variabelRingkasan = isset($_POST['variabel_ringkasan_kode']) ? trim($_POST['variabel_ringkasan_kode']) : '';
        $kodeKlasifikasi   = isset($_POST['kode_klasifikasi']) ? trim($_POST['kode_klasifikasi']) : '';

        if ($nama === '') {
            $pesan = 'Nama wajib diisi.';
            $pesanTipe = 'error';
        } else {
            $pdo->prepare(
                'UPDATE jenis_surat SET nama=?, deskripsi=?, icon=?, kop_surat=?, pola_nama_unduhan=?, urutan_tampil=?,
                 variabel_nomor_kode=?, variabel_tanggal_kode=?, variabel_ringkasan_kode=?, kode_klasifikasi=?, updated_at=NOW() WHERE id=?'
            )->execute(array(
                $nama, $deskripsi !== '' ? $deskripsi : null, $icon, $kopSurat, $polaNamaUnduhan !== '' ? $polaNamaUnduhan : null, $urutanTampil,
                $variabelNomor !== '' ? $variabelNomor : null, $variabelTanggal !== '' ? $variabelTanggal : null, $variabelRingkasan !== '' ? $variabelRingkasan : null,
                $kodeKlasifikasi !== '' ? $kodeKlasifikasi : null,
                $id,
            ));
        }
        header('Location: jenis_surat.php?id=' . $id);
        exit;
    } elseif ($aksi === 'toggle_aktif' && isset($_POST['id'])) {
        $pdo->prepare('UPDATE jenis_surat SET status_aktif = 1 - status_aktif WHERE id = ?')->execute(array((int) $_POST['id']));
        header('Location: jenis_surat.php');
        exit;
    } elseif ($aksi === 'tambah_sub_jenis' && isset($_POST['jenis_surat_id'])) {
        $jenisSuratId = (int) $_POST['jenis_surat_id'];
        $kodeSub  = isset($_POST['kode']) ? trim($_POST['kode']) : '';
        $labelSub = isset($_POST['label']) ? trim($_POST['label']) : '';
        if (auratKodeValid($kodeSub) && $labelSub !== '') {
            try {
                $pdo->prepare('INSERT INTO sub_jenis_surat (jenis_surat_id, kode, label) VALUES (?, ?, ?)')
                    ->execute(array($jenisSuratId, $kodeSub, $labelSub));
            } catch (\PDOException $e) {
                // kode sub-jenis bentrok -> diamkan, redirect tetap jalan (pengguna lihat daftar tak berubah)
            }
        }
        header('Location: jenis_surat.php?id=' . $jenisSuratId);
        exit;
    } elseif ($aksi === 'hapus_sub_jenis' && isset($_POST['id']) && isset($_POST['jenis_surat_id'])) {
        $jenisSuratId = (int) $_POST['jenis_surat_id'];
        $pdo->prepare('DELETE FROM sub_jenis_surat WHERE id = ?')->execute(array((int) $_POST['id']));
        header('Location: jenis_surat.php?id=' . $jenisSuratId);
        exit;
    } elseif ($aksi === 'tambah_peran' && isset($_POST['jenis_surat_id'])) {
        $jenisSuratId = (int) $_POST['jenis_surat_id'];
        $kodePeran  = isset($_POST['kode']) ? trim($_POST['kode']) : '';
        $labelPeran = isset($_POST['label']) ? trim($_POST['label']) : '';
        $wajib = !empty($_POST['wajib']) ? 1 : 0;
        if (auratKodeValid($kodePeran) && $labelPeran !== '') {
            try {
                $pdo->prepare('INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib) VALUES (?, ?, ?, ?)')
                    ->execute(array($jenisSuratId, $kodePeran, $labelPeran, $wajib));
            } catch (\PDOException $e) {
                // kode peran bentrok -> diamkan
            }
        }
        header('Location: jenis_surat.php?id=' . $jenisSuratId);
        exit;
    } elseif ($aksi === 'hapus_peran' && isset($_POST['id']) && isset($_POST['jenis_surat_id'])) {
        $jenisSuratId = (int) $_POST['jenis_surat_id'];
        try {
            $pdo->prepare('DELETE FROM peran_pegawai_surat WHERE id = ?')->execute(array((int) $_POST['id']));
        } catch (\PDOException $e) {
            // FK RESTRICT: masih dipakai template_surat_variabel -> gagal diam-diam
        }
        header('Location: jenis_surat.php?id=' . $jenisSuratId);
        exit;
    }
}

$idDetail = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$detail = $idDetail > 0 ? JenisSuratRepository::muatById($idDetail) : null;

// Buat dropdown "field mana = nomor/tanggal/ringkasan" (ledger surat
// diterbitkan) - daftar semua variabel yang PERNAH terpasang ke SALAH SATU
// template jenis surat ini (lintas sub_jenis), bukan cuma variabel di
// katalog global - biar admin gak milih field yang gak relevan sama sekali.
$variabelUntukLedger = array();
if ($detail) {
    $stmt = $pdo->prepare(
        'SELECT DISTINCT v.kode, v.label FROM variabel_surat v
         JOIN template_surat_variabel tsv ON tsv.variabel_surat_id = v.id
         JOIN template_surat ts ON ts.id = tsv.template_surat_id
         WHERE ts.jenis_surat_id = ? ORDER BY v.kode'
    );
    $stmt->execute(array($idDetail));
    $variabelUntukLedger = $stmt->fetchAll();
}

$daftarJenisSurat = $pdo->query('SELECT * FROM jenis_surat ORDER BY urutan_tampil, nama')->fetchAll();

$halamanAktif = 'admin_jenis_surat';
$judulHalaman = 'Kelola Jenis Surat';
$breadcrumb   = 'Kelola Jenis Surat';
$subJudul     = 'Tambah jenis surat baru, kelola sub-jenis dan peran pegawai — tanpa perlu mengubah kode aplikasi.';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-<?php echo htmlspecialchars((string) $pesanTipe); ?>"><?php echo htmlspecialchars((string) $pesan); ?></div>
<?php endif; ?>

<?php if (!$detail): ?>

  <div class="toolbar">
    <span></span>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('formTambah').style.display='block'">+ Jenis Surat Baru</button>
  </div>

  <div class="form-card" id="formTambah" style="display:none; margin-bottom:20px;">
    <form method="post" action="jenis_surat.php">
        <?php echo Csrf::field(); ?>
      <input type="hidden" name="aksi" value="tambah">
      <div class="grid-2">
        <div class="field">
          <label>Kode <span class="req">*</span></label>
          <input type="text" name="kode" placeholder="mis. surat_tugas, undangan" pattern="[a-z][a-z0-9_]{1,49}" required>
        </div>
        <div class="field">
          <label>Nama <span class="req">*</span></label>
          <input type="text" name="nama" placeholder="mis. Surat Keterangan" required>
        </div>
        <div class="field">
          <label>Kategori</label>
          <select name="kategori">
            <option value="single_dokumen">Satu dokumen</option>
            <option value="dua_dokumen">Beberapa sub-jenis (template berbeda per sub-jenis)</option>
          </select>
        </div>
        <div class="field">
          <label>Ikon</label>
          <select name="icon">
            <?php foreach (IconLibrary::opsi() as $slug => $label): ?>
              <option value="<?php echo htmlspecialchars((string) $slug); ?>"<?php echo $slug === IconLibrary::DEFAULT_SLUG ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Kop Surat</label>
          <input type="text" name="kop_surat" value="standar" placeholder="standar / tanpa_kop / lainnya">
        </div>
        <div class="field">
          <label>Pola Nama Berkas Unduhan</label>
          <input type="text" name="pola_nama_unduhan" placeholder="mis. Surat_Tugas_{nomor_surat}">
        </div>
        <div class="field">
          <label>Urutan Tampil</label>
          <input type="number" name="urutan_tampil" value="0">
        </div>
      </div>
      <div class="field">
        <label>Deskripsi</label>
        <textarea name="deskripsi"></textarea>
      </div>
      <div style="display:flex; gap:10px;">
        <button type="submit" class="btn btn-primary">Simpan &amp; Lanjutkan</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('formTambah').style.display='none'">Batal</button>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Ikon</th><th>Kode</th><th>Nama</th><th>Kategori</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (count($daftarJenisSurat) === 0): ?>
          <tr><td colspan="6" style="text-align:center; color:var(--ink-dim); font-style:italic;">Belum ada jenis surat.</td></tr>
        <?php else: foreach ($daftarJenisSurat as $js): ?>
          <tr>
            <td><span class="letter-icon letter-icon--sm"><?php echo IconLibrary::render($js['icon']); ?></span></td>
            <td class="tnum"><?php echo htmlspecialchars((string) $js['kode']); ?></td>
            <td><a href="jenis_surat.php?id=<?php echo (int) $js['id']; ?>"><?php echo htmlspecialchars((string) $js['nama']); ?></a></td>
            <td><?php echo $js['kategori'] === 'dua_dokumen' ? 'Beberapa sub-jenis' : 'Satu dokumen'; ?></td>
            <td><?php echo ((int) $js['status_aktif'] === 1) ? 'Aktif' : 'Nonaktif'; ?></td>
            <td>
              <form method="post" action="jenis_surat.php" style="display:inline;">
                  <?php echo Csrf::field(); ?>
                <input type="hidden" name="aksi" value="toggle_aktif">
                <input type="hidden" name="id" value="<?php echo (int) $js['id']; ?>">
                <button type="submit" class="btn btn-secondary"><?php echo ((int) $js['status_aktif'] === 1) ? 'Nonaktifkan' : 'Aktifkan'; ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

<?php else: ?>

  <div class="note">
    Kode: <b><?php echo htmlspecialchars((string) $detail['kode']); ?></b> — dipakai di URL <code>surat/index.php?kode=<?php echo htmlspecialchars((string) $detail['kode']); ?></code>.
    <a href="jenis_surat.php">&larr; Kembali ke daftar</a>
  </div>

  <div class="form-card" style="margin-bottom:20px;">
    <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Data Jenis Surat</h4>
    <form method="post" action="jenis_surat.php">
        <?php echo Csrf::field(); ?>
      <input type="hidden" name="aksi" value="ubah">
      <input type="hidden" name="id" value="<?php echo (int) $detail['id']; ?>">
      <div class="grid-2">
        <div class="field">
          <label>Nama <span class="req">*</span></label>
          <input type="text" name="nama" value="<?php echo htmlspecialchars((string) $detail['nama']); ?>" required>
        </div>
        <div class="field">
          <label>Kategori</label>
          <input type="text" value="<?php echo $detail['kategori'] === 'dua_dokumen' ? 'Beberapa sub-jenis' : 'Satu dokumen'; ?>" disabled>
        </div>
        <div class="field">
          <label>Ikon</label>
          <select name="icon">
            <?php foreach (IconLibrary::opsi() as $slug => $label): ?>
              <option value="<?php echo htmlspecialchars((string) $slug); ?>"<?php echo $slug === $detail['icon'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Kop Surat</label>
          <input type="text" name="kop_surat" value="<?php echo htmlspecialchars((string) $detail['kop_surat']); ?>">
        </div>
        <div class="field">
          <label>Pola Nama Berkas Unduhan</label>
          <input type="text" name="pola_nama_unduhan" value="<?php echo htmlspecialchars((string) $detail['pola_nama_unduhan']); ?>" placeholder="mis. Surat_Tugas_{nomor_surat}">
        </div>
        <div class="field">
          <label>Urutan Tampil</label>
          <input type="number" name="urutan_tampil" value="<?php echo (int) $detail['urutan_tampil']; ?>">
        </div>
      </div>
      <div class="field">
        <label>Deskripsi</label>
        <textarea name="deskripsi"><?php echo htmlspecialchars((string) $detail['deskripsi']); ?></textarea>
      </div>

      <h4 style="font-family:var(--display); font-size:0.92rem; margin:20px 0 4px;">Nomor Surat Otomatis</h4>
      <p class="note" style="margin-bottom:12px;">
        Kode klasifikasi jenis surat ini SAJA (mis. "UND.KP3.4.3") - bagian
        TETAP nomor surat yang beda-beda per jenis surat. Nomor lengkap
        tersusun: <code>{nomor urut}/{kode penandatangan}.{kode satker}/{kode ini}/{bulan romawi}/{tahun}</code>
        - kode penandatangan otomatis dari jabatan yang tanda tangan (KPA/WKPA/PAN/SEK),
        kode satker diatur sekali di <a href="pengaturan.php">Pengaturan Aplikasi</a>
        (sama buat semua jenis surat). Kosongkan kalau jenis surat ini
        nomornya diisi manual apa adanya.
      </p>
      <div class="field" style="max-width:400px; margin-bottom:16px;">
        <label>Kode Klasifikasi</label>
        <input type="text" name="kode_klasifikasi" value="<?php echo htmlspecialchars((string) $detail['kode_klasifikasi']); ?>" placeholder="mis. KPA.W15-A8/OT.01">
      </div>

      <h4 style="font-family:var(--display); font-size:0.92rem; margin:20px 0 4px;">Ledger Surat Diterbitkan</h4>
      <p class="note" style="margin-bottom:12px;">
        Pilih field mana yang jadi kolom Nomor/Tanggal/Ringkasan di daftar
        <a href="surat_diterbitkan.php?jenis_surat_id=<?php echo (int) $detail['id']; ?>">Riwayat Surat Diterbitkan</a>
        - opsional, kosongkan kalau gak perlu. Isi form (semua field) tetap
        kerekam lengkap walau ini gak di-set.
      </p>
      <div class="grid-2">
        <?php
        $renderDropdownVariabel = function ($nameField, $labelField, $nilaiSekarang) use ($variabelUntukLedger) {
            echo '<div class="field"><label>' . htmlspecialchars($labelField) . '</label><select name="' . htmlspecialchars($nameField) . '">';
            echo '<option value="">(tidak dipakai)</option>';
            foreach ($variabelUntukLedger as $v) {
                $selected = ($v['kode'] === $nilaiSekarang) ? ' selected' : '';
                echo '<option value="' . htmlspecialchars((string) $v['kode']) . '"' . $selected . '>'
                    . htmlspecialchars((string) $v['label']) . ' (' . htmlspecialchars((string) $v['kode']) . ')</option>';
            }
            echo '</select></div>';
        };
        $renderDropdownVariabel('variabel_nomor_kode', 'Field Nomor', $detail['variabel_nomor_kode']);
        $renderDropdownVariabel('variabel_tanggal_kode', 'Field Tanggal', $detail['variabel_tanggal_kode']);
        $renderDropdownVariabel('variabel_ringkasan_kode', 'Field Ringkasan', $detail['variabel_ringkasan_kode']);
        ?>
      </div>

      <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
    </form>
  </div>

  <?php if ($detail['kategori'] === 'dua_dokumen'): ?>
  <div class="form-card" style="margin-bottom:20px;">
    <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Sub-Jenis</h4>
    <div class="table-wrap" style="margin-bottom:16px;">
      <table>
        <thead><tr><th>Kode</th><th>Label</th><th>Template &amp; Variabel</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($detail['sub_jenis'])): ?>
            <tr><td colspan="4" style="text-align:center; color:var(--ink-dim); font-style:italic;">Belum ada sub-jenis.</td></tr>
          <?php else: foreach ($detail['sub_jenis'] as $sj): ?>
            <tr>
              <td class="tnum"><?php echo htmlspecialchars((string) $sj['kode']); ?></td>
              <td><?php echo htmlspecialchars((string) $sj['label']); ?></td>
              <td><a href="template_surat.php?jenis_surat_id=<?php echo (int) $detail['id']; ?>&amp;sub_jenis_surat_id=<?php echo (int) $sj['id']; ?>">Kelola Template</a></td>
              <td>
                <form method="post" action="jenis_surat.php" style="display:inline;" onsubmit="return confirm('Hapus sub-jenis ini? Template & variabel yang sudah dipasang akan ikut terhapus.');">
                    <?php echo Csrf::field(); ?>
                  <input type="hidden" name="aksi" value="hapus_sub_jenis">
                  <input type="hidden" name="id" value="<?php echo (int) $sj['id']; ?>">
                  <input type="hidden" name="jenis_surat_id" value="<?php echo (int) $detail['id']; ?>">
                  <button type="submit" class="btn btn-secondary">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <form method="post" action="jenis_surat.php">
        <?php echo Csrf::field(); ?>
      <input type="hidden" name="aksi" value="tambah_sub_jenis">
      <input type="hidden" name="jenis_surat_id" value="<?php echo (int) $detail['id']; ?>">
      <div class="grid-2">
        <div class="field"><label>Kode Sub-Jenis</label><input type="text" name="kode" pattern="[a-z][a-z0-9_]{1,49}" required></div>
        <div class="field"><label>Label</label><input type="text" name="label" required></div>
      </div>
      <button type="submit" class="btn btn-secondary">+ Tambah Sub-Jenis</button>
    </form>
  </div>
  <?php else: ?>
  <div class="note">
    <a href="template_surat.php?jenis_surat_id=<?php echo (int) $detail['id']; ?>">Kelola Template &amp; Variabel &rarr;</a>
  </div>
  <?php endif; ?>

  <div class="form-card" style="margin-bottom:20px;">
    <h4 style="font-family:var(--display); font-size:1rem; margin-bottom:16px;">Peran Pegawai</h4>
    <p style="font-size:0.85rem; color:var(--ink-dim); margin-bottom:16px;">Slot pemilih SATU pegawai yang tampil di formulir, mis. "Pemohon", "Pejabat yang Menetapkan".</p>
    <div class="table-wrap" style="margin-bottom:16px;">
      <table>
        <thead><tr><th>Kode</th><th>Label</th><th>Wajib</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($detail['peran_pegawai'])): ?>
            <tr><td colspan="4" style="text-align:center; color:var(--ink-dim); font-style:italic;">Belum ada peran pegawai.</td></tr>
          <?php else: foreach ($detail['peran_pegawai'] as $pp): ?>
            <tr>
              <td class="tnum"><?php echo htmlspecialchars((string) $pp['kode']); ?></td>
              <td><?php echo htmlspecialchars((string) $pp['label']); ?></td>
              <td><?php echo ((int) $pp['wajib'] === 1) ? 'Ya' : 'Tidak'; ?></td>
              <td>
                <form method="post" action="jenis_surat.php" style="display:inline;" onsubmit="return confirm('Hapus peran ini? Gagal kalau masih dipakai variabel di suatu template.');">
                    <?php echo Csrf::field(); ?>
                  <input type="hidden" name="aksi" value="hapus_peran">
                  <input type="hidden" name="id" value="<?php echo (int) $pp['id']; ?>">
                  <input type="hidden" name="jenis_surat_id" value="<?php echo (int) $detail['id']; ?>">
                  <button type="submit" class="btn btn-secondary">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <form method="post" action="jenis_surat.php">
        <?php echo Csrf::field(); ?>
      <input type="hidden" name="aksi" value="tambah_peran">
      <input type="hidden" name="jenis_surat_id" value="<?php echo (int) $detail['id']; ?>">
      <div class="grid-2">
        <div class="field"><label>Kode Peran</label><input type="text" name="kode" placeholder="mis. pemohon" pattern="[a-z][a-z0-9_]{1,49}" required></div>
        <div class="field"><label>Label</label><input type="text" name="label" placeholder="mis. Pegawai Pemohon" required></div>
      </div>
      <div class="field" style="flex-direction:row; align-items:center; gap:8px;">
        <input type="checkbox" name="wajib" id="peranWajib" value="1" checked style="width:auto;">
        <label for="peranWajib" style="margin:0;">Wajib diisi</label>
      </div>
      <button type="submit" class="btn btn-secondary">+ Tambah Peran</button>
    </form>
  </div>

  <div class="note">
    <a href="blok_tabel.php?jenis_surat_id=<?php echo (int) $detail['id']; ?>">Kelola Blok Tabel (daftar berulang, mis. lampiran pegawai) &rarr;</a>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
