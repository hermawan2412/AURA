<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Database;
use Aurat\JenisSurat;
use Aurat\Formatter;
use Aurat\DocxGenerator;

Auth::requireLogin();

$konfigurasi = JenisSurat::muat('surat_tugas');
$pesanError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomorSurat     = isset($_POST['nomor_surat']) ? trim($_POST['nomor_surat']) : '';
    $dasarUndangan  = isset($_POST['dasar_undangan']) ? trim($_POST['dasar_undangan']) : '';
    $uraianTugas    = isset($_POST['uraian_tugas']) ? trim($_POST['uraian_tugas']) : '';
    $sumberAnggaran = isset($_POST['sumber_anggaran']) ? trim($_POST['sumber_anggaran']) : '';
    $pegawaiIds     = isset($_POST['pegawai_id']) && is_array($_POST['pegawai_id']) ? $_POST['pegawai_id'] : array();
    $tanggalBaris   = isset($_POST['tanggal_baris']) && is_array($_POST['tanggal_baris']) ? $_POST['tanggal_baris'] : array();

    if ($nomorSurat === '' || $uraianTugas === '' || count($pegawaiIds) === 0) {
        $pesanError = 'Nomor Surat, Uraian Tugas, dan minimal satu pegawai wajib diisi.';
    } else {
        $placeholders = implode(',', array_fill(0, count($pegawaiIds), '?'));
        $stmt = Database::pdo()->prepare("SELECT * FROM pegawai WHERE id IN ($placeholders) AND status_aktif = 1");
        $stmt->execute(array_map('intval', $pegawaiIds));
        $pegawaiTerpilih = array();
        foreach ($stmt->fetchAll() as $row) {
            $pegawaiTerpilih[$row['id']] = $row;
        }

        $barisTabel = array();
        foreach ($pegawaiIds as $idx => $pid) {
            $pid = (int) $pid;
            if (!isset($pegawaiTerpilih[$pid])) {
                continue;
            }
            $p = $pegawaiTerpilih[$pid];
            $barisTabel[] = array(
                'no'             => count($barisTabel) + 1,
                'nama'           => Formatter::namaBergelar($p),
                'nip'            => $p['nip'],
                'jabatan_satker' => trim($p['jabatan'] . ' — ' . $p['unit_kerja'], ' —'),
                'tanggal'        => isset($tanggalBaris[$idx]) ? trim($tanggalBaris[$idx]) : '',
            );
        }

        if (count($barisTabel) === 0) {
            $pesanError = 'Pegawai yang dipilih tidak ditemukan. Silakan cari ulang.';
        } else {
            $dasarUndanganKlausa = $dasarUndangan !== ''
                ? 'Berdasarkan Surat Undangan Nomor : ' . $dasarUndangan . '.'
                : '';
            $infoAnggaran = $sumberAnggaran !== ''
                ? 'Segala biaya yang ditimbulkan selama pelaksanaan kegiatan ini dibebankan kepada ' . $sumberAnggaran . '.'
                : '';

            $nilai = array(
                'nomor_surat'          => $nomorSurat,
                'tanggal_surat'        => Formatter::tanggalIndonesia(date('Y-m-d')),
                'dasar_undangan_klausa' => $dasarUndanganKlausa,
                'uraian_tugas'         => $uraianTugas,
                'info_anggaran'        => $infoAnggaran,
            );

            $tabel = array('no' => $barisTabel);
            $namaUnduhan = 'Surat_Tugas_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $nomorSurat) . '.docx';

            try {
                DocxGenerator::generateDanUnduh($konfigurasi['template_file'], $nilai, $tabel, $namaUnduhan);
                exit;
            } catch (\RuntimeException $e) {
                $pesanError = $e->getMessage();
            }
        }
    }
}

$halamanAktif = 'surat_tugas';
$judulHalaman = 'Surat Tugas';
$breadcrumb   = 'Buat Surat';
$subJudul     = '';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<div class="note"><b>Catatan:</b> nomor surat diisi manual. Kosongkan "Sumber Anggaran" kalau surat tugas ini tanpa pembebanan DIPA (Non-SPD) — lampiran SPD-nya sendiri dibuat terpisah oleh bendahara, di luar aplikasi ini.</div>

<?php if ($pesanError !== ''): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars($pesanError); ?></div>
<?php endif; ?>

