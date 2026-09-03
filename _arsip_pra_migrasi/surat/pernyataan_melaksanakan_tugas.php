<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Database;
use Aurat\JenisSurat;
use Aurat\Formatter;
use Aurat\DocxGenerator;

Auth::requireLogin();

$konfigurasi = JenisSurat::muat('pernyataan_melaksanakan_tugas');
$pesanError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomorSurat       = isset($_POST['nomor_surat']) ? trim($_POST['nomor_surat']) : '';
    $dasarSkNomor     = isset($_POST['dasar_sk_nomor']) ? trim($_POST['dasar_sk_nomor']) : '';
    $dasarSkTanggal   = isset($_POST['dasar_sk_tanggal']) ? trim($_POST['dasar_sk_tanggal']) : '';
    $tmt              = isset($_POST['tmt']) ? trim($_POST['tmt']) : '';
    $instansi         = isset($_POST['instansi']) ? trim($_POST['instansi']) : '';
    $besaranTunjangan = isset($_POST['besaran_tunjangan']) ? trim($_POST['besaran_tunjangan']) : '';
    $tujuanSurat      = isset($_POST['tujuan_surat']) ? trim($_POST['tujuan_surat']) : '';
    $menyatakanId     = isset($_POST['menyatakan_id']) ? (int) $_POST['menyatakan_id'] : 0;
    $dinyatakanId     = isset($_POST['dinyatakan_id']) ? (int) $_POST['dinyatakan_id'] : 0;

    if ($nomorSurat === '' || $dasarSkNomor === '' || $dasarSkTanggal === '' || $tmt === ''
        || $instansi === '' || $tujuanSurat === '' || $menyatakanId === 0 || $dinyatakanId === 0) {
        $pesanError = 'Semua field bertanda * wajib diisi, termasuk kedua pegawai.';
    } else {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM pegawai WHERE id IN (?, ?) AND status_aktif = 1');
        $stmt->execute(array($menyatakanId, $dinyatakanId));
        $hasil = array();
        foreach ($stmt->fetchAll() as $row) {
            $hasil[$row['id']] = $row;
        }

        if (!isset($hasil[$menyatakanId]) || !isset($hasil[$dinyatakanId])) {
            $pesanError = 'Salah satu pegawai yang dipilih tidak ditemukan. Silakan cari ulang.';
        } else {
            $menyatakan = $hasil[$menyatakanId];
            $dinyatakan = $hasil[$dinyatakanId];

            $angkaTunjangan = (int) preg_replace('/[^0-9]/', '', $besaranTunjangan);
            if ($angkaTunjangan > 0) {
                $formatRupiah = 'Rp' . number_format($angkaTunjangan, 0, ',', '.') . ',-';
                $terbilangRupiah = ucwords(Formatter::terbilang($angkaTunjangan)) . ' Rupiah';
                $klausaTunjangan = ' dan berdasarkan Peraturan Presiden RI nomor 24 tahun 2007 yang bersangkutan diberi tunjangan umum sebesar '
                    . $formatRupiah . ' (' . $terbilangRupiah . ').';
            } else {
                $klausaTunjangan = '.';
            }

            $narasi = 'Berdasarkan Petikan Keputusan Sekretaris Mahkamah Agung Republik Indonesia Nomor: '
                . $dasarSkNomor . ' tanggal ' . Formatter::tanggalIndonesia($dasarSkTanggal)
                . ' terhitung mulai tanggal ' . Formatter::tanggalIndonesia($tmt)
                . ' telah nyata melaksanakan tugasnya sebagai Pegawai Negeri Sipil pada ' . $instansi
                . $klausaTunjangan;

            $nilai = array(
                'nomor_surat'               => $nomorSurat,
                'tanggal_surat'             => Formatter::tanggalIndonesia(date('Y-m-d')),
                'menyatakan_nama_lengkap'   => Formatter::namaBergelar($menyatakan),
                'menyatakan_nip'            => $menyatakan['nip'],
                'menyatakan_pangkat_golongan' => Formatter::pangkatGolongan($menyatakan),
                'menyatakan_jabatan'        => $menyatakan['jabatan'],
                'dinyatakan_nama_lengkap'   => Formatter::namaBergelar($dinyatakan),
                'dinyatakan_nip'            => $dinyatakan['nip'],
                'dinyatakan_pangkat_golongan' => Formatter::pangkatGolongan($dinyatakan),
                'dinyatakan_jabatan'        => $dinyatakan['jabatan'],
                'narasi_pelaksanaan_tugas'  => $narasi,
                'tujuan_surat'              => $tujuanSurat,
            );

            $namaUnduhan = 'SPMT_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $dinyatakan['nama_lengkap']) . '.docx';

            try {
                DocxGenerator::generateDanUnduh($konfigurasi['template_file'], $nilai, array(), $namaUnduhan);
                exit;
            } catch (\RuntimeException $e) {
                $pesanError = $e->getMessage();
            }
        }
    }
}

