-- Menambahkan variabel turunan "pemohon_masa_kerja" ke surat Cuti: "X Tahun Y Bulan",
-- dihitung otomatis dari NIP pemohon (posisi 9-12 = tahun TMT, posisi 13-14 = bulan TMT,
-- format NIP PNS 18-digit standar) terhadap tanggal_surat -- lihat
-- Aurat\Formatter::masaKerjaDariNip() (src/Formatter.php) dan whitelist fungsi_pasca
-- 'masa_kerja_dari_nip' di src/Surat/NilaiResolver.php.
--
-- PENTING: berbeda dari migrasi lain di sini, perubahan ini juga BUTUH file kode PHP
-- diupload (bukan cuma docx + SQL) -- src/Formatter.php dan src/Surat/NilaiResolver.php --
-- karena fungsi_pasca ini baru, belum ada di kode yang sudah berjalan di server.
--
-- Placeholder baru yang WAJIB sudah ada sbg ${...} literal di templates/cuti.docx
-- SEBELUM migrasi ini dijalankan: pemohon_masa_kerja
--
-- Jalankan SETELAH db/001-007 (lewat SQLyog: File -> Open -> Execute All / F9).
-- Aman dijalankan ulang berkali-kali (idempoten, NOT EXISTS).

USE aurat;

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, parameter_variabel, fungsi_pasca, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'pemohon_masa_kerja' AS kode, 'Masa Kerja Pemohon' AS label,
  'text' AS tipe_input, 'turunan' AS sumber,
  '["pemohon_nip","tanggal_surat"]' AS parameter_variabel,
  'masa_kerja_dari_nip' AS fungsi_pasca,
  0 AS wajib_default, 45 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

INSERT INTO template_surat_variabel (template_surat_id, variabel_surat_id, peran_pegawai_surat_id, urutan_tampil, terdeteksi_otomatis)
SELECT ts.id, vs.id, NULL, 45, 0
FROM template_surat ts
JOIN jenis_surat j ON j.id = ts.jenis_surat_id AND j.kode = 'cuti' AND ts.status_aktif = 1 AND ts.sub_jenis_surat_id IS NULL
JOIN variabel_surat vs ON vs.kode = 'pemohon_masa_kerja'
WHERE NOT EXISTS (
  SELECT 1 FROM template_surat_variabel x
  WHERE x.template_surat_id = ts.id AND x.variabel_surat_id = vs.id
);
