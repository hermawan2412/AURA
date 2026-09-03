<?php
// Definisi migrasi "sk" (Surat Keputusan) — dari surat/sk.php + config JSON. Kasus PALING
// kompleks: kategori dua_dokumen (2 template berbeda, satu per sub_jenis) + blok tabel dgn
// override kolom per sub_jenis. Dikonfirmasi via TemplateProcessor::getVariables() bahwa
// KEDUA docx berbagi 8 placeholder skalar yang identik (nomor_sk, tentang, menimbang,
// mengingat, diktum, tanggal_penetapan, penetap_nama_lengkap, penetap_nip) — hanya kolom
// tabelnya beda. JSON lama menandai kolom "nama" sbg pegawai_field/nama_lengkap, tapi kode
// aslinya pakai Formatter::namaBergelar($p) — dipetakan sbg pegawai_fungsi/nama_bergelar
// (perilaku nyata, bukan JSON yg tak pernah diimplementasikan persis).

return array(
    'jenis_surat' => array(
        'kode'              => 'sk',
        'nama'              => 'Surat Keputusan',
        'deskripsi'         => null,
        'kategori'          => 'dua_dokumen',
        'kop_surat'         => 'gambar_sk',
        'pola_nama_unduhan' => 'SK_{nomor_sk}',
        'urutan_tampil'     => 70,
    ),

    'sub_jenis' => array(
        array('kode' => 'tim_kerja', 'label' => 'SK Tim Kerja', 'urutan_tampil' => 10),
        array('kode' => 'panitia', 'label' => 'SK Panitia Kegiatan', 'urutan_tampil' => 20),
    ),

    'peran_pegawai' => array(
        array('kode' => 'penetap', 'label' => 'Pejabat yang Menetapkan', 'wajib' => 1, 'urutan_tampil' => 10),
    ),

    // dua_dokumen -> dua berkas, masing2 diberi sub_jenis_kode-nya sendiri. Semua variabel
    // di bawah dipasang ke KEDUA template (skrip migrasi loop per template_list).
    'template_list' => array(
        array('sub_jenis_kode' => 'tim_kerja', 'sumber_path' => __DIR__ . '/../../templates/sk_tim_kerja.docx', 'nama_asli' => 'sk_tim_kerja.docx'),
        array('sub_jenis_kode' => 'panitia', 'sumber_path' => __DIR__ . '/../../templates/sk_panitia.docx', 'nama_asli' => 'sk_panitia.docx'),
    ),

    'variabel' => array(
        array('kode' => 'nomor_sk', 'label' => 'Nomor SK', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 10),
        array('kode' => 'tanggal_penetapan', 'label' => 'Tanggal Penetapan', 'sumber' => 'manual',
              'tipe_input' => 'date', 'wajib_default' => 1, 'fungsi_pasca' => 'tanggal_indonesia', 'urutan_tampil' => 20),
        array('kode' => 'tentang', 'label' => 'Tentang / Perihal (judul lengkap, termasuk tahun)', 'sumber' => 'manual',
              'tipe_input' => 'text', 'wajib_default' => 1, 'urutan_tampil' => 30),
        array('kode' => 'menimbang', 'label' => 'Menimbang (bernomor a, b, c, ...)', 'sumber' => 'manual',
              'tipe_input' => 'textarea', 'wajib_default' => 1, 'urutan_tampil' => 40),
        array('kode' => 'mengingat', 'label' => 'Mengingat (bernomor 1, 2, 3, ...)', 'sumber' => 'manual',
              'tipe_input' => 'textarea', 'wajib_default' => 1, 'urutan_tampil' => 50),
        array('kode' => 'diktum', 'label' => 'Diktum (KESATU, KEDUA, KETIGA, ...)', 'sumber' => 'manual',
              'tipe_input' => 'textarea', 'wajib_default' => 1, 'urutan_tampil' => 60),
        array('kode' => 'penetap_nama_lengkap', 'label' => 'Nama Lengkap (Penetap)', 'sumber' => 'pegawai',
              'peran_kode' => 'penetap', 'field_pegawai' => null, 'fungsi_pasca' => 'nama_bergelar', 'urutan_tampil' => 70),
        array('kode' => 'penetap_nip', 'label' => 'NIP (Penetap)', 'sumber' => 'pegawai',
              'peran_kode' => 'penetap', 'field_pegawai' => 'nip', 'urutan_tampil' => 80),
    ),

    'blok_tabel' => array(
        // Default (dipakai tim_kerja, karena tidak ada override utk sub_jenis itu)
        array(
            'kode' => 'no', 'sub_jenis_kode' => null, 'nama_anchor_kolom' => 'no',
            'label' => 'Lampiran Daftar Pegawai', 'minimal_baris' => 1,
            'kolom' => array(
                array('kode' => 'no', 'label' => 'No', 'sumber' => 'auto_nomor', 'urutan_kolom' => 10),
                array('kode' => 'nama', 'label' => 'Nama', 'sumber' => 'pegawai_fungsi', 'fungsi_pasca' => 'nama_bergelar', 'urutan_kolom' => 20),
                array('kode' => 'nip', 'label' => 'NIP', 'sumber' => 'pegawai_field', 'field_pegawai' => 'nip', 'urutan_kolom' => 30),
                array('kode' => 'jabatan', 'label' => 'Jabatan', 'sumber' => 'pegawai_field', 'field_pegawai' => 'jabatan', 'urutan_kolom' => 40),
                array('kode' => 'kedudukan', 'label' => 'Kedudukan', 'sumber' => 'manual_per_baris', 'urutan_kolom' => 50),
            ),
        ),
        // Override khusus panitia — kode blok SAMA ('no') supaya BlokTabelRepository menimpa default
        array(
            'kode' => 'no', 'sub_jenis_kode' => 'panitia', 'nama_anchor_kolom' => 'no',
            'label' => 'Lampiran Daftar Panitia', 'minimal_baris' => 1,
            'kolom' => array(
                array('kode' => 'no', 'label' => 'No', 'sumber' => 'auto_nomor', 'urutan_kolom' => 10),
                array('kode' => 'nama', 'label' => 'Nama', 'sumber' => 'pegawai_fungsi', 'fungsi_pasca' => 'nama_bergelar', 'urutan_kolom' => 20),
                array('kode' => 'nip', 'label' => 'NIP', 'sumber' => 'pegawai_field', 'field_pegawai' => 'nip', 'urutan_kolom' => 30),
                array('kode' => 'peran_panitia', 'label' => 'Peran dalam Panitia', 'sumber' => 'manual_per_baris', 'urutan_kolom' => 40),
            ),
        ),
    ),
);