$halamanAktif = 'pernyataan_melaksanakan_tugas';
$judulHalaman = 'Surat Pernyataan Melaksanakan Tugas';
$breadcrumb   = 'Buat Surat';
$subJudul     = '';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<div class="note"><b>Catatan:</b> nomor surat diisi manual. Kosongkan "Besaran Tunjangan" jika tidak ada tunjangan yang disebutkan — kalimat pembiayaan otomatis hilang.</div>

<?php if ($pesanError !== ''): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars($pesanError); ?></div>
<?php endif; ?>

<div class="form-card">
  <form method="post" action="pernyataan_melaksanakan_tugas.php" id="formSpmt">
    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Pejabat yang Menyatakan</h4>
      <div class="field">
        <label>Cari nama atau NIP <span class="req">*</span></label>
        <input type="text" class="picker-input" data-peran="menyatakan" placeholder="Ketik nama pegawai&hellip;" autocomplete="off">
        <input type="hidden" name="menyatakan_id" id="id_menyatakan" required>
        <div class="picker-results" id="hasil_menyatakan"></div>
      </div>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Pegawai yang Dinyatakan</h4>
      <div class="field">
        <label>Cari nama atau NIP <span class="req">*</span></label>
        <input type="text" class="picker-input" data-peran="dinyatakan" placeholder="Ketik nama pegawai&hellip;" autocomplete="off">
        <input type="hidden" name="dinyatakan_id" id="id_dinyatakan" required>
        <div class="picker-results" id="hasil_dinyatakan"></div>
      </div>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Rincian</h4>
      <div class="grid-2">
        <div class="field">
          <label>Nomor Surat <span class="req">*</span></label>
          <input type="text" name="nomor_surat" required>
        </div>
        <div class="field">
          <label>Instansi / Satuan Kerja <span class="req">*</span></label>
          <input type="text" name="instansi" placeholder="mis. Pengadilan Agama Rantau" required>
        </div>
        <div class="field">
          <label>Nomor SK Dasar <span class="req">*</span></label>
          <input type="text" name="dasar_sk_nomor" required>
        </div>
        <div class="field">
          <label>Tanggal SK Dasar <span class="req">*</span></label>
          <input type="date" name="dasar_sk_tanggal" required>
        </div>
        <div class="field">
          <label>Terhitung Mulai Tanggal (TMT) <span class="req">*</span></label>
          <input type="date" name="tmt" required>
        </div>
        <div class="field">
          <label>Besaran Tunjangan — angka saja, mis. 185000</label>
          <input type="text" name="besaran_tunjangan" placeholder="kosongkan jika tidak ada">
        </div>
      </div>
      <div class="field">
        <label>Ditujukan Kepada <span class="req">*</span></label>
        <input type="text" name="tujuan_surat" placeholder="mis. Kepala Kantor Pelayanan Perbendaharaan Negara Barabai" required>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Unduh Dokumen (.docx)</button>
  </form>
</div>

<script>
(function(){
  ['menyatakan', 'dinyatakan'].forEach(function(kode){
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

  document.getElementById('formSpmt').addEventListener('submit', function(e){
    if (!document.getElementById('id_menyatakan').value || !document.getElementById('id_dinyatakan').value) {
      e.preventDefault();
      alert('Pilih kedua pegawai terlebih dahulu.');
    }
  });
})();
</script>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
