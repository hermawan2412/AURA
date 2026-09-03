<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\JenisSurat;
use Aurat\Formatter;
use Aurat\DocxGenerator;

Auth::requireLogin();

$konfigurasi = JenisSurat::muat('undangan');
$pesanError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomorSurat  = isset($_POST['nomor_surat']) ? trim($_POST['nomor_surat']) : '';
    $sifat       = isset($_POST['sifat']) ? trim($_POST['sifat']) : '';
    $lampiran    = isset($_POST['lampiran']) ? trim($_POST['lampiran']) : '';
    $hal         = isset($_POST['hal']) ? trim($_POST['hal']) : '';
    $namaAcara   = isset($_POST['nama_acara']) ? trim($_POST['nama_acara']) : '';
    $tujuan      = isset($_POST['tujuan']) ? trim($_POST['tujuan']) : '';
    $hari        = isset($_POST['hari']) ? trim($_POST['hari']) : '';
    $tanggalAcara = isset($_POST['tanggal_acara']) ? trim($_POST['tanggal_acara']) : '';
    $waktu       = isset($_POST['waktu']) ? trim($_POST['waktu']) : '';
    $tempat      = isset($_POST['tempat']) ? trim($_POST['tempat']) : '';

    if ($nomorSurat === '' || $hal === '' || $namaAcara === '' || $tujuan === ''
        || $hari === '' || $tanggalAcara === '' || $waktu === '' || $tempat === '') {
        $pesanError = 'Semua field bertanda * wajib diisi.';
    } else {
        $nilai = array(
            'nomor_surat'   => $nomorSurat,
            'tanggal_surat' => Formatter::tanggalIndonesia(date('Y-m-d')),
            'sifat'         => $sifat !== '' ? $sifat : '-',
            'lampiran'      => $lampiran !== '' ? $lampiran : '-',
            'hal'           => $hal,
            'nama_acara'    => $namaAcara,
            'tujuan'        => $tujuan,
            'hari'          => $hari,
            'tanggal_acara' => Formatter::tanggalIndonesia($tanggalAcara),
            'waktu'         => $waktu,
            'tempat'        => $tempat,
        );

        $namaUnduhan = 'Undangan_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $nomorSurat) . '.docx';

        try {
            DocxGenerator::generateDanUnduh($konfigurasi['template_file'], $nilai, array(), $namaUnduhan);
            exit;
        } catch (\RuntimeException $e) {
            $pesanError = $e->getMessage();
        }
    }
}

$halamanAktif = 'undangan';
$judulHalaman = 'Surat Undangan';
$breadcrumb   = 'Buat Surat';
$subJudul     = '';
$rootAsset    = '../';

require __DIR__ . '/../views/layout_atas.php';
?>

<div class="note"><b>Catatan:</b> nomor surat diisi manual. Kolom "Kepada Yth." diisi bebas (bisa kelompok/jabatan, bukan cuma satu nama pegawai) — belum ada saran otomatis karena aplikasi ini sengaja tidak menyimpan riwayat surat yang pernah dibuat.</div>

<?php if ($pesanError !== ''): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars($pesanError); ?></div>
<?php endif; ?>

<div class="form-card">
  <form method="post" action="undangan.php">
    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Kepala Surat</h4>
      <div class="grid-2">
        <div class="field">
          <label>Nomor <span class="req">*</span></label>
          <input type="text" name="nomor_surat" required>
        </div>
        <div class="field">
          <label>Sifat</label>
          <input type="text" name="sifat" placeholder="mis. Terbatas">
        </div>
        <div class="field">
          <label>Lampiran</label>
          <input type="text" name="lampiran" placeholder="mis. - (kosongkan jika tidak ada)">
        </div>
        <div class="field">
          <label>Hal <span class="req">*</span></label>
          <input type="text" name="hal" placeholder="mis. Undangan" required>
        </div>
      </div>
      <div class="field">
        <label>Nama/Uraian Acara — untuk kalimat pembuka <span class="req">*</span></label>
        <input type="text" name="nama_acara" placeholder="mis. Ekspose Audit Kinerja Badan Pengawas Mahkamah Agung RI" required>
      </div>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Tujuan</h4>
      <div class="field">
        <label>Kepada Yth. <span class="req">*</span></label>
        <textarea name="tujuan" placeholder="mis. Wakil Ketua, Seluruh Hakim, Pejabat Kepaniteraan, Pejabat Kesekretariatan, Staf Kepaniteraan, Staf Kesekretariatan, seluruh Karyawan/ti Pengadilan Agama Rantau" required></textarea>
      </div>
    </div>

    <div class="form-section">
      <h4 style="font-family:var(--display); font-size:1rem;">Detail Acara</h4>
      <div class="grid-2">
        <div class="field">
          <label>Hari <span class="req">*</span></label>
          <input type="text" name="hari" placeholder="mis. Kamis" required>
        </div>
        <div class="field">
          <label>Tanggal <span class="req">*</span></label>
          <input type="date" name="tanggal_acara" required>
        </div>
        <div class="field">
          <label>Waktu <span class="req">*</span></label>
          <input type="text" name="waktu" placeholder="mis. 13.00 WITA" required>
        </div>
        <div class="field">
          <label>Tempat <span class="req">*</span></label>
          <input type="text" name="tempat" placeholder="mis. Ruang Sidang Utama Pengadilan Agama Rantau" required>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Unduh Dokumen (.docx)</button>
  </form>
</div>

<?php require __DIR__ . '/../views/layout_bawah.php'; ?>
