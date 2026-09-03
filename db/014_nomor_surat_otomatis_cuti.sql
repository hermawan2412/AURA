-- Nomor surat Cuti sekarang disusun otomatis: petugas cuma input nomor urutnya
-- (mis. "88"), sisanya "/KPA.W15-A8/KP5.3/[bulan romawi]/[tahun]" ditempel otomatis
-- (bulan+tahun dari Tanggal Surat) lewat Aurat\Formatter::nomorSuratCuti().
--
-- PENTING: variabel_surat "nomor_surat" (yang lama, manual, teks bebas) dipakai
-- SHARED oleh 6 jenis surat (cuti, berita_acara_sumpah, pelaksana_harian,
-- pernyataan_melaksanakan_tugas, surat_tugas, undangan) -- lihat catatan migrasi 006.
-- Migrasi ini SENGAJA TIDAK mengubah variabel_surat "nomor_surat" itu sendiri (supaya
-- 5 jenis surat lain tidak ikut berubah) -- sebagai gantinya dibuat 2 variabel BARU
-- yang scope-nya cuma surat Cuti, dan baris template_surat_variabel yang menghubungkan
-- "nomor_surat" (lama) ke template Cuti DIHAPUS (bukan variabelnya, cuma link-nya).
--
-- Placeholder baru yang WAJIB sudah ada sbg ${...} literal di templates/cuti.docx
-- SEBELUM migrasi ini dijalankan: nomor_surat_cuti_lengkap (MENGGANTIKAN ${nomor_surat}
-- yang sebelumnya dipakai di baris "Nomor: ..." template).
--
-- Jalankan SETELAH db/001-013 (lewat SQLyog: File -> Open -> Execute All / F9).
-- Aman dijalankan ulang berkali-kali (idempoten, NOT EXISTS / DELETE yang sama hasilnya).

USE aurat;

-- 1) Lepas link "nomor_surat" (lama, shared) dari template Cuti saja -- variabel dan
--    penggunaannya di 5 jenis surat lain tidak disentuh.
DELETE tsv FROM template_surat_variabel tsv
JOIN template_surat ts ON ts.id = tsv.template_surat_id
JOIN jenis_surat j ON j.id = ts.jenis_surat_id AND j.kode = 'cuti' AND ts.status_aktif = 1 AND ts.sub_jenis_surat_id IS NULL
JOIN variabel_surat vs ON vs.id = tsv.variabel_surat_id AND vs.kode = 'nomor_surat';

-- 2) Variabel baru: input manual nomor urut saja.
INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'nomor_urut_cuti' AS kode, 'Nomor Urut Surat' AS label,
  'text' AS tipe_input, 'manual' AS sumber, 1 AS wajib_default, 48 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

-- 3) Variabel baru: hasil gabungan otomatis, ini yang jadi placeholder di docx.
INSERT INTO variabel_surat (kode, label, tipe_input, sumber, parameter_variabel, fungsi_pasca, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'nomor_surat_cuti_lengkap' AS kode, 'Nomor Surat Lengkap (otomatis)' AS label,
  'text' AS tipe_input, 'turunan' AS sumber,
  '["nomor_urut_cuti","tanggal_surat"]' AS parameter_variabel,
  'nomor_surat_cuti' AS fungsi_pasca, 0 AS wajib_default, 49 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

-- 4) Pasang keduanya ke template Cuti.
INSERT INTO template_surat_variabel (template_surat_id, variabel_surat_id, peran_pegawai_surat_id, urutan_tampil, terdeteksi_otomatis)
SELECT ts.id, vs.id, NULL, urut.n, 0
FROM template_surat ts
JOIN jenis_surat j ON j.id = ts.jenis_surat_id AND j.kode = 'cuti' AND ts.status_aktif = 1 AND ts.sub_jenis_surat_id IS NULL
JOIN (
  SELECT 'nomor_urut_cuti' AS kode, 48 AS n
  UNION ALL SELECT 'nomor_surat_cuti_lengkap', 49
) urut ON 1 = 1
JOIN variabel_surat vs ON vs.kode = urut.kode
WHERE NOT EXISTS (
  SELECT 1 FROM template_surat_variabel x
  WHERE x.template_surat_id = ts.id AND x.variabel_surat_id = vs.id
);
