-- Menambahkan 2 peran pegawai baru khusus surat Cuti: "Atasan Langsung" dan
-- "Pejabat yang Berwenang Memberikan Cuti" -- sebelumnya bagian VII & VIII di
-- templates/cuti.docx cuma baris kosong yang ditulis/ditandatangani tangan
-- setelah dokumen dicetak. Sekarang jadi dropdown pilih pegawai (sama seperti
-- peran 'pemohon' yang sudah ada), lalu nama/NIP/jabatannya otomatis terisi
-- dari data pegawai -- konsisten dengan cara kerja peran_pegawai lain di app ini.
--
-- Placeholder baru yang WAJIB sudah ada sbg ${...} literal di templates/cuti.docx
-- SEBELUM migrasi ini dijalankan (kalau belum, upload dulu versi terbaru berkas itu):
--   atasan_langsung_nama_lengkap, atasan_langsung_nip, atasan_langsung_jabatan,
--   pejabat_berwenang_nama_lengkap, pejabat_berwenang_nip, pejabat_berwenang_jabatan
--
-- Jalankan SETELAH db/001-006 (lewat SQLyog: File -> Open -> Execute All / F9).
-- Aman dijalankan ulang berkali-kali (idempoten, semua INSERT dijaga NOT EXISTS).

USE aurat;

-- 1) Dua peran pegawai baru untuk jenis_surat 'cuti'
INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil)
SELECT j.id, 'atasan_langsung', 'Atasan Langsung', 1, 20
FROM jenis_surat j
WHERE j.kode = 'cuti'
  AND NOT EXISTS (
    SELECT 1 FROM peran_pegawai_surat p WHERE p.jenis_surat_id = j.id AND p.kode = 'atasan_langsung'
  );

INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil)
SELECT j.id, 'pejabat_berwenang', 'Pejabat yang Berwenang Memberikan Cuti', 1, 30
FROM jenis_surat j
WHERE j.kode = 'cuti'
  AND NOT EXISTS (
    SELECT 1 FROM peran_pegawai_surat p WHERE p.jenis_surat_id = j.id AND p.kode = 'pejabat_berwenang'
  );

-- 2) Enam variabel baru (nama bergelar, NIP, jabatan x 2 peran)
INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai, fungsi_pasca, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'atasan_langsung_nama_lengkap' AS kode, 'Nama Lengkap Atasan Langsung (bergelar)' AS label,
  'text' AS tipe_input, 'pegawai' AS sumber, NULL AS field_pegawai, 'nama_bergelar' AS fungsi_pasca,
  1 AS wajib_default, 130 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai, fungsi_pasca, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'atasan_langsung_nip' AS kode, 'NIP Atasan Langsung' AS label,
  'text' AS tipe_input, 'pegawai' AS sumber, 'nip' AS field_pegawai, NULL AS fungsi_pasca,
  1 AS wajib_default, 140 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai, fungsi_pasca, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'atasan_langsung_jabatan' AS kode, 'Jabatan Atasan Langsung' AS label,
  'text' AS tipe_input, 'pegawai' AS sumber, 'jabatan' AS field_pegawai, NULL AS fungsi_pasca,
  1 AS wajib_default, 150 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai, fungsi_pasca, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'pejabat_berwenang_nama_lengkap' AS kode, 'Nama Lengkap Pejabat Berwenang (bergelar)' AS label,
  'text' AS tipe_input, 'pegawai' AS sumber, NULL AS field_pegawai, 'nama_bergelar' AS fungsi_pasca,
  1 AS wajib_default, 160 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai, fungsi_pasca, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'pejabat_berwenang_nip' AS kode, 'NIP Pejabat Berwenang' AS label,
  'text' AS tipe_input, 'pegawai' AS sumber, 'nip' AS field_pegawai, NULL AS fungsi_pasca,
  1 AS wajib_default, 170 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai, fungsi_pasca, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'pejabat_berwenang_jabatan' AS kode, 'Jabatan Pejabat Berwenang' AS label,
  'text' AS tipe_input, 'pegawai' AS sumber, 'jabatan' AS field_pegawai, NULL AS fungsi_pasca,
  1 AS wajib_default, 180 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

-- 3) Pasang ke-6 variabel di atas ke template 'cuti' yang sedang aktif, terhubung ke peran yang sesuai
INSERT INTO template_surat_variabel (template_surat_id, variabel_surat_id, peran_pegawai_surat_id, urutan_tampil, terdeteksi_otomatis)
SELECT ts.id, vs.id, pp.id, urut.n, 0
FROM template_surat ts
JOIN jenis_surat j ON j.id = ts.jenis_surat_id AND j.kode = 'cuti' AND ts.status_aktif = 1 AND ts.sub_jenis_surat_id IS NULL
JOIN peran_pegawai_surat pp ON pp.jenis_surat_id = j.id
JOIN (
  SELECT 'atasan_langsung' AS peran, 'atasan_langsung_nama_lengkap' AS kode, 130 AS n
  UNION ALL SELECT 'atasan_langsung', 'atasan_langsung_nip', 140
  UNION ALL SELECT 'atasan_langsung', 'atasan_langsung_jabatan', 150
  UNION ALL SELECT 'pejabat_berwenang', 'pejabat_berwenang_nama_lengkap', 160
  UNION ALL SELECT 'pejabat_berwenang', 'pejabat_berwenang_nip', 170
  UNION ALL SELECT 'pejabat_berwenang', 'pejabat_berwenang_jabatan', 180
) urut ON urut.peran = pp.kode
JOIN variabel_surat vs ON vs.kode = urut.kode
WHERE NOT EXISTS (
  SELECT 1 FROM template_surat_variabel x
  WHERE x.template_surat_id = ts.id AND x.variabel_surat_id = vs.id
);
