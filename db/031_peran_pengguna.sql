-- 3 peran akun (diminta user 2026-09-05, mengganti model is_admin biner
-- lama): 'pengguna' (default, CUMA boleh akses Izin Keluar Kantor - satu-
-- satunya jenis surat yang whitelist-nya di-hardcode di src/Auth.php,
-- bukan data-driven, krn cuma 1 pengecualian yang diminta, YAGNI buat
-- bikin ini configurable dulu), 'pengelola' (baru - akses semua fungsi
-- KECUALI Kelola Pengguna & Pengaturan Aplikasi), 'admin' (akses penuh,
-- setara is_admin=1 lama).
--
-- is_admin DIHAPUS (diganti kolom peran) - satu sumber kebenaran, bukan 2
-- kolom yang bisa gak sinkron. Backfill: is_admin=1 -> 'admin', selebihnya
-- (akun yang udah ada) -> 'pengguna' (paling ketat by default, admin bisa
-- naikkan manual ke 'pengelola' lewat Kelola Pengguna kalau perlu -
-- konsisten sama filosofi "default paling restrictive" utk akun baru).
--
-- Backfill+DROP is_admin dijaga guard manual (bukan ALTER...DROP COLUMN
-- IF EXISTS polos) - biar re-run migrasi ini gak gagal di UPDATE ... WHERE
-- is_admin=1 pas kolomnya udah beneran ilang dari run pertama.

USE aurat;

ALTER TABLE user_login ADD COLUMN IF NOT EXISTS peran ENUM('pengguna','pengelola','admin') NOT NULL DEFAULT 'pengguna' AFTER nip;

SET @kolom_is_admin_ada = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_login' AND COLUMN_NAME = 'is_admin'
);

SET @sql_backfill = IF(@kolom_is_admin_ada > 0,
  'UPDATE user_login SET peran = ''admin'' WHERE is_admin = 1',
  'SELECT 1');
PREPARE stmt FROM @sql_backfill;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_drop = IF(@kolom_is_admin_ada > 0,
  'ALTER TABLE user_login DROP COLUMN is_admin',
  'SELECT 1');
PREPARE stmt FROM @sql_drop;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
