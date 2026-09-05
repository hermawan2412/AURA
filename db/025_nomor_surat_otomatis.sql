-- Auto-nomor surat, lanjutan dari nomor_surat_otomatis() (Formatter.php,
-- sudah di-deploy sebelumnya). Diminta user 2026-09-05: kode klasifikasi
-- HARUS bisa diisi admin lewat UI (bukan tulis di kode/SQL tiap kali beda),
-- dan otomatis ikut tanggal/tahun surat dibuat (sudah ditangani
-- nomorSuratOtomatis(), bagian bulan-romawi+tahun dari tanggal dokumen).
--
-- kode_klasifikasi disimpan di jenis_surat (1 kolom, admin edit lewat
-- admin/jenis_surat.php) - BUKAN di template_surat_variabel.fungsi_
-- parameter_1 (itu statis per-attachment, susah diubah). Nilainya
-- di-suntik ke $konteksSistem (surat/index.php) tiap request, jadi variabel
-- 'kode_klasifikasi_surat' (sumber=sistem, sistem_kode=kode_klasifikasi) di
-- bawah ini SATU DEFINISI dipakai bareng oleh SEMUA jenis surat yang
-- pasang nomor otomatis - resolve ke nilai jenis_surat yang lagi diproses,
-- bukan hardcode per jenis.
--
-- Nilai kode_klasifikasi sendiri SENGAJA dibiarkan NULL/kosong di sini -
-- user belum dapat kode asli dari TU/kepegawaian, diisi belakangan lewat
-- admin UI begitu ada. Fungsi nomorSuratOtomatis() sudah aman kalau kosong
-- (return string kosong, bukan error).

USE aurat;

ALTER TABLE jenis_surat ADD COLUMN IF NOT EXISTS kode_klasifikasi VARCHAR(150) NULL AFTER variabel_ringkasan_kode;

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, sistem_kode, wajib_default)
SELECT 'kode_klasifikasi_surat', 'Kode Klasifikasi (dari Jenis Surat)', 'text', 'sistem', 'kode_klasifikasi', 0
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'kode_klasifikasi_surat');
