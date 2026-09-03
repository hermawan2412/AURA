-- Mengganti sumber perhitungan "Masa Kerja" dari parsing digit NIP (rapuh -- format
-- NIP PPPK ternyata BEDA dari PNS, posisi 9-14 bukan tahun+bulan TMT yang valid utk
-- PPPK, sehingga hasilnya kosong) menjadi kolom TMT eksplisit yang diisi manual oleh
-- petugas kepegawaian di halaman Data Pegawai (pegawai.php) -- berlaku sama utk PNS
-- maupun PPPK, tidak bergantung tebak-tebakan format NIP.
--
-- PENTING: setelah migrasi ini, "Masa Kerja" akan TAMPIL KOSONG untuk semua pegawai
-- sampai TMT-nya diisi manual satu-satu lewat halaman Data Pegawai (kolom baru
-- "TMT (Mulai Kerja)") -- ini kondisi normal/diharapkan, bukan bug.
--
-- PENTING JUGA: perubahan ini BUTUH src/Formatter.php, src/Surat/NilaiResolver.php,
-- DAN pegawai.php diupload (bukan cuma SQL) -- fungsi masaKerjaDariNip() diganti jadi
-- masaKerjaDariTmt(), dan field_pegawai 'tmt' baru ditambah ke whitelist.
--
-- Jalankan SETELAH db/001-011 (lewat SQLyog: File -> Open -> Execute All / F9).
-- Aman dijalankan ulang berkali-kali (ALTER TABLE dijaga IF NOT EXISTS via prosedur
-- kecil di bawah karena MariaDB versi lama tidak semua dukung "ADD COLUMN IF NOT EXISTS").

USE aurat;

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

-- Variabel baru: nilai mentah TMT pemohon (dari kolom pegawai.tmt), dipakai HANYA
-- sbg parameter utk pemohon_masa_kerja -- tidak wajib jadi placeholder ${...} sendiri.
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

-- pemohon_masa_kerja: ganti parameter & fungsi_pasca dari NIP-parsing ke TMT langsung.
UPDATE variabel_surat
SET parameter_variabel = '["pemohon_tmt","tanggal_surat"]',
    fungsi_pasca = 'masa_kerja_dari_tmt'
WHERE kode = 'pemohon_masa_kerja';
