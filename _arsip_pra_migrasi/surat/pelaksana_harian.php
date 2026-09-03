<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Database;
use Aurat\JenisSurat;
use Aurat\Formatter;
use Aurat\DocxGenerator;

Auth::requireLogin();

$konfigurasi = JenisSurat::muat('pelaksana_harian');
$pesanError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomorSurat        = isset($_POST['nomor_surat']) ? trim($_POST['nomor_surat']) : '';
    $jabatanDiplh       = isset($_POST['jabatan_diplh']) ? trim($_POST['jabatan_diplh']) : '';
    $tanggalPelaksanaan = isset($_POST['tanggal_pelaksanaan']) ? trim($_POST['tanggal_pelaksanaan']) : '';
    $alasan             = isset($_POST['alasan']) ? trim($_POST['alasan']) : '';
    $dasarSuratTugas     = isset($_POST['dasar_surat_tugas']) ? trim($_POST['dasar_surat_tugas']) : '';
    $menunjukId          = isset($_POST['menunjuk_id']) ? (int) $_POST['menunjuk_id'] : 0;
    $ditunjukId          = isset($_POST['ditunjuk_id']) ? (int) $_POST['ditunjuk_id'] : 0;

    if ($nomorSurat === '' || $jabatanDiplh === '' || $tanggalPelaksanaan === '' || $menunjukId === 0 || $ditunjukId === 0) {
        $pesanError = 'Semua field bertanda * wajib diisi, termasuk kedua pejabat.';
    } else {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM pegawai WHERE id IN (?, ?) AND status_aktif = 1');
        $stmt->execute(array($menunjukId, $ditunjukId));
        $hasil = array();
        foreach ($stmt->fetchAll() as $row) {
            $hasil[$row['id']] = $row;
        }

        if (!isset($hasil[$menunjukId]) || !isset($hasil[$ditunjukId])) {
            $pesanError = 'Salah satu pejabat yang dipilih tidak ditemukan. Silakan cari ulang.';
        } else {
            $menunjuk = $hasil[$menunjukId];
            $ditunjuk = $hasil[$ditunjukId];

            $alasanFinal = $alasan !== '' ? $alasan : 'melaksanakan dinas';
            $klausaDasar = $dasarSuratTugas !== ''
                ? ' berdasarkan surat tugas nomor ' . $dasarSuratTugas . '.'
                : '.';
            $narasiPenunjukan = 'Ditunjuk sebagai Pelaksana tugas harian (Plh) ' . $jabatanDiplh
                . ' pada tanggal ' . Formatter::tanggalIndonesia($tanggalPelaksanaan)
                . ' karena ' . $jabatanDiplh . ' ' . $alasanFinal . $klausaDasar;

            $nilai = array(
                'nomor_surat'             => $nomorSurat,
                'tanggal_surat'           => Formatter::tanggalIndonesia(date('Y-m-d')),
                'menunjuk_nama_lengkap'   => Formatter::namaBergelar($menunjuk),
                'menunjuk_nip'            => $menunjuk['nip'],
                'menunjuk_pangkat_golongan' => Formatter::pangkatGolongan($menunjuk),
                'menunjuk_jabatan'        => $menunjuk['jabatan'],
                'ditunjuk_nama_lengkap'   => Formatter::namaBergelar($ditunjuk),
                'ditunjuk_nip'            => $ditunjuk['nip'],
                'ditunjuk_pangkat_golongan' => Formatter::pangkatGolongan($ditunjuk),
                'ditunjuk_jabatan'        => $ditunjuk['jabatan'],
                'narasi_penunjukan'       => $narasiPenunjukan,
            );

            $namaUnduhan = 'Surat_Penunjukan_Plh_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $ditunjuk['nama_lengkap']) . '.docx';

            try {
                DocxGenerator::generateDanUnduh($konfigurasi['template_file'], $nilai, array(), $namaUnduhan);
                exit;
            } catch (\RuntimeException $e) {
                $pesanError = $e->getMessage();
            }
        }
    }
}

$halamanAktif = 'pelaksana_harian';
$judulHalaman = 'Surat Penunjukan Pelaksana Harian';
$breadcrumb   = 'Buat Surat';
$subJudul     = '';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<div class="note"><b>Catatan:</b> nomor surat diisi manual. Kosongkan "Alasan" untuk memakai teks default "melaksanakan dinas".</div>

<?php if ($pesanError !== ''): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars($pesanError); ?></div>
<?php endif; ?>

<div class="form-card">
  <form method="post" action="pelaksana_harian.php" id="formPlh">
    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Pejabat yang Menunjuk</h4>
      <div class="field">
        <label>Cari nama atau NIP <span class="req">*</span></label>
        <input type="text" class="picker-input" data-peran="menunjuk" placeholder="Ketik nama pegawai&hellip;" autocomplete="off">
        <input type="hidden" name="menunjuk_id" id="id_menunjuk" required>
        <div class="picker-results" id="hasil_menunjuk"></div>
      </div>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Pejabat yang Ditunjuk (Plh)</h4>
      <div class="field">
        <label>Cari nama atau NIP <span class="req">*</span></label>
        <input type="text" class="picker-input" data-peran="ditunjuk" placeholder="Ketik nama pegawai&hellip;" autocomplete="off">
        <input type="hidden" name="ditunjuk_id" id="id_ditunjuk" required>
        <div class="picker-results" id="hasil_ditunjuk"></div>
      </div>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Rincian Penunjukan</h4>
      <div class="grid-2">
        <div class="field">
          <label>Nomor Surat <span class="req">*</span></label>
          <input type="text" name="nomor_surat" required>
        </div>
        <div class="field">
          <label>Tanggal Pelaksanaan <span class="req">*</span></label>
          <input type="date" name="tanggal_pelaksanaan" required>
        </div>
      </div>
      <div class="field">
        <label>Jabatan yang Di-Plh-kan (lengkap, mis. Sekretaris Pengadilan Agama Rantau) <span class="req">*</span></label>
        <input type="text" name="jabatan_diplh" required>
      </div>
      <div class="grid-2">
        <div class="field">
          <label>Alasan — kosongkan untuk pakai default "melaksanakan dinas"</label>
          <input type="text" name="alasan" placeholder="mis. mengikuti pendidikan dan pelatihan">
        </div>
        <div class="field">
          <label>Dasar (No. &amp; Tgl. Surat Tugas)</label>
          <input type="text" name="dasar_surat_tugas" placeholder="mis. 1387/PTA.W15-A8/ST.KP7.1/VII/2026 tanggal 14 Juli 2026">
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Unduh Dokumen (.docx)</button>
  </form>
</div>

<script>
(function(){
  ['menunjuk', 'ditunjuk'].forEach(function(kode){
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

  document.getElementById('formPlh').addEventListener('submit', function(e){
    if (!document.getElementById('id_menunjuk').value || !document.getElementById('id_ditunjuk').value) {
      e.preventDefault();
      alert('Pilih kedua pejabat terlebih dahulu.');
    }
  });
})();
</script>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
