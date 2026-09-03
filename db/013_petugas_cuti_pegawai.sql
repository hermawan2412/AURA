-- Menambahkan peran pegawai baru "Petugas Cuti" ke surat Cuti -- sebelumnya bagian
-- V "Paraf Petugas Cuti" cuma garis kosong tulis tangan, sekarang jadi dropdown pilih
-- pegawai (sama seperti Pemohon/Atasan Langsung/Pejabat Berwenang), tapi outputnya
-- CUMA nama (tanpa NIP) karena cuma dipakai sbg label di kolom paraf, bukan blok
-- tanda tangan resmi.
--
-- Placeholder baru yang WAJIB sudah ada sbg ${...} literal di templates/cuti.docx
-- SEBELUM migrasi ini dijalankan: petugas_cuti_nama
--
-- Jalankan SETELAH db/001-012 (lewat SQLyog: File -> Open -> Execute All / F9).
-- Aman dijalankan ulang berkali-kali (idempoten, NOT EXISTS).

USE aurat;

INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil)
SELECT j.id, 'petugas_cuti', 'Petugas Cuti', 1, 40
FROM jenis_surat j
WHERE j.kode = 'cuti'
  AND NOT EXISTS (
    SELECT 1 FROM peran_pegawai_surat p WHERE p.jenis_surat_id = j.id AND p.kode = 'petugas_cuti'
  );

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai, fungsi_pasca, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'petugas_cuti_nama' AS kode, 'Nama Petugas Cuti (bergelar)' AS label,
  'text' AS tipe_input, 'pegawai' AS sumber, NULL AS field_pegawai, 'nama_bergelar' AS fungsi_pasca,
  1 AS wajib_default, 46 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

INSERT INTO template_surat_variabel (template_surat_id, variabel_surat_id, peran_pegawai_surat_id, urutan_tampil, terdeteksi_otomatis)
SELECT ts.id, vs.id, pp.id, 46, 0
FROM template_surat ts
JOIN jenis_surat j ON j.id = ts.jenis_surat_id AND j.kode = 'cuti' AND ts.status_aktif = 1 AND ts.sub_jenis_surat_id IS NULL
JOIN peran_pegawai_surat pp ON pp.jenis_surat_id = j.id AND pp.kode = 'petugas_cuti'
JOIN variabel_surat vs ON vs.kode = 'petugas_cuti_nama'
WHERE NOT EXISTS (
  SELECT 1 FROM template_surat_variabel x
  WHERE x.template_surat_id = ts.id AND x.variabel_surat_id = vs.id
);
