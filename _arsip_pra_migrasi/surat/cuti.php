<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Database;
use Aurat\JenisSurat;
use Aurat\Formatter;
use Aurat\DocxGenerator;

Auth::requireLogin();

$konfigurasi = JenisSurat::muat('cuti');
$pesanError = '';

$opsiJenisCuti = array();
foreach ($konfigurasi['field_umum'] as $f) {
    if ($f['kode'] === 'jenis_cuti') {
        $opsiJenisCuti = $f['opsi'];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pegawaiId      = isset($_POST['pegawai_id']) ? (int) $_POST['pegawai_id'] : 0;
    $nomorSurat     = isset($_POST['nomor_surat']) ? trim($_POST['nomor_surat']) : '';
    $jenisCuti      = isset($_POST['jenis_cuti']) ? trim($_POST['jenis_cuti']) : '';
    $tanggalMulai   = isset($_POST['tanggal_mulai']) ? trim($_POST['tanggal_mulai']) : '';
    $tanggalSelesai = isset($_POST['tanggal_selesai']) ? trim($_POST['tanggal_selesai']) : '';
    $alamatCuti     = isset($_POST['alamat_cuti']) ? trim($_POST['alamat_cuti']) : '';
    $alasan         = isset($_POST['alasan']) ? trim($_POST['alasan']) : '';

    if ($pegawaiId === 0 || $nomorSurat === '' || $jenisCuti === '' || $tanggalMulai === '' || $tanggalSelesai === '') {
        $pesanError = 'Pegawai, Nomor Surat, Jenis Cuti, Tanggal Mulai, dan Tanggal Selesai wajib diisi.';
    } else {
        $stmt = Database::pdo()->prepare('SELECT * FROM pegawai WHERE id = ? AND status_aktif = 1');
        $stmt->execute(array($pegawaiId));
        $pegawai = $stmt->fetch();

        if (!$pegawai) {
            $pesanError = 'Pegawai yang dipilih tidak ditemukan. Silakan cari ulang.';
        } else {
            $lamaHari = (int) ((strtotime($tanggalSelesai) - strtotime($tanggalMulai)) / 86400) + 1;

            $nilai = array(
                'pemohon_nip'            => $pegawai['nip'],
                'pemohon_nama_lengkap'   => Formatter::namaBergelar($pegawai),
                'pemohon_pangkat'        => $pegawai['pangkat'],
                'pemohon_golongan_ruang' => $pegawai['golongan_ruang'],
                'pemohon_jabatan'        => $pegawai['jabatan'],
                'pemohon_unit_kerja'     => $pegawai['unit_kerja'],
                'nomor_surat'            => $nomorSurat,
                'tanggal_surat'          => Formatter::tanggalIndonesia(date('Y-m-d')),
                'jenis_cuti'             => $jenisCuti,
                'tanggal_mulai'          => Formatter::tanggalIndonesia($tanggalMulai),
                'tanggal_selesai'        => Formatter::tanggalIndonesia($tanggalSelesai),
                'lama_cuti_hari'         => $lamaHari > 0 ? $lamaHari : 0,
                'alamat_cuti'            => $alamatCuti,
                'alasan'                 => $alasan,
            );

            $namaUnduhan = 'Surat_Cuti_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $pegawai['nama_lengkap']) . '.docx';

            try {
                DocxGenerator::generateDanUnduh($konfigurasi['template_file'], $nilai, array(), $namaUnduhan);
                exit; // generateDanUnduh sudah exit setelah stream; baris ini jaga-jaga.
            } catch (\RuntimeException $e) {
                $pesanError = $e->getMessage();
            }
        }
    }
}

$halamanAktif = 'cuti';
$judulHalaman = 'Surat Cuti';
$breadcrumb   = 'Buat Surat';
$subJudul     = '';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<div class="note"><b>Catatan:</b> nomor surat diisi manual dari aplikasi penomoran. Dokumen dibuat saat diunduh, tidak disimpan di server.</div>

<?php if ($pesanError !== ''): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars($pesanError); ?></div>
<?php endif; ?>

<div class="form-card">
  <form method="post" action="cuti.php" id="formCuti">
    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Pegawai</h4>
      <div class="field">
        <label>Cari nama atau NIP <span class="req">*</span></label>
        <input type="text" id="pegawaiCari" placeholder="Ketik nama pegawai&hellip;" autocomplete="off">
        <div class="picker-results" id="pegawaiHasil"></div>
      </div>
      <input type="hidden" name="pegawai_id" id="pegawaiId" required>
      <div id="pegawaiTerpilih"></div>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Rincian Cuti</h4>
      <div class="grid-2">
        <div class="field">
          <label>Nomor Surat <span class="req">*</span></label>
          <input type="text" name="nomor_surat" required>
        </div>
        <div class="field">
          <label>Jenis Cuti <span class="req">*</span></label>
          <select name="jenis_cuti" required>
            <?php foreach ($opsiJenisCuti as $opsi): ?>
              <option value="<?php echo htmlspecialchars($opsi); ?>"><?php echo htmlspecialchars($opsi); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Tanggal Mulai <span class="req">*</span></label>
          <input type="date" name="tanggal_mulai" required>
        </div>
        <div class="field">
          <label>Tanggal Selesai <span class="req">*</span></label>
          <input type="date" name="tanggal_selesai" required>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Lainnya</h4>
      <div class="field">
        <label>Alamat Selama Cuti</label>
        <input type="text" name="alamat_cuti">
      </div>
      <div class="field">
        <label>Alasan</label>
        <textarea name="alasan"></textarea>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Unduh Dokumen (.docx)</button>
  </form>
</div>

<script>
(function(){
  var input = document.getElementById('pegawaiCari');
  var hasilBox = document.getElementById('pegawaiHasil');
  var idField = document.getElementById('pegawaiId');
  var terpilihBox = document.getElementById('pegawaiTerpilih');
  var timer = null;

  input.addEventListener('input', function(){
    var q = input.value.trim();
    idField.value = '';
    clearTimeout(timer);
    if (q.length < 2) { hasilBox.style.display = 'none'; return; }
    timer = setTimeout(function(){
      fetch('../api/pegawai_cari.php?q=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(data){
          hasilBox.innerHTML = '';
          if (!data.length) { hasilBox.style.display = 'none'; return; }
          data.forEach(function(p){
            var row = document.createElement('div');
            row.className = 'picker-row';
            row.innerHTML = '<div><b></b><span></span></div>';
            row.querySelector('b').textContent = p.nama_lengkap;
            row.querySelector('span').textContent = p.nip + ' · ' + (p.jabatan || '');
            row.addEventListener('click', function(){
              idField.value = p.id;
              input.value = p.nama_lengkap;
              hasilBox.style.display = 'none';
              terpilihBox.innerHTML = '<div class="alert alert-info">' + p.nama_lengkap + ' &mdash; ' + p.nip + '</div>';
            });
            hasilBox.appendChild(row);
          });
          hasilBox.style.display = 'block';
        });
    }, 200);
  });

  document.addEventListener('click', function(e){
    if (e.target !== input) hasilBox.style.display = 'none';
  });

  document.getElementById('formCuti').addEventListener('submit', function(e){
    if (!idField.value) {
      e.preventDefault();
      alert('Pilih pegawai dari hasil pencarian terlebih dahulu.');
    }
  });
})();
</script>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
