-- Ledger surat diterbitkan - diminta user 2026-09-05 (ide awal: link
-- dokumentasi SK + tracking kadaluwarsa; diperluas ke SEMUA jenis surat
-- setelah dikonfirmasi via AskUserQuestion, sekalian jadi basis fitur
-- "isi ulang dari surat sebelumnya" yang juga ditanyakan user).
--
-- AURA sebelumnya STATELESS - surat/index.php generate docx langsung
-- stream ke browser, gak pernah nyimpen histori isian form ke mana pun.
-- Tabel ini nyimpen SATU baris tiap kali dokumen sukses digenerate
-- (nyantol di auratProsesGenerate(), lihat surat/index.php).
--
-- nomor/tanggal_dokumen/ringkasan didenormalisasi dari nilai_lengkap (JSON,
-- snapshot LENGKAP semua variabel yg resolve) berdasarkan 3 kolom baru di
-- jenis_surat (variabel_nomor_kode dst) yang ADMIN pilih manual per jenis
-- surat (dikonfirmasi user - bukan tebak otomatis dari nama field, krn tiap
-- jenis surat beda kode variabelnya: SK pakai nomor_sk, Undangan pakai
-- nomor_surat). Kalau belum di-set admin, kolom denormalisasi ini NULL -
-- baris tetap kerekam (nilai_lengkap tetap lengkap), cuma gak muncul di
-- kolom nomor/tanggal/ringkasan daftar sampai admin nyetel pointernya.
--
-- berlaku_sampai & link_dokumentasi: MANUAL, admin isi belakangan lewat
-- admin/surat_diterbitkan.php (bukan bagian generate) - dasar utk badge
-- status Aktif/Segera-Kedaluwarsa/Kedaluwarsa & link ke NAS/dokumentasi.

USE aurat;

ALTER TABLE jenis_surat
  ADD COLUMN IF NOT EXISTS variabel_nomor_kode VARCHAR(100) NULL AFTER pola_nama_unduhan,
  ADD COLUMN IF NOT EXISTS variabel_tanggal_kode VARCHAR(100) NULL AFTER variabel_nomor_kode,
  ADD COLUMN IF NOT EXISTS variabel_ringkasan_kode VARCHAR(100) NULL AFTER variabel_tanggal_kode;

CREATE TABLE IF NOT EXISTS surat_diterbitkan (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jenis_surat_id      INT UNSIGNED NOT NULL,
  sub_jenis_surat_id  INT UNSIGNED NULL,
  template_surat_id   INT UNSIGNED NOT NULL,
  nomor               VARCHAR(150) NULL,
  tanggal_dokumen     DATE NULL,
  ringkasan           VARCHAR(255) NULL,
  nilai_lengkap       LONGTEXT NOT NULL,
  berlaku_sampai      DATE NULL,
  link_dokumentasi    VARCHAR(500) NULL,
  dibuat_oleh         INT UNSIGNED NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_surat_diterbitkan_jenis (jenis_surat_id, sub_jenis_surat_id),
  KEY idx_surat_diterbitkan_berlaku (berlaku_sampai),
  CONSTRAINT fk_surat_diterbitkan_jenis FOREIGN KEY (jenis_surat_id) REFERENCES jenis_surat (id),
  CONSTRAINT fk_surat_diterbitkan_sub FOREIGN KEY (sub_jenis_surat_id) REFERENCES sub_jenis_surat (id),
  CONSTRAINT fk_surat_diterbitkan_template FOREIGN KEY (template_surat_id) REFERENCES template_surat (id),
  CONSTRAINT fk_surat_diterbitkan_user FOREIGN KEY (dibuat_oleh) REFERENCES user_login (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
