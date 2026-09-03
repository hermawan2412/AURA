-- Tambahan: peran admin pada user_login. Sebelumnya semua akun berperan seragam
-- (lihat komentar lama di src/Auth.php) -- sekarang dibedakan: hanya akun is_admin=1
-- yang melihat menu & bisa membuka halaman Kelola Pengguna (admin/user_login.php).
--
-- Jalankan SETELAH db/002_generic_surat_engine.sql, db/003_blok_tabel_fungsi_pasca.sql,
-- dan db/004_ikon_jenis_surat.sql:
--   mysql -u <user> -p aurat < db/005_peran_admin_user_login.sql

USE aurat;

ALTER TABLE user_login
  ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER nama_tampilan;

-- Akun 'admin' (kalau ada) dijadikan administrator.
UPDATE user_login SET is_admin = 1 WHERE username = 'admin';

-- Jaga-jaga supaya tidak ada instalasi yang berakhir nol admin (mis. instalasi lama
-- yang cuma punya 'admin.kepegawaian', tanpa akun bernama persis 'admin'): kalau
-- update di atas tidak kena siapa pun, akun dgn id terkecil otomatis dijadikan admin.
-- Boleh diubah manual sesudahnya lewat menu Kelola Pengguna.
UPDATE user_login
  SET is_admin = 1
  WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM user_login) t)
    AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM user_login WHERE is_admin = 1 LIMIT 1) x);
