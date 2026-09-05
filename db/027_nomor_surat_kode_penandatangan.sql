-- Refine format nomor surat otomatis - user kasih contoh nyata:
-- "1697/WKPA.W15-A8/UND.KP3.4.3/IX/2026" - ternyata segmen tengah itu 2
-- bagian (kode PENANDATANGAN, beda2 tergantung SIAPA tanda tangan + kode
-- SATKER, tetap sama semua surat), bukan 1 gabungan kayak yang dipasang
-- db/025/026 kemarin (kode_klasifikasi_surat TETAP cuma buat kode jenis
-- surat, mis "UND.KP3.4.3" - itu bagian yang sudah benar, gak diubah).
--
-- Format baru: {nomor_urut}/{kode_penandatangan}.{kode_satker}/{kode_jenis_surat}/{bulan_romawi}/{tahun}
-- Formatter::nomorSuratOtomatis() nambah 2 parameter (lihat src/Formatter.php,
-- commit sama) - variabel turunan nomor_lengkap* SEMUA di-UPDATE
-- parameter_variabel-nya di sini (bukan bikin baru dgn kode lain, biar
-- yang udah kepasang ke template gak perlu di-lepas-pasang ulang).
--
-- Kode penandatangan (KPA/WKPA/PAN/SEK) di-hardcode di Formatter (cuma 4,
-- dikonfirmasi user - gak perlu tabel mapping admin-editable buat ini).
-- Kode satker (W15-A8) beda: SATU nilai tetap semua surat, TAPI
-- dikonfirmasi user harus bisa diubah admin tanpa deploy kode - disimpan
-- di tabel pengaturan_aplikasi baru (1 baris, pola sama kayak RESTU).

USE aurat;

CREATE TABLE IF NOT EXISTS pengaturan_aplikasi (
  id           TINYINT UNSIGNED NOT NULL,
  kode_satker  VARCHAR(50) NULL,
  updated_at   TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pengaturan_aplikasi (id, kode_satker)
SELECT 1, NULL WHERE NOT EXISTS (SELECT 1 FROM pengaturan_aplikasi WHERE id = 1);

-- Variabel baru: kode_satker_surat (sumber=sistem, global - TIDAK beda per
-- jenis_surat kayak kode_klasifikasi_surat, disuntik sekali ke
-- $konteksSistem di surat/index.php dari pengaturan_aplikasi).
INSERT INTO variabel_surat (kode, label, tipe_input, sumber, sistem_kode, wajib_default)
SELECT 'kode_satker_surat', 'Kode Satuan Kerja (pengaturan aplikasi)', 'text', 'sistem', 'kode_satker', 0
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'kode_satker_surat');

-- Variabel kode_penandatangan_X - beda per PERAN yang jadi penandatangan
-- (bukan per jenis_surat) - sumber=pegawai, field_pegawai=NULL (butuh baris
-- penuh, fungsi_pasca butuh field 'jabatan' pegawai itu), fungsi_pasca baru
-- 'kode_penandatangan_dari_jabatan' (lihat src/Formatter.php).
INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca)
SELECT 'kode_penandatangan_penetap', 'Kode Penandatangan (dari Pejabat yang Menetapkan)', 'text', 'pegawai', 'kode_penandatangan_dari_jabatan'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'kode_penandatangan_penetap');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca)
SELECT 'kode_penandatangan_menyatakan', 'Kode Penandatangan (dari Pejabat yang Menyatakan)', 'text', 'pegawai', 'kode_penandatangan_dari_jabatan'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'kode_penandatangan_menyatakan');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca)
SELECT 'kode_penandatangan_pengambil_sumpah', 'Kode Penandatangan (dari Pejabat yang Mengambil Sumpah)', 'text', 'pegawai', 'kode_penandatangan_dari_jabatan'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'kode_penandatangan_pengambil_sumpah');

