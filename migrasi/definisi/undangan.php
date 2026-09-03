<?php
// Definisi migrasi "undangan" — dari surat/undangan.php + config JSON. Tidak ada peran
// pegawai sama sekali (semua manual). "sifat"/"lampiran" default ke '-' kalau kosong
// (persis perilaku lama `$sifat !== '' ? $sifat : '-'`) via placeholder_default.
// Catatan: JSON lama menandai "tujuan" sbg tipe "textarea_datalist", tapi form aslinya
// cuma <textarea> polos tanpa datalist — dipetakan sbg 'textarea' biasa (sesuai perilaku
// nyata, bukan aspirasi JSON yang tidak pernah benar2 diimplementasikan).

return array(
    'jenis_surat' => array(
        'kode'              => 'undangan',
        'nama'              => 'Surat Undangan',
        'deskripsi'         => null,
        'kategori'          => 'single_dokumen',
        'kop_surat'         => 'standar',
        'pola_nama_unduhan' => 'Undangan_{nomor_surat}',
        'urutan_tampil'     => 50,
    ),
    'sub_jenis' => array(),
    'peran_pegawai' => array(),
    'template' => array(
        'sub_jenis_kode' => null,
        'sumber_path'    => __DIR__ . '/../../templates/undangan.docx',
        'nama_asli'      => 'undangan.docx',
    ),
    'variabel' => array(
        array('kode' => 'nomor_surat', 'label' => 'Nomor', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 10),
        array('kode' => 'tanggal_surat', 'label' => 'Tanggal Surat', 'sumber' => 'sistem',
              'sistem_kode' => 'tanggal_sekarang', 'fungsi_pasca' => 'tanggal_indonesia', 'urutan_tampil' => 20),
        array('kode' => 'sifat', 'label' => 'Sifat', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 0, 'placeholder_default' => '-', 'urutan_tampil' => 30),
        array('kode' => 'lampiran', 'label' => 'Lampiran', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 0, 'placeholder_default' => '-', 'urutan_tampil' => 40),
        array('kode' => 'hal', 'label' => 'Hal', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 50),
        array('kode' => 'nama_acara', 'label' => 'Nama/Uraian Acara (untuk kalimat pembuka)', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 60),
        array('kode' => 'tujuan', 'label' => 'Kepada Yth.', 'sumber' => 'manual',
              'tipe_input' => 'textarea', 'wajib_default' => 1, 'urutan_tampil' => 70),
        array('kode' => 'hari', 'label' => 'Hari', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 80),
        array('kode' => 'tanggal_acara', 'label' => 'Tanggal', 'sumber' => 'manual',
              'tipe_input' => 'date', 'wajib_default' => 1, 'fungsi_pasca' => 'tanggal_indonesia', 'urutan_tampil' => 90),
        array('kode' => 'waktu', 'label' => 'Waktu', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 100),
        array('kode' => 'tempat', 'label' => 'Tempat', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 110),
    ),
    'blok_tabel' => array(),
);
