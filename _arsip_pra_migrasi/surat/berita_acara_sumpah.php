<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Database;
use Aurat\JenisSurat;
use Aurat\Formatter;
use Aurat\DocxGenerator;

Auth::requireLogin();

$konfigurasi = JenisSurat::muat('berita_acara_sumpah');
$pesanError = '';

$peranDaftar = array('pengambil_sumpah', 'disumpah', 'saksi_1', 'saksi_2', 'rohaniawan');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomorSurat      = isset($_POST['nomor_surat']) ? trim($_POST['nomor_surat']) : '';
    $hariTanggal     = isset($_POST['hari_tanggal']) ? trim($_POST['hari_tanggal']) : '';
    $dasarSkNomor    = isset($_POST['dasar_sk_nomor']) ? trim($_POST['dasar_sk_nomor']) : '';
    $dasarSkTanggal  = isset($_POST['dasar_sk_tanggal']) ? trim($_POST['dasar_sk_tanggal']) : '';

    $peranId = array();
    foreach ($peranDaftar as $kode) {
        $peranId[$kode] = isset($_POST['peran_' . $kode]) ? (int) $_POST['peran_' . $kode] : 0;
    }

    $semuaTerisi = $nomorSurat !== '' && $hariTanggal !== '' && $dasarSkNomor !== '' && $dasarSkTanggal !== '';
    foreach ($peranId as $id) {
        if ($id === 0) { $semuaTerisi = false; }
    }

    if (!$semuaTerisi) {
        $pesanError = 'Semua field dan kelima peran pegawai wajib diisi.';
    } else {
        $pdo = Database::pdo();
        $placeholders = implode(',', array_fill(0, count($peranId), '?'));
        $stmt = $pdo->prepare("SELECT * FROM pegawai WHERE id IN ($placeholders) AND status_aktif = 1");
        $stmt->execute(array_values(array_map('intval', $peranId)));
        $pegawaiTerambil = array();
        foreach ($stmt->fetchAll() as $row) {
            $pegawaiTerambil[$row['id']] = $row;
        }

        $lengkap = true;
        $pegawaiPeran = array();
        foreach ($peranId as $kode => $id) {
            if (!isset($pegawaiTerambil[$id])) {
                $lengkap = false;
                break;
            }
            $pegawaiPeran[$kode] = $pegawaiTerambil[$id];
        }

        if (!$lengkap) {
            $pesanError = 'Salah satu pegawai yang dipilih tidak ditemukan. Silakan cari ulang.';
        } else {
            $nilai = array('nomor_surat' => $nomorSurat);
            $nilai['pembukaan_tanggal'] = Formatter::tanggalNaratif($hariTanggal);
            $nilai['dasar_sk_nomor']    = $dasarSkNomor;
            $nilai['dasar_sk_tanggal']  = Formatter::tanggalIndonesia($dasarSkTanggal);

            foreach ($pegawaiPeran as $kode => $p) {
                $nilai[$kode . '_nama_lengkap']    = Formatter::namaBergelar($p);
                $nilai[$kode . '_nip']             = $p['nip'];
                $nilai[$kode . '_pangkat_golongan'] = Formatter::pangkatGolongan($p);
            }
            $nilai['pengambil_sumpah_jabatan'] = $pegawaiPeran['pengambil_sumpah']['jabatan'];

            $namaUnduhan = 'Berita_Acara_Sumpah_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $pegawaiPeran['disumpah']['nama_lengkap']) . '.docx';

            try {
                DocxGenerator::generateDanUnduh($konfigurasi['template_file'], $nilai, array(), $namaUnduhan);
                exit;
            } catch (\RuntimeException $e) {
                $pesanError = $e->getMessage();
            }
        }
    }
}

$halamanAktif = 'berita_acara_sumpah';
$judulHalaman = 'Berita Acara Pengambilan Sumpah';
$breadcrumb   = 'Buat Surat';
$subJudul     = '';
$rootAsset    = '../';

$labelPeran = array(
    'pengambil_sumpah' => 'Pejabat yang Mengambil Sumpah',
    'disumpah'         => 'PNS yang Disumpah',
    'saksi_1'          => 'Saksi 1',
    'saksi_2'          => 'Saksi 2',
    'rohaniawan'       => 'Rohaniawan Pendamping',
);

require __DIR__ . '/../views/layout_atas.php';
?>

<div class="note"><b>Catatan:</b> nomor surat diisi manual. Dokumen ini tanpa kop surat (bersifat personal/internal). Kelima peran di bawah wajib diisi dari data pegawai.</div>

<?php if ($pesanError !== ''): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars($pesanError); ?></div>
<?php endif; ?>

<div class="form-card">
  <form method="post" action="berita_acara_sumpah.php" id="formBerita">
    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Pihak yang Terlibat</h4>
      <?php foreach ($labelPeran as $kode => $label): ?>
        <div class="field">
          <label><?php echo htmlspecialchars($label); ?> <span class="req">*</span></label>
          <input type="text" class="picker-input" data-peran="<?php echo $kode; ?>" placeholder="Ketik nama pegawai&hellip;" autocomplete="off">
          <input type="hidden" name="peran_<?php echo $kode; ?>" id="id_<?php echo $kode; ?>" required>
          <div class="picker-results" id="hasil_<?php echo $kode; ?>"></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Rincian</h4>
      <div class="grid-2">
        <div class="field">
          <label>Nomor Surat <span class="req">*</span></label>
          <input type="text" name="nomor_surat" required>
        </div>
        <div class="field">
          <label>Hari, Tanggal Pengambilan Sumpah <span class="req">*</span></label>
          <input type="date" name="hari_tanggal" required>
        </div>
        <div class="field">
          <label>Nomor SK Pengangkatan <span class="req">*</span></label>
          <input type="text" name="dasar_sk_nomor" required>
        </div>
        <div class="field">
          <label>Tanggal SK Pengangkatan <span class="req">*</span></label>
          <input type="date" name="dasar_sk_tanggal" required>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Unduh Dokumen (.docx)</button>
  </form>
</div>

<script>
(function(){
  var peranList = <?php echo json_encode(array_keys($labelPeran)); ?>;

  peranList.forEach(function(kode){
    var input = document.querySelector('.picker-input[data-peran="' + kode + '"]');
    var hasilBox = document.getElementById('hasil_' + kode);
    var idField = document.getElementById('id_' + kode);
    var timer = null;

    input.addEventListener('input', function(){
      idField.value = '';
      var q = input.value.trim();
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
  });

  document.getElementById('formBerita').addEventListener('submit', function(e){
    var kurang = peranList.some(function(kode){
      return !document.getElementById('id_' + kode).value;
    });
    if (kurang) {
      e.preventDefault();
      alert('Pilih pegawai untuk setiap peran terlebih dahulu.');
    }
  });
})();
</script>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
