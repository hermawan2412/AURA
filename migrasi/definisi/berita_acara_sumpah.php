<?php
// Definisi migrasi "berita_acara_sumpah" — dari surat/berita_acara_sumpah.php + config JSON.
// 5 peran (pengambil_sumpah, disumpah, saksi_1, saksi_2, rohaniawan), masing2 3 placeholder
// standar (nama_lengkap/nip/pangkat_golongan) + 1 placeholder ekstra khusus pengambil_sumpah
// (jabatan). Tidak ada narasi bespoke di jenis surat ini — semua pemetaan langsung.

$peranDaftar = array(
    array('kode' => 'pengambil_sumpah', 'label' => 'Pejabat yang Mengambil Sumpah'),
    array('kode' => 'disumpah', 'label' => 'PNS yang Disumpah'),
    array('kode' => 'saksi_1', 'label' => 'Saksi 1'),
    array('kode' => 'saksi_2', 'label' => 'Saksi 2'),
    array('kode' => 'rohaniawan', 'label' => 'Rohaniawan Pendamping'),
);

$peranPegawai = array();
$variabel = array();
$urutan = 10;

foreach ($peranDaftar as $i => $p) {
    $peranPegawai[] = array('kode' => $p['kode'], 'label' => $p['label'], 'wajib' => 1, 'urutan_tampil' => ($i + 1) * 10);

    $variabel[] = array('kode' => $p['kode'] . '_nama_lengkap', 'label' => $p['label'] . ' — Nama Lengkap', 'sumber' => 'pegawai',
        'peran_kode' => $p['kode'], 'field_pegawai' => null, 'fungsi_pasca' => 'nama_bergelar', 'urutan_tampil' => $urutan);
    $urutan += 10;
    $variabel[] = array('kode' => $p['kode'] . '_nip', 'label' => $p['label'] . ' — NIP', 'sumber' => 'pegawai',
        'peran_kode' => $p['kode'], 'field_pegawai' => 'nip', 'urutan_tampil' => $urutan);
    $urutan += 10;
    $variabel[] = array('kode' => $p['kode'] . '_pangkat_golongan', 'label' => $p['label'] . ' — Pangkat/Golongan', 'sumber' => 'pegawai',
        'peran_kode' => $p['kode'], 'field_pegawai' => null, 'fungsi_pasca' => 'pangkat_golongan', 'urutan_tampil' => $urutan);
    $urutan += 10;
}

// Placeholder ekstra khusus: jabatan pengambil_sumpah saja (peran lain tidak butuh jabatan di docx)
$variabel[] = array('kode' => 'pengambil_sumpah_jabatan', 'label' => 'Pejabat yang Mengambil Sumpah — Jabatan', 'sumber' => 'pegawai',
    'peran_kode' => 'pengambil_sumpah', 'field_pegawai' => 'jabatan', 'urutan_tampil' => $urutan);
$urutan += 10;

$variabel[] = array('kode' => 'nomor_surat', 'label' => 'Nomor Surat', 'sumber' => 'manual',
    'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => $urutan);
$urutan += 10;
$variabel[] = array('kode' => 'pembukaan_tanggal', 'label' => 'Hari, Tanggal Pengambilan Sumpah', 'sumber' => 'manual',
    'tipe_input' => 'date', 'wajib_default' => 1, 'fungsi_pasca' => 'tanggal_naratif', 'urutan_tampil' => $urutan);
$urutan += 10;
$variabel[] = array('kode' => 'dasar_sk_nomor', 'label' => 'Nomor SK Pengangkatan', 'sumber' => 'manual',
    'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => $urutan);
$urutan += 10;
$variabel[] = array('kode' => 'dasar_sk_tanggal', 'label' => 'Tanggal SK Pengangkatan', 'sumber' => 'manual',
    'tipe_input' => 'date', 'wajib_default' => 1, 'fungsi_pasca' => 'tanggal_indonesia', 'urutan_tampil' => $urutan);

return array(
    'jenis_surat' => array(
        'kode'              => 'berita_acara_sumpah',
        'nama'              => 'Berita Acara Pengambilan Sumpah',
        'deskripsi'         => null,
        'kategori'          => 'single_dokumen',
        'kop_surat'         => 'tanpa_kop',
        'pola_nama_unduhan' => 'Berita_Acara_Sumpah_{disumpah_nama_lengkap}',
        'urutan_tampil'     => 40,
    ),
    'sub_jenis' => array(),
    'peran_pegawai' => $peranPegawai,
    'template' => array(
        'sub_jenis_kode' => null,
        'sumber_path'    => __DIR__ . '/../../templates/berita_acara_sumpah.docx',
        'nama_asli'      => 'berita_acara_sumpah.docx',
    ),
    'variabel' => $variabel,
    'blok_tabel' => array(),
);
