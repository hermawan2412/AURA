-- Struktur database — AURAT (Aplikasi Untuk Persuratan)
-- Skema bisa berkembang lewat ALTER TABLE seiring kebutuhan jenis surat baru.
-- Jalankan file ini sekali di MariaDB server untuk membuat database awal.

CREATE DATABASE IF NOT EXISTS aurat
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aurat;

-- ============================================================
-- pegawai
-- Dibangun independen (bukan sinkronisasi SIMPEG).
-- ============================================================
CREATE TABLE pegawai (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nip               VARCHAR(18)  NOT NULL,
  nama_lengkap      VARCHAR(150) NOT NULL,
  gelar_depan       VARCHAR(30)  NULL,
  gelar_belakang    VARCHAR(50)  NULL,
  pangkat           VARCHAR(100) NULL,
  golongan_ruang    VARCHAR(10)  NULL,
  jabatan           VARCHAR(150) NULL,
  unit_kerja        VARCHAR(150) NULL,
  status_aktif      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    NULL     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pegawai_nip (nip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- user_login
-- Berdiri sendiri, tidak direlasikan ke pegawai. Peran seragam
-- (tanpa pembedaan hak akses) untuk 1-3 administrator kepegawaian.
-- ============================================================
CREATE TABLE user_login (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username            VARCHAR(50)  NOT NULL,
  password_hash       VARCHAR(255) NOT NULL,
  nama_tampilan       VARCHAR(150) NOT NULL,
  status_aktif        TINYINT(1)   NOT NULL DEFAULT 1,
  percobaan_gagal     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  terkunci_hingga     DATETIME     NULL,
  login_terakhir_at   DATETIME     NULL,
  created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    NULL     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_login_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Data contoh — supaya alur Surat Cuti bisa langsung dicoba
-- ============================================================
INSERT INTO pegawai (nip, nama_lengkap, gelar_depan, gelar_belakang, pangkat, golongan_ruang, jabatan, unit_kerja) VALUES
('197803152006042001', 'Sri Wahyuni', NULL, 'S.H.', 'Penata Tk. I', 'III/d', 'Kepala Subbagian Umum', 'Sekretariat'),
('198511202010011003', 'Ahmad Fauzan', NULL, 'S.Kom.', 'Penata Muda', 'III/b', 'Analis Kepegawaian', 'Bagian Kepegawaian'),
('199002142015032002', 'Dewi Kartika', NULL, 'A.Md.', 'Pengatur Tk. I', 'III/a', 'Staf Administrasi', 'Bagian Umum'),
('197209101999031005', 'Bambang Suryanto', 'Drs.', 'M.H.', 'Pembina', 'IV/a', 'Kepala Bidang Pelayanan', 'Bidang Pelayanan'),
('199407082019022001', 'Rina Marlina', NULL, NULL, 'Pengatur Muda', 'II/c', 'Pengadministrasi Surat', 'Sekretariat');

-- Akun login contoh. GANTI password_hash di bawah sebelum dipakai:
-- jalankan di server -> php -r "echo password_hash('KATA_SANDI_BARU', PASSWORD_DEFAULT), PHP_EOL;"
-- lalu UPDATE user_login SET password_hash = '<hasil>' WHERE username = 'admin.kepegawaian';
INSERT INTO user_login (username, password_hash, nama_tampilan) VALUES
('admin.kepegawaian', '$2y$10$GANTI.DENGAN.HASH.ASLI.SEBELUM.DIPAKAI...............', 'Dian Puspitasari');
