<?php
// Definisi migrasi jenis surat "pelaksana_harian" — ditulis manual dari surat/pelaksana_harian.php
// + config/jenis_surat/pelaksana_harian.json. Catatan khusus: jabatan_diplh, tanggal_pelaksanaan,
// alasan, dasar_surat_tugas TIDAK tampil sendiri sbg placeholder di docx — keempatnya cuma dipakai
// sbg parameter utk membentuk kalimat "narasi_penunjukan" (Formatter::narasiPenunjukanPlh()).

return array(
    'jenis_surat' => array(
        'kode'              => 'pelaksana_harian',
        'nama'              => 'Surat Penunjukan Pelaksana Harian',
        'deskripsi'         => null,
        'kategori'          => 'single_dokumen',
        'kop_surat'         => 'standar',
        'pola_nama_unduhan' => 'Surat_Penunjukan_Plh_{ditunjuk_nama_lengkap}',
        'urutan_tampil'     => 20,
    ),

    'sub_jenis' => array(),

    'peran_pegawai' => array(
        array('kode' => 'menunjuk', 'label' => 'Pejabat yang Menunjuk', 'wajib' => 1, 'urutan_tampil' => 10),
        array('kode' => 'ditunjuk', 'label' => 'Pejabat yang Ditunjuk (Plh)', 'wajib' => 1, 'urutan_tampil' => 20),
    ),

    'template' => array(
        'sub_jenis_kode' => null,
        'sumber_path'    => __DIR__ . '/../../templates/pelaksana_harian.docx',
        'nama_asli'      => 'pelaksana_harian.docx',
    ),

    'variabel' => array(
        array('kode' => 'menunjuk_nama_lengkap', 'label' => 'Nama Lengkap (Menunjuk)', 'sumber' => 'pegawai',
              'peran_kode' => 'menunjuk', 'field_pegawai' => null, 'fungsi_pasca' => 'nama_bergelar', 'urutan_tampil' => 10),
        array('kode' => 'menunjuk_nip', 'label' => 'NIP (Menunjuk)', 'sumber' => 'pegawai',
              'peran_kode' => 'menunjuk', 'field_pegawai' => 'nip', 'urutan_tampil' => 20),
        array('kode' => 'menunjuk_pangkat_golongan', 'label' => 'Pangkat/Golongan (Menunjuk)', 'sumber' => 'pegawai',
              'peran_kode' => 'menunjuk', 'field_pegawai' => null, 'fungsi_pasca' => 'pangkat_golongan', 'urutan_tampil' => 30),
        array('kode' => 'menunjuk_jabatan', 'label' => 'Jabatan (Menunjuk)', 'sumber' => 'pegawai',
              'peran_kode' => 'menunjuk', 'field_pegawai' => 'jabatan', 'urutan_tampil' => 40),

        array('kode' => 'ditunjuk_nama_lengkap', 'label' => 'Nama Lengkap (Ditunjuk)', 'sumber' => 'pegawai',
              'peran_kode' => 'ditunjuk', 'field_pegawai' => null, 'fungsi_pasca' => 'nama_bergelar', 'urutan_tampil' => 50),
        array('kode' => 'ditunjuk_nip', 'label' => 'NIP (Ditunjuk)', 'sumber' => 'pegawai',
              'peran_kode' => 'ditunjuk', 'field_pegawai' => 'nip', 'urutan_tampil' => 60),
        array('kode' => 'ditunjuk_pangkat_golongan', 'label' => 'Pangkat/Golongan (Ditunjuk)', 'sumber' => 'pegawai',
              'peran_kode' => 'ditunjuk', 'field_pegawai' => null, 'fungsi_pasca' => 'pangkat_golongan', 'urutan_tampil' => 70),
        array('kode' => 'ditunjuk_jabatan', 'label' => 'Jabatan (Ditunjuk)', 'sumber' => 'pegawai',
              'peran_kode' => 'ditunjuk', 'field_pegawai' => 'jabatan', 'urutan_tampil' => 80),

        array('kode' => 'nomor_surat', 'label' => 'Nomor Surat', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 90),
        array('kode' => 'tanggal_surat', 'label' => 'Tanggal Surat', 'sumber' => 'sistem',
              'sistem_kode' => 'tanggal_sekarang', 'fungsi_pasca' => 'tanggal_indonesia', 'urutan_tampil' => 100),

        // --- Helper (bukan placeholder sendiri) — cuma parameter utk narasi_penunjukan ---
        array('kode' => 'jabatan_diplh', 'label' => 'Jabatan yang Di-Plh-kan (lengkap, mis. Sekretaris Pengadilan Agama Rantau)',
              'sumber' => 'manual', 'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 110),
        array('kode' => 'tanggal_pelaksanaan', 'label' => 'Tanggal Pelaksanaan', 'sumber' => 'manual',
              'tipe_input' => 'date', 'wajib_default' => 1, 'urutan_tampil' => 120),
        array('kode' => 'alasan', 'label' => 'Alasan — kosongkan untuk pakai default "melaksanakan dinas"',
              'sumber' => 'manual', 'tipe_input' => 'text', 'wajib_default' => 0, 'urutan_tampil' => 130),
        array('kode' => 'dasar_surat_tugas', 'label' => 'Dasar (No. & Tgl. Surat Tugas)', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 0, 'urutan_tampil' => 140),

        array('kode' => 'narasi_penunjukan', 'label' => 'Narasi Penunjukan (otomatis)', 'sumber' => 'turunan',
              'parameter_variabel' => array('jabatan_diplh', 'tanggal_pelaksanaan', 'alasan', 'dasar_surat_tugas'),
              'fungsi_pasca' => 'narasi_penunjukan_plh', 'urutan_tampil' => 150),
    ),

    'blok_tabel' => array(),
);
