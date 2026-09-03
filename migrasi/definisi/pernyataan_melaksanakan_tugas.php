<?php
// Definisi migrasi "pernyataan_melaksanakan_tugas" (SPMT) — dari surat/pernyataan_melaksanakan_tugas.php
// + config JSON. dasar_sk_nomor, dasar_sk_tanggal, tmt, instansi, besaran_tunjangan bukan placeholder
// sendiri — cuma parameter narasi_pelaksanaan_tugas (Formatter::narasiPelaksanaanTugas()).

return array(
    'jenis_surat' => array(
        'kode'              => 'pernyataan_melaksanakan_tugas',
        'nama'              => 'Surat Pernyataan Melaksanakan Tugas',
        'deskripsi'         => null,
        'kategori'          => 'single_dokumen',
        'kop_surat'         => 'standar',
        'pola_nama_unduhan' => 'SPMT_{dinyatakan_nama_lengkap}',
        'urutan_tampil'     => 30,
    ),

    'sub_jenis' => array(),

    'peran_pegawai' => array(
        array('kode' => 'menyatakan', 'label' => 'Pejabat yang Menyatakan', 'wajib' => 1, 'urutan_tampil' => 10),
        array('kode' => 'dinyatakan', 'label' => 'Pegawai yang Dinyatakan', 'wajib' => 1, 'urutan_tampil' => 20),
    ),

    'template' => array(
        'sub_jenis_kode' => null,
        'sumber_path'    => __DIR__ . '/../../templates/pernyataan_melaksanakan_tugas.docx',
        'nama_asli'      => 'pernyataan_melaksanakan_tugas.docx',
    ),

    'variabel' => array(
        array('kode' => 'menyatakan_nama_lengkap', 'label' => 'Nama Lengkap (Menyatakan)', 'sumber' => 'pegawai',
              'peran_kode' => 'menyatakan', 'field_pegawai' => null, 'fungsi_pasca' => 'nama_bergelar', 'urutan_tampil' => 10),
        array('kode' => 'menyatakan_nip', 'label' => 'NIP (Menyatakan)', 'sumber' => 'pegawai',
              'peran_kode' => 'menyatakan', 'field_pegawai' => 'nip', 'urutan_tampil' => 20),
        array('kode' => 'menyatakan_pangkat_golongan', 'label' => 'Pangkat/Golongan (Menyatakan)', 'sumber' => 'pegawai',
              'peran_kode' => 'menyatakan', 'field_pegawai' => null, 'fungsi_pasca' => 'pangkat_golongan', 'urutan_tampil' => 30),
        array('kode' => 'menyatakan_jabatan', 'label' => 'Jabatan (Menyatakan)', 'sumber' => 'pegawai',
              'peran_kode' => 'menyatakan', 'field_pegawai' => 'jabatan', 'urutan_tampil' => 40),

        array('kode' => 'dinyatakan_nama_lengkap', 'label' => 'Nama Lengkap (Dinyatakan)', 'sumber' => 'pegawai',
              'peran_kode' => 'dinyatakan', 'field_pegawai' => null, 'fungsi_pasca' => 'nama_bergelar', 'urutan_tampil' => 50),
        array('kode' => 'dinyatakan_nip', 'label' => 'NIP (Dinyatakan)', 'sumber' => 'pegawai',
              'peran_kode' => 'dinyatakan', 'field_pegawai' => 'nip', 'urutan_tampil' => 60),
        array('kode' => 'dinyatakan_pangkat_golongan', 'label' => 'Pangkat/Golongan (Dinyatakan)', 'sumber' => 'pegawai',
              'peran_kode' => 'dinyatakan', 'field_pegawai' => null, 'fungsi_pasca' => 'pangkat_golongan', 'urutan_tampil' => 70),
        array('kode' => 'dinyatakan_jabatan', 'label' => 'Jabatan (Dinyatakan)', 'sumber' => 'pegawai',
              'peran_kode' => 'dinyatakan', 'field_pegawai' => 'jabatan', 'urutan_tampil' => 80),

        array('kode' => 'nomor_surat', 'label' => 'Nomor Surat', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 90),
        array('kode' => 'tanggal_surat', 'label' => 'Tanggal Surat', 'sumber' => 'sistem',
              'sistem_kode' => 'tanggal_sekarang', 'fungsi_pasca' => 'tanggal_indonesia', 'urutan_tampil' => 100),
        array('kode' => 'tujuan_surat', 'label' => 'Ditujukan Kepada (mis. Kepala KPPN)', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 110),

        // --- Helper (bukan placeholder sendiri) — parameter narasi_pelaksanaan_tugas ---
        array('kode' => 'dasar_sk_nomor', 'label' => 'Nomor SK Dasar', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 120),
        array('kode' => 'dasar_sk_tanggal', 'label' => 'Tanggal SK Dasar', 'sumber' => 'manual',
              'tipe_input' => 'date', 'wajib_default' => 1, 'urutan_tampil' => 130),
        array('kode' => 'tmt', 'label' => 'Terhitung Mulai Tanggal (TMT)', 'sumber' => 'manual',
              'tipe_input' => 'date', 'wajib_default' => 1, 'urutan_tampil' => 140),
        array('kode' => 'instansi', 'label' => 'Instansi / Satuan Kerja', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 150),
        array('kode' => 'besaran_tunjangan', 'label' => 'Besaran Tunjangan — ketik angka saja, mis. 185000 (kosongkan jika tidak ada)',
              'sumber' => 'manual', 'tipe_input' => 'text', 'wajib_default' => 0, 'urutan_tampil' => 160),

        array('kode' => 'narasi_pelaksanaan_tugas', 'label' => 'Narasi Pelaksanaan Tugas (otomatis)', 'sumber' => 'turunan',
              'parameter_variabel' => array('dasar_sk_nomor', 'dasar_sk_tanggal', 'tmt', 'instansi', 'besaran_tunjangan'),
              'fungsi_pasca' => 'narasi_pelaksanaan_tugas', 'urutan_tampil' => 170),
    ),

    'blok_tabel' => array(),
);
