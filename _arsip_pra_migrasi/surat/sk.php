<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Database;
use Aurat\JenisSurat;
use Aurat\Formatter;
use Aurat\DocxGenerator;

Auth::requireLogin();

$konfigurasi = JenisSurat::muat('sk');
$pesanError = '';

$labelKolomTambahan = array(
    'tim_kerja' => 'Kedudukan',
    'panitia'   => 'Peran dalam Panitia',
);
$kodeKolomTambahan = array(
    'tim_kerja' => 'kedudukan',
    'panitia'   => 'peran_panitia',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subJenis        = isset($_POST['sub_jenis']) ? trim($_POST['sub_jenis']) : '';
    $penetapId       = isset($_POST['penetap_id']) ? (int) $_POST['penetap_id'] : 0;
    $nomorSk         = isset($_POST['nomor_sk']) ? trim($_POST['nomor_sk']) : '';
    $tanggalPenetapan = isset($_POST['tanggal_penetapan']) ? trim($_POST['tanggal_penetapan']) : '';
    $tentang         = isset($_POST['tentang']) ? trim($_POST['tentang']) : '';
    $menimbang       = isset($_POST['menimbang']) ? trim($_POST['menimbang']) : '';
    $mengingat       = isset($_POST['mengingat']) ? trim($_POST['mengingat']) : '';
    $diktum          = isset($_POST['diktum']) ? trim($_POST['diktum']) : '';
    $pegawaiIds      = isset($_POST['pegawai_id']) && is_array($_POST['pegawai_id']) ? $_POST['pegawai_id'] : array();
    $kolomTambahan   = isset($_POST['kolom_tambahan']) && is_array($_POST['kolom_tambahan']) ? $_POST['kolom_tambahan'] : array();

    $subJenisValid = isset($kodeKolomTambahan[$subJenis]);

    if (!$subJenisValid || $penetapId === 0 || $nomorSk === '' || $tanggalPenetapan === '' || $tentang === ''
        || $menimbang === '' || $mengingat === '' || $diktum === '' || count($pegawaiIds) === 0) {
        $pesanError = 'Semua field bertanda * wajib diisi, dan minimal satu pegawai harus dipilih untuk lampiran.';
    } else {
        $pdo = Database::pdo();

        $stmtPenetap = $pdo->prepare('SELECT * FROM pegawai WHERE id = ? AND status_aktif = 1');
        $stmtPenetap->execute(array($penetapId));
        $penetap = $stmtPenetap->fetch();

        $placeholders = implode(',', array_fill(0, count($pegawaiIds), '?'));
        $stmtLampiran = $pdo->prepare("SELECT * FROM pegawai WHERE id IN ($placeholders) AND status_aktif = 1");
        $stmtLampiran->execute(array_map('intval', $pegawaiIds));
        $pegawaiTerpilih = array();
        foreach ($stmtLampiran->fetchAll() as $row) {
            $pegawaiTerpilih[$row['id']] = $row;
        }

        if (!$penetap) {
            $pesanError = 'Pejabat yang menetapkan tidak ditemukan. Silakan pilih ulang.';
        } else {
            $kodeExtra = $kodeKolomTambahan[$subJenis];
            $barisTabel = array();
            foreach ($pegawaiIds as $idx => $pid) {
                $pid = (int) $pid;
                if (!isset($pegawaiTerpilih[$pid])) {
                    continue;
                }
                $p = $pegawaiTerpilih[$pid];
                $baris = array(
                    'no'   => count($barisTabel) + 1,
                    'nama' => Formatter::namaBergelar($p),
                    'nip'  => $p['nip'],
                );
                if ($subJenis === 'tim_kerja') {
                    $baris['jabatan'] = $p['jabatan'];
                }
                $baris[$kodeExtra] = isset($kolomTambahan[$idx]) ? trim($kolomTambahan[$idx]) : '';
                $barisTabel[] = $baris;
            }

            if (count($barisTabel) === 0) {
                $pesanError = 'Pegawai yang dipilih untuk lampiran tidak ditemukan. Silakan cari ulang.';
            } else {
                $nilai = array(
                    'nomor_sk'               => $nomorSk,
                    'tanggal_penetapan'      => Formatter::tanggalIndonesia($tanggalPenetapan),
                    'tentang'                => $tentang,
                    'menimbang'              => $menimbang,
                    'mengingat'              => $mengingat,
                    'diktum'                 => $diktum,
                    'penetap_nama_lengkap'   => Formatter::namaBergelar($penetap),
                    'penetap_nip'            => $penetap['nip'],
                );

                $tabel = array('no' => $barisTabel);
                $templateFile = $konfigurasi['template_file'][$subJenis];
                $namaUnduhan = 'SK_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $nomorSk) . '.docx';

                try {
                    DocxGenerator::generateDanUnduh($templateFile, $nilai, $tabel, $namaUnduhan);
                    exit;
                } catch (\RuntimeException $e) {
                    $pesanError = $e->getMessage();
                }
            }
        }
    }
}

