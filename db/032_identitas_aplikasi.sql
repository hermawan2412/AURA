-- Nama aplikasi, subjudul login, dan nama instansi sekarang bisa diedit admin
-- (halaman Kelola Pengaturan) - sebelumnya hardcode "AURA" / "Aplikasi Untuk
-- suRAt" / "Sekretariat · Bagian Kepegawaian" tersebar di login.php dan
-- views/layout_atas.php (2 tempat beda teks: "Sekretariat · Bagian
-- Kepegawaian" di login, "Bagian Kepegawaian" doang di sidebar - diseragamkan
-- jadi 1 kolom, dipakai di kedua tempat). Nambah kolom ke pengaturan_aplikasi
-- yang sudah ada (db/027), bukan bikin tabel baru - masih 1 baris.

USE aurat;

ALTER TABLE pengaturan_aplikasi
  ADD COLUMN IF NOT EXISTS nama_aplikasi VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS subjudul_login VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS nama_instansi VARCHAR(150) NULL;

-- Isi nilai default persis teks lama yang di-hardcode, biar tampilan gak
-- berubah sampai admin benar-benar edit lewat halaman Pengaturan.
UPDATE pengaturan_aplikasi
SET nama_aplikasi = 'AURA',
    subjudul_login = 'Aplikasi Untuk suRAt',
    nama_instansi = 'Sekretariat · Bagian Kepegawaian'
WHERE id = 1 AND nama_aplikasi IS NULL;
