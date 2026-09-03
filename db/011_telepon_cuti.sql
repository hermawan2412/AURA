-- Menambahkan variabel manual "telepon_cuti" (Nomor Telepon yang Bisa Dihubungi)
-- ke bagian "VI. Alamat Selama Menjalankan Cuti", sebaris di bawah alamat -- sama
-- seperti kolom "Telp." pada formulir cuti PA Rantau yang asli.
--
-- Placeholder baru yang WAJIB sudah ada sbg ${...} literal di templates/cuti.docx
-- SEBELUM migrasi ini dijalankan: telepon_cuti
--
-- Jalankan SETELAH db/001-010 (lewat SQLyog: File -> Open -> Execute All / F9).
-- Aman dijalankan ulang berkali-kali (idempoten, NOT EXISTS).

USE aurat;

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'telepon_cuti' AS kode, 'Nomor Telepon yang Bisa Dihubungi' AS label,
  'text' AS tipe_input, 'manual' AS sumber, 0 AS wajib_default, 115 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

INSERT INTO template_surat_variabel (template_surat_id, variabel_surat_id, peran_pegawai_surat_id, urutan_tampil, terdeteksi_otomatis)
SELECT ts.id, vs.id, NULL, 115, 0
FROM template_surat ts
JOIN jenis_surat j ON j.id = ts.jenis_surat_id AND j.kode = 'cuti' AND ts.status_aktif = 1 AND ts.sub_jenis_surat_id IS NULL
JOIN variabel_surat vs ON vs.kode = 'telepon_cuti'
WHERE NOT EXISTS (
  SELECT 1 FROM template_surat_variabel x
  WHERE x.template_surat_id = ts.id AND x.variabel_surat_id = vs.id
);