<div class="form-card">
  <form method="post" action="surat_tugas.php" id="formTugas">
    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Pegawai yang Ditugaskan</h4>
      <div class="field">
        <label>Cari nama atau NIP <span class="req">*</span></label>
        <input type="text" id="pegawaiCari" placeholder="Ketik nama pegawai&hellip; (bisa lebih dari satu)" autocomplete="off">
        <div class="picker-results" id="pegawaiHasil"></div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>No</th><th>Nama</th><th>NIP</th><th>Jabatan / Satuan Kerja</th><th>Tanggal</th><th></th></tr>
          </thead>
          <tbody id="tabelTerpilih">
            <tr id="barisKosong"><td colspan="6" style="text-align:center; color:var(--ink-dim); font-style:italic;">Belum ada pegawai dipilih.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Rincian Penugasan</h4>
      <div class="grid-2">
        <div class="field">
          <label>Nomor Surat <span class="req">*</span></label>
          <input type="text" name="nomor_surat" required>
        </div>
        <div class="field">
          <label>Dasar (No. &amp; Tgl. Surat Undangan)</label>
          <input type="text" name="dasar_undangan" placeholder="mis. 345/KPTA.W15-A/UND.HM3.1.3/I/2026 Tanggal 22 Januari 2026">
        </div>
      </div>
      <div class="field">
        <label>Uraian Tugas <span class="req">*</span></label>
        <textarea name="uraian_tugas" placeholder="mis. Untuk mengikuti Pelaksanaan Rapat Koordinasi di ..."></textarea>
      </div>
      <div class="field">
        <label>Sumber Anggaran (DIPA) — kosongkan jika tanpa SPD</label>
        <input type="text" name="sumber_anggaran" placeholder="mis. DIPA Pengadilan Agama Rantau Tahun Anggaran 2026 Nomor: SP DIPA-005.01.2.402525/2026">
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Unduh Dokumen (.docx)</button>
  </form>
</div>

<script>
(function(){
  var input = document.getElementById('pegawaiCari');
  var hasilBox = document.getElementById('pegawaiHasil');
  var tbody = document.getElementById('tabelTerpilih');
  var barisKosong = document.getElementById('barisKosong');
  var terpilih = [];
  var timer = null;

  function render(){
    tbody.innerHTML = '';
    if (terpilih.length === 0) {
      tbody.appendChild(barisKosong);
      return;
    }
    terpilih.forEach(function(p, idx){
      var tr = document.createElement('tr');

      var tdNo = document.createElement('td'); tdNo.textContent = idx + 1; tr.appendChild(tdNo);
      var tdNama = document.createElement('td'); tdNama.textContent = p.nama_lengkap; tr.appendChild(tdNama);
      var tdNip = document.createElement('td'); tdNip.textContent = p.nip; tr.appendChild(tdNip);
      var tdJab = document.createElement('td'); tdJab.textContent = (p.jabatan || '') + ' / ' + (p.unit_kerja || ''); tr.appendChild(tdJab);

      var tdTgl = document.createElement('td');
      var inpTgl = document.createElement('input');
      inpTgl.type = 'text';
      inpTgl.className = 'row-input';
      inpTgl.name = 'tanggal_baris[]';
      inpTgl.placeholder = 'mis. 26 Januari 2026';
      inpTgl.style.cssText = 'width:100%; border:1px solid var(--border-strong); border-radius:6px; padding:6px 8px; font-size:0.83rem;';
      tdTgl.appendChild(inpTgl);
      tr.appendChild(tdTgl);

      var tdAksi = document.createElement('td');
      var hiddenId = document.createElement('input');
      hiddenId.type = 'hidden';
      hiddenId.name = 'pegawai_id[]';
      hiddenId.value = p.id;
      tdAksi.appendChild(hiddenId);
      var rm = document.createElement('button');
      rm.type = 'button';
      rm.textContent = '×';
      rm.style.cssText = 'background:none; border:none; cursor:pointer; font-size:1rem; color:var(--ink-dim);';
      rm.addEventListener('click', function(){ terpilih.splice(idx, 1); render(); });
      tdAksi.appendChild(rm);
      tr.appendChild(tdAksi);

      tbody.appendChild(tr);
    });
  }

  input.addEventListener('input', function(){
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
              if (!terpilih.some(function(x){ return x.id === p.id; })) {
                terpilih.push(p);
                render();
              }
              input.value = '';
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

  document.getElementById('formTugas').addEventListener('submit', function(e){
    if (terpilih.length === 0) {
      e.preventDefault();
      alert('Pilih minimal satu pegawai terlebih dahulu.');
    }
  });

  render();
})();
</script>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
