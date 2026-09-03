<?php
// Definisi migrasi "surat_tugas" — dari surat/surat_tugas.php + config JSON. Jenis surat
// pertama yang pakai blok tabel (lampiran daftar pegawai) DAN variabel turunan yang makai
// fungsi klausa_jika_ada (dipindah persis dari string concat manual di kode lama).
// Kolom tabel "nama" & "jabatan_satker" butuh sumber 'pegawai_fungsi' (bukan field mentah)
// karena nilainya gabungan/format dari beberapa kolom pegawai (lihat db/003_...sql).

return array(
    'jenis_surat' => array(
        'kode'              => 'surat_tugas',
        'nama'              => 'Surat Tugas',
        'deskripsi'         => null,
        'kategori'          => 'single_dokumen',
        'kop_surat'         => 'standar',
        'pola_nama_unduhan' => 'Surat_Tugas_{nomor_surat}',
        'urutan_tampil'     => 60,
    ),

    'sub_jenis' => array(),
    'peran_pegawai' => array(), // tidak ada peran tunggal — semua pegawai masuk lewat blok tabel

    'template' => array(
        'sub_jenis_kode' => null,
        'sumber_path'    => __DIR__ . '/../../templates/surat_tugas.docx',
        'nama_asli'      => 'surat_tugas.docx',
    ),

    'variabel' => array(
        array('kode' => 'nomor_surat', 'label' => 'Nomor Surat', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 10),
        array('kode' => 'tanggal_surat', 'label' => 'Tanggal Surat', 'sumber' => 'sistem',
              'sistem_kode' => 'tanggal_sekarang', 'fungsi_pasca' => 'tanggal_indonesia', 'urutan_tampil' => 20),
        array('kode' => 'uraian_tugas', 'label' => 'Uraian Tugas', 'sumber' => 'manual',
              'tipe_input' => 'textarea', 'wajib_default' => 1, 'urutan_tampil' => 30),

        // --- Helper (bukan placeholder sendiri) — parameter klausa opsional ---
        array('kode' => 'dasar_undangan', 'label' => 'Dasar (No. & Tgl. Surat Undangan)', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 0, 'urutan_tampil' => 40),
        array('kode' => 'sumber_anggaran', 'label' => 'Sumber Anggaran (DIPA) — kosongkan jika tanpa SPD', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 0, 'urutan_tampil' => 50),

        array('kode' => 'dasar_undangan_klausa', 'label' => 'Klausa Dasar Undangan (otomatis)', 'sumber' => 'turunan',
              'parameter_variabel' => array('dasar_undangan'), 'fungsi_pasca' => 'klausa_jika_ada',
              'fungsi_parameter_1' => 'Berdasarkan Surat Undangan Nomor : ', 'fungsi_parameter_2' => '.', 'urutan_tampil' => 60),
        array('kode' => 'info_anggaran', 'label' => 'Klausa Info Anggaran (otomatis)', 'sumber' => 'turunan',
              'parameter_variabel' => array('sumber_anggaran'), 'fungsi_pasca' => 'klausa_jika_ada',
              'fungsi_parameter_1' => 'Segala biaya yang ditimbulkan selama pelaksanaan kegiatan ini dibebankan kepada ',
              'fungsi_parameter_2' => '.', 'urutan_tampil' => 70),
    ),

    'blok_tabel' => array(
        array(
            'kode' => 'no', 'sub_jenis_kode' => null, 'nama_anchor_kolom' => 'no',
            'label' => 'Pegawai yang Ditugaskan', 'minimal_baris' => 1,
            'kolom' => array(
                array('kode' => 'no', 'label' => 'No', 'sumber' => 'auto_nomor', 'urutan_kolom' => 10),
                array('kode' => 'nama', 'label' => 'Nama', 'sumber' => 'pegawai_fungsi', 'fungsi_pasca' => 'nama_bergelar', 'urutan_kolom' => 20),
                array('kode' => 'nip', 'label' => 'NIP', 'sumber' => 'pegawai_field', 'field_pegawai' => 'nip', 'urutan_kolom' => 30),
                array('kode' => 'jabatan_satker', 'label' => 'Jabatan / Satuan Kerja', 'sumber' => 'pegawai_fungsi', 'fungsi_pasca' => 'jabatan_satuan_kerja', 'urutan_kolom' => 40),
                array('kode' => 'tanggal', 'label' => 'Tanggal', 'sumber' => 'manual_per_baris', 'urutan_kolom' => 50),
            ),
        ),
    ),
);