-- surat_perintah_plh: penandatangannya BUKAN peran pegawai, tapi dropdown
-- jabatan_diplh (Ketua/Panitera/Sekretaris) yang sudah ada - fungsi_pasca
-- beda ('kode_penandatangan_dari_teks', nerima string biasa bukan baris
-- pegawai).
INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca, parameter_variabel)
SELECT 'kode_penandatangan_plh', 'Kode Penandatangan (dari Jabatan yang Di-PLH-kan)', 'text', 'turunan', 'kode_penandatangan_dari_teks', '["jabatan_diplh"]'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'kode_penandatangan_plh');

-- surat_tugas & undangan: peran penandatangan BELUM ADA sama sekali (masih
-- teks statis "Ketua Pengadilan Agama Rantau" di docx) - peran + variabel
-- kode_penandatangan_penandatangan disiapkan di sini, PEMASANGAN ke
-- template (+ ganti teks statis jadi macro picker) dikerjakan terpisah
-- lewat migrasi PHP (butuh insert conditional per jenis_surat_id yg beda).
INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil)
SELECT js.id, 'penandatangan', 'Pejabat Penandatangan', 1, 0
FROM jenis_surat js WHERE js.kode IN ('surat_tugas', 'undangan')
  AND NOT EXISTS (SELECT 1 FROM peran_pegawai_surat WHERE jenis_surat_id = js.id AND kode = 'penandatangan');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca)
SELECT 'penandatangan_nama_lengkap', 'Nama Lengkap (Penandatangan)', 'text', 'pegawai', 'nama_bergelar'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'penandatangan_nama_lengkap');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai)
SELECT 'penandatangan_jabatan', 'Jabatan (Penandatangan)', 'text', 'pegawai', 'jabatan'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'penandatangan_jabatan');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca)
SELECT 'kode_penandatangan_penandatangan', 'Kode Penandatangan (dari Pejabat Penandatangan)', 'text', 'pegawai', 'kode_penandatangan_dari_jabatan'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'kode_penandatangan_penandatangan');

-- Update parameter_variabel variabel nomor_lengkap* yang SUDAH ADA (db/026)
-- ke urutan argumen baru Formatter::nomorSuratOtomatis() yg sekarang 5
-- parameter: [nomor_urut, tanggal, kode_penandatangan_X, kode_satker_surat, kode_klasifikasi_surat]
UPDATE variabel_surat SET parameter_variabel = '["nomor_urut","tanggal_penetapan","kode_penandatangan_penetap","kode_satker_surat","kode_klasifikasi_surat"]'
  WHERE kode = 'nomor_lengkap_sk';
UPDATE variabel_surat SET parameter_variabel = '["nomor_urut","pembukaan_tanggal","kode_penandatangan_pengambil_sumpah","kode_satker_surat","kode_klasifikasi_surat"]'
  WHERE kode = 'nomor_lengkap_bas';
-- nomor_lengkap (dulu dipakai bareng pernyataan_melaksanakan_tugas/undangan/
-- surat_tugas/surat_perintah_plh) di-split: sisa jadi milik
-- pernyataan_melaksanakan_tugas doang (peran menyatakan), 3 jenis surat
-- lain pindah ke variabel nomor_lengkap_* baru masing2 (dipasang terpisah
-- lewat migrasi PHP, krn perlu ganti attachment template_surat_variabel
-- juga, bukan cuma UPDATE definisi variabelnya).
UPDATE variabel_surat SET parameter_variabel = '["nomor_urut","tanggal_surat","kode_penandatangan_menyatakan","kode_satker_surat","kode_klasifikasi_surat"]'
  WHERE kode = 'nomor_lengkap';

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca, parameter_variabel)
SELECT 'nomor_lengkap_plh', 'Nomor Surat Lengkap (otomatis)', 'text', 'turunan', 'nomor_surat_otomatis',
       '["nomor_urut","tanggal_surat","kode_penandatangan_plh","kode_satker_surat","kode_klasifikasi_surat"]'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'nomor_lengkap_plh');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca, parameter_variabel)
SELECT 'nomor_lengkap_ttd', 'Nomor Surat Lengkap (otomatis)', 'text', 'turunan', 'nomor_surat_otomatis',
       '["nomor_urut","tanggal_surat","kode_penandatangan_penandatangan","kode_satker_surat","kode_klasifikasi_surat"]'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'nomor_lengkap_ttd');
