-- Basis sync akun login dari RESTU - diminta user 2026-09-05: AURA cuma
-- punya 1 akun (admin.kepegawaian, generik) selama ini, semua jenis surat
-- sebenarnya udah kebuka buat siapa aja yg login (cuma "Kelola Pengguna"
-- yg admin-only) - jadi masalahnya bukan "buka akses", tapi "belum ada
-- akun per-pegawai". RESTU udah punya 13 akun asli (username+password
-- bcrypt+role, terhubung nip->pegawai) - reuse itu.
--
-- Dikonfirmasi user (AskUserQuestion):
-- - Password DISINKRON dari RESTU (1 kredensial, 2 app) - dicek dulu hash
--   RESTU beneran bcrypt ($2y$..., kompatibel password_verify() PHP
--   standar, bukan skema beda) sebelum diputusin aman disalin langsung.
-- - Akses tetap PENUH semua jenis surat (kondisi yg SUDAH ADA sekarang,
--   gak perlu sistem izin baru) - is_admin CUMA soal "boleh kelola akun
--   lain", TETAP dipetakan dari role RESTU (Admin->1/User->0) tapi HANYA
--   pas akun pertama kali dibuat, gak pernah di-timpa ulang tiap sync
--   (biar admin AURA bisa promosikan manual tanpa didemote balik besoknya).
--
-- kunci pencocokan: NIP (bukan username - username RESTU/AURA bisa beda
-- konvensi). Akun generik tanpa NIP (mis. admin.kepegawaian di RESTU
-- sendiri) SENGAJA gak disync - gak ada pegawai riil yg bisa dicocokkan,
-- beda dari 12 akun asli lainnya yg semua ke-link ke NIP pegawai valid.

USE aurat;

ALTER TABLE user_login ADD COLUMN IF NOT EXISTS nip VARCHAR(18) NULL AFTER username;

-- Index unik terpisah (bukan inline di ADD COLUMN) - lebih portable lintas
-- versi MariaDB. Dijaga guard manual krn "ADD INDEX IF NOT EXISTS" utk
-- UNIQUE KEY constraint gak seragam didukung semua versi.
SET @idx_ada = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_login' AND INDEX_NAME = 'uq_user_login_nip'
);
SET @sql = IF(@idx_ada = 0, 'ALTER TABLE user_login ADD UNIQUE KEY uq_user_login_nip (nip)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