$halamanAktif = 'sk';
$judulHalaman = 'Surat Keputusan';
$breadcrumb   = 'Buat Surat';
$subJudul     = 'Satu formulir menghasilkan dokumen SK lengkap dengan halaman Lampiran daftar pegawai.';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<div class="note"><b>Catatan:</b> kolom tabel lampiran menyesuaikan Jenis SK yang dipilih. Kop surat mengikuti gambar logo tetap, tidak dibuat ulang tiap generate.</div>

<?php if ($pesanError !== ''): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars($pesanError); ?></div>
<?php endif; ?>

<div class="form-card">
  <form method="post" action="sk.php" id="formSk">
    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Data Penetapan</h4>
      <div class="grid-2">
        <div class="field">
          <label>Nomor SK <span class="req">*</span></label>
          <input type="text" name="nomor_sk" required>
        </div>
        <div class="field">
          <label>Tanggal Penetapan <span class="req">*</span></label>
          <input type="date" name="tanggal_penetapan" required>
        </div>
        <div class="field">
          <label>Jenis SK <span class="req">*</span></label>
          <select name="sub_jenis" id="subJenis" required>
            <?php foreach ($konfigurasi['sub_jenis'] as $sj): ?>
              <option value="<?php echo htmlspecialchars($sj['kode']); ?>"><?php echo htmlspecialchars($sj['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Pejabat yang Menetapkan <span class="req">*</span></label>
          <input type="text" id="penetapCari" placeholder="Ketik nama pegawai&hellip;" autocomplete="off">
          <input type="hidden" name="penetap_id" id="penetapId" required>
          <div class="picker-results" id="penetapHasil"></div>
        </div>
      </div>
      <div class="field">
        <label>Tentang / Perihal — judul lengkap termasuk tahun <span class="req">*</span></label>
        <input type="text" name="tentang" placeholder="mis. PEMBENTUKAN TIM KERJA DIGITALISASI ARSIP TAHUN 2026" required>
      </div>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Konsideran &amp; Diktum</h4>
      <div class="field">
        <label>Menimbang (bernomor a, b, c, ...) <span class="req">*</span></label>
        <textarea name="menimbang" required></textarea>
      </div>
      <div class="field">
        <label>Mengingat (bernomor 1, 2, 3, ...) <span class="req">*</span></label>
        <textarea name="mengingat" required></textarea>
      </div>
      <div class="field">
        <label>Diktum — KESATU, KEDUA, KETIGA, ... <span class="req">*</span></label>
        <textarea name="diktum" required></textarea>
      </div>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Lampiran — Daftar Pegawai</h4>
      <div class="field">
        <label>Cari nama atau NIP untuk ditambahkan ke tabel</label>
        <input type="text" id="lampiranCari" placeholder="Ketik nama pegawai&hellip;" autocomplete="off">
        <div class="picker-results" id="lampiranHasil"></div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th></th><th>No</th><th>Nama</th><th>NIP</th>
              <th id="thJabatan">Jabatan</th>
              <th id="thExtra">Kedudukan</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="tabelLampiran">
            <tr id="lampiranKosong"><td colspan="7" style="text-align:center; color:var(--ink-dim); font-style:italic;">Belum ada pegawai ditambahkan.</td></tr>
          </tbody>
        </table>
      </div>
      <span class="form-hint">Seret baris untuk mengubah urutan.</span>
    </div>

    <button type="submit" class="btn btn-primary">Unduh Dokumen SK (.docx)</button>
  </form>
</div>

<script>
(function(){
  var subJenisSelect = document.getElementById('subJenis');
  var thJabatan = document.getElementById('thJabatan');
  var thExtra = document.getElementById('thExtra');
  var labelExtra = { tim_kerja: 'Kedudukan', panitia: 'Peran dalam Panitia' };

  function terapkanSubJenis(){
    var v = subJenisSelect.value;
    thJabatan.style.display = (v === 'tim_kerja') ? '' : 'none';
    thExtra.textContent = labelExtra[v] || 'Keterangan';
    renderLampiran();
  }
  subJenisSelect.addEventListener('change', terapkanSubJenis);

  function initPicker(inputId, hasilId, onPick){
    var input = document.getElementById(inputId);
    var hasilBox = document.getElementById(hasilId);
    var timer = null;
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
                onPick(p);
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
  }

  // --- Pejabat yang Menetapkan (single) ---
  initPicker('penetapCari', 'penetapHasil', function(p){
    document.getElementById('penetapId').value = p.id;
    document.getElementById('penetapCari').value = p.nama_lengkap;
  });

  // --- Lampiran (multi + reorder) ---
  var lampiran = [];
  var dragFrom = null;
  var tbody = document.getElementById('tabelLampiran');
  var kosong = document.getElementById('lampiranKosong');

  function renderLampiran(){
    var subJenis = subJenisSelect.value;
    tbody.innerHTML = '';
    if (lampiran.length === 0) { tbody.appendChild(kosong); return; }

    lampiran.forEach(function(p, idx){
      var tr = document.createElement('tr');
      tr.draggable = true;
      tr.dataset.idx = idx;

      var tdHandle = document.createElement('td');
      tdHandle.textContent = '⠿';
      tdHandle.style.cssText = 'cursor:grab; color:var(--ink-dim);';
      tr.appendChild(tdHandle);

      var tdNo = document.createElement('td'); tdNo.textContent = idx + 1; tr.appendChild(tdNo);
      var tdNama = document.createElement('td'); tdNama.textContent = p.nama_lengkap; tr.appendChild(tdNama);
      var tdNip = document.createElement('td'); tdNip.textContent = p.nip; tr.appendChild(tdNip);

      var tdJabatan = document.createElement('td');
      tdJabatan.textContent = p.jabatan || '';
      tdJabatan.style.display = (subJenis === 'tim_kerja') ? '' : 'none';
      tr.appendChild(tdJabatan);

      var tdExtra = document.createElement('td');
      var inpExtra = document.createElement('input');
      inpExtra.type = 'text';
      inpExtra.name = 'kolom_tambahan[]';
      inpExtra.value = p._extra || '';
      inpExtra.placeholder = labelExtra[subJenis] || '';
      inpExtra.style.cssText = 'width:100%; border:1px solid var(--border-strong); border-radius:6px; padding:6px 8px; font-size:0.83rem;';
      inpExtra.addEventListener('input', function(){ p._extra = inpExtra.value; });
      tdExtra.appendChild(inpExtra);
      tr.appendChild(tdExtra);

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
      rm.addEventListener('click', function(){ lampiran.splice(idx, 1); renderLampiran(); });
      tdAksi.appendChild(rm);
      tr.appendChild(tdAksi);

      tr.addEventListener('dragstart', function(){ dragFrom = idx; });
      tr.addEventListener('dragover', function(e){ e.preventDefault(); });
      tr.addEventListener('drop', function(e){
        e.preventDefault();
        if (dragFrom === null || dragFrom === idx) return;
        var moved = lampiran.splice(dragFrom, 1)[0];
        lampiran.splice(idx, 0, moved);
        dragFrom = null;
        renderLampiran();
      });

      tbody.appendChild(tr);
    });
  }

  initPicker('lampiranCari', 'lampiranHasil', function(p){
    if (!lampiran.some(function(x){ return x.id === p.id; })) {
      lampiran.push(p);
      renderLampiran();
    }
    document.getElementById('lampiranCari').value = '';
  });

  document.getElementById('formSk').addEventListener('submit', function(e){
    if (!document.getElementById('penetapId').value) {
      e.preventDefault();
      alert('Pilih Pejabat yang Menetapkan terlebih dahulu.');
      return;
    }
    if (lampiran.length === 0) {
      e.preventDefault();
      alert('Tambahkan minimal satu pegawai ke lampiran.');
    }
  });

  terapkanSubJenis();
})();
</script>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
