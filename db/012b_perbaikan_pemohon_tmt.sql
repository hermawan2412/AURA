-- Perbaikan: kalau setelah menjalankan db/012_tmt_pegawai_ganti_sumber_masa_kerja.sql
-- muncul error "Variabel 'pemohon_tmt' dipakai sbg parameter tapi tidak terpasang ke
-- template ini" saat generate surat Cuti, berarti bagian wiring (variabel_surat +
-- template_surat_variabel) untuk pemohon_tmt tidak sempat tersimpan di server,
-- walau bagian UPDATE pemohon_masa_kerja sudah berhasil (makanya errornya
-- menyebut pemohon_tmt secara spesifik).
--
-- Skrip ini HANYA mengulang bagian wiring itu -- aman dijalankan berkali-kali
-- (idempoten, NOT EXISTS), tidak menyentuh kolom pegawai.tmt yang sudah ada.
--
-- Jalankan di SQLyog: File -> Open -> Execute All / F9.

USE aurat;

-- Pastikan kolom pegawai.tmt memang sudah ada (kalau migrasi 012 memang jalan
-- sampai situ). Kalau belum ada, baris ini akan membuatnya.
SET @kolom_ada := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pegawai' AND COLUMN_NAME = 'tmt'
);
SET @sql := IF(@kolom_ada = 0,
  'ALTER TABLE pegawai ADD COLUMN tmt DATE NULL AFTER unit_kerja',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'pemohon_tmt' AS kode, 'TMT Pemohon (internal, dipakai hitung Masa Kerja)' AS label,
  'text' AS tipe_input, 'pegawai' AS sumber, 'tmt' AS field_pegawai, 0 AS wajib_default, 44 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

INSERT INTO template_surat_variabel (template_surat_id, variabel_surat_id, peran_pegawai_surat_id, urutan_tampil, terdeteksi_otomatis)
SELECT ts.id, vs.id, pp.id, 44, 0
FROM template_surat ts
JOIN jenis_surat j ON j.id = ts.jenis_surat_id AND j.kode = 'cuti' AND ts.status_aktif = 1 AND ts.sub_jenis_surat_id IS NULL
JOIN peran_pegawai_surat pp ON pp.jenis_surat_id = j.id AND pp.kode = 'pemohon'
JOIN variabel_surat vs ON vs.kode = 'pemohon_tmt'
WHERE NOT EXISTS (
  SELECT 1 FROM template_surat_variabel x
  WHERE x.template_surat_id = ts.id AND x.variabel_surat_id = vs.id
);

-- Pastikan juga pemohon_masa_kerja memang sudah menunjuk ke pemohon_tmt (kalau belum,
-- perbaiki -- aman dijalankan meski sudah benar sebelumnya).
UPDATE variabel_surat
SET parameter_variabel = '["pemohon_tmt","tanggal_surat"]',
    fungsi_pasca = 'masa_kerja_dari_tmt'
WHERE kode = 'pemohon_masa_kerja';

-- Verifikasi hasil -- jalankan query ini terpisah kalau mau lihat hasilnya:
-- SELECT tsv.id, vs.kode, vs.sumber FROM template_surat_variabel tsv
-- JOIN template_surat ts ON ts.id = tsv.template_surat_id
-- JOIN jenis_surat j ON j.id = ts.jenis_surat_id AND j.kode = 'cuti'
-- JOIN variabel_surat vs ON vs.id = tsv.variabel_surat_id
-- WHERE ts.status_aktif = 1 AND vs.kode = 'pemohon_tmt';
