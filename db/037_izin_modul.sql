-- Modul pengajuan izin keluar sementara / pulang awal (rancangan:
-- web-parantau/docs/superpowers/specs/2026-11-16-aura-pengajuan-izin-design.md,
-- rencana: docs/rencana-izin-v1.md).
--
-- Aditif saja: tidak mengubah/menghapus tabel surat lama. Modul mulai dalam
-- mode 'nonaktif' - jalur surat izin lama tetap jalan sampai admin
-- mengaktifkan modul.
--
-- Waktu kejadian (*_at) disimpan UTC, diisi dari PHP (gmdate), bukan NOW()
-- server. Tanggal/jam izin (tanggal, jam_mulai, jam_kembali) adalah waktu
-- lokal kantor menurut izin_pengaturan.zona_waktu.
--
-- Idempotent (CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).

-- Nomor WA pegawai, diisi cron/sync_pegawai_dari_restu.php dari restu.pegawai.no_telp.
ALTER TABLE pegawai ADD COLUMN IF NOT EXISTS no_telp VARCHAR(20) NULL AFTER unit_kerja;

CREATE TABLE IF NOT EXISTS izin_pengaturan (
  id                TINYINT UNSIGNED NOT NULL,
  mode              ENUM('nonaktif','aktif','pemeliharaan') NOT NULL DEFAULT 'nonaktif',
  zona_waktu        VARCHAR(40)  NOT NULL DEFAULT 'Asia/Makassar',
  -- JSON {"1":{"mulai":"08:00","selesai":"16:30"}, ...} kunci = ISO hari (1=Senin..7=Minggu); hari tanpa kunci = libur
  jadwal_kerja      TEXT         NOT NULL,
  izin_lisan_aktif  TINYINT(1)   NOT NULL DEFAULT 0,
  wa_aktif          TINYINT(1)   NOT NULL DEFAULT 0,
  wa_token          VARCHAR(255) NULL,
  token_portal      VARCHAR(100) NULL,
  updated_at        DATETIME     NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jam kerja resmi (dikonfirmasi pengguna 2026-11-16): Senin-Kamis 08.00-16.30, Jumat 08.00-17.00 WITA.
INSERT INTO izin_pengaturan (id, jadwal_kerja)
SELECT 1, '{"1":{"mulai":"08:00","selesai":"16:30"},"2":{"mulai":"08:00","selesai":"16:30"},"3":{"mulai":"08:00","selesai":"16:30"},"4":{"mulai":"08:00","selesai":"16:30"},"5":{"mulai":"08:00","selesai":"17:00"}}'
WHERE NOT EXISTS (SELECT 1 FROM izin_pengaturan WHERE id = 1);

CREATE TABLE IF NOT EXISTS izin_hari_libur (
  tanggal     DATE         NOT NULL,
  keterangan  VARCHAR(150) NOT NULL,
  PRIMARY KEY (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS izin_pemetaan_pegawai (
  pegawai_id  INT UNSIGNED NOT NULL,
  kelompok    ENUM('ketua','wakil','hakim','panitera','sekretaris','kepaniteraan','kesekretariatan') NOT NULL,
  updated_by  INT UNSIGNED NULL,
  updated_at  DATETIME     NULL,
  PRIMARY KEY (pegawai_id),
  CONSTRAINT fk_izin_pemetaan_pegawai FOREIGN KEY (pegawai_id) REFERENCES pegawai (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS izin_pejabat (
  kode        ENUM('ketua','wakil','panitera','sekretaris') NOT NULL,
  pegawai_id  INT UNSIGNED NOT NULL,
  updated_by  INT UNSIGNED NULL,
  updated_at  DATETIME     NULL,
  PRIMARY KEY (kode),
  CONSTRAINT fk_izin_pejabat_pegawai FOREIGN KEY (pegawai_id) REFERENCES pegawai (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS izin_berhalangan (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pegawai_id      INT UNSIGNED NOT NULL,
  mulai           DATETIME     NOT NULL COMMENT 'UTC',
  selesai         DATETIME     NOT NULL COMMENT 'UTC',
  jenis           ENUM('cuti','dinas_luar','lainnya') NOT NULL,
  catatan         VARCHAR(255) NULL,
  dibuat_oleh     INT UNSIGNED NOT NULL,
  dibuat_at       DATETIME     NOT NULL,
  dibatalkan_oleh INT UNSIGNED NULL,
  dibatalkan_at   DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_izin_berhalangan_pegawai (pegawai_id, mulai, selesai),
  CONSTRAINT fk_izin_berhalangan_pegawai FOREIGN KEY (pegawai_id) REFERENCES pegawai (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS izin_permohonan (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pemohon_user_id       INT UNSIGNED NOT NULL,
  pemohon_pegawai_id    INT UNSIGNED NOT NULL,
  pemohon_nama          VARCHAR(200) NOT NULL,
  pemohon_nip           VARCHAR(18)  NOT NULL,
  pemohon_jabatan       VARCHAR(150) NULL,
  pemohon_unit_kerja    VARCHAR(150) NULL,
  kelompok              VARCHAR(20)  NOT NULL,
  zona_waktu            VARCHAR(40)  NOT NULL,
  jenis                 ENUM('keluar_sementara','pulang_awal') NOT NULL,
  tanggal               DATE         NOT NULL COMMENT 'lokal kantor',
  jam_mulai             TIME         NOT NULL COMMENT 'lokal kantor',
  jam_kembali           TIME         NULL     COMMENT 'rencana, hanya keluar_sementara',
  jam_akhir             TIME         NOT NULL COMMENT 'akhir interval utk cek bentrok: jam_kembali atau akhir jam kerja',
  alasan                TEXT         NOT NULL,
  status                ENUM('draf','menunggu_persetujuan','diajukan_ke_pta','disetujui','ditolak','dibatalkan','dicabut') NOT NULL DEFAULT 'draf',
  versi                 INT UNSIGNED NOT NULL DEFAULT 1,
  asal_permohonan_id    INT UNSIGNED NULL,
  mengetahui_user_id    INT UNSIGNED NULL,
  mengetahui_nama       VARCHAR(200) NULL,
  mengetahui_jabatan    VARCHAR(150) NULL,
  pemberi_eksternal     VARCHAR(200) NULL COMMENT 'izin Ketua: teks pejabat PTA',
  -- Izin darurat/lisan: hanya dipakai bila izin_pengaturan.izin_lisan_aktif = 1.
  lisan                 TINYINT(1)   NOT NULL DEFAULT 0,
  waktu_kejadian        DATETIME     NULL COMMENT 'UTC',
  dicatat_oleh          INT UNSIGNED NULL,
  catatan_keputusan     TEXT         NULL,
  dibuat_at             DATETIME     NOT NULL,
  diajukan_at           DATETIME     NULL,
  diputus_at            DATETIME     NULL,
  diputus_oleh          INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_izin_permohonan_pemohon (pemohon_pegawai_id, tanggal),
  KEY idx_izin_permohonan_status (status, tanggal),
  CONSTRAINT fk_izin_permohonan_pegawai FOREIGN KEY (pemohon_pegawai_id) REFERENCES pegawai (id),
  CONSTRAINT fk_izin_permohonan_asal FOREIGN KEY (asal_permohonan_id) REFERENCES izin_permohonan (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS izin_tahap (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  permohonan_id   INT UNSIGNED NOT NULL,
  pemberi_user_id INT UNSIGNED NOT NULL,
  pemberi_pegawai_id INT UNSIGNED NOT NULL,
  pemberi_nama    VARCHAR(200) NOT NULL,
  pemberi_jabatan VARCHAR(150) NULL,
  status          ENUM('menunggu','selesai','ditolak','diganti','dibatalkan') NOT NULL DEFAULT 'menunggu',
  catatan         TEXT         NULL,
  diganti_dari    INT UNSIGNED NULL,
  dibuat_at       DATETIME     NOT NULL,
  diputus_at      DATETIME     NULL,
  -- NULL kecuali status 'menunggu' -> unique menjamin 1 tugas aktif per permohonan.
  aktif_kunci     INT UNSIGNED AS (IF(status = 'menunggu', permohonan_id, NULL)) PERSISTENT,
  PRIMARY KEY (id),
  UNIQUE KEY uq_izin_tahap_aktif (aktif_kunci),
  KEY idx_izin_tahap_pemberi (pemberi_user_id, status),
  CONSTRAINT fk_izin_tahap_permohonan FOREIGN KEY (permohonan_id) REFERENCES izin_permohonan (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS izin_audit (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  permohonan_id   INT UNSIGNED NULL,
  aktor_user_id   INT UNSIGNED NULL,
  aktor_nama      VARCHAR(200) NULL,
  aksi            VARCHAR(50)  NOT NULL,
  status_sebelum  VARCHAR(30)  NULL,
  status_sesudah  VARCHAR(30)  NULL,
  data            TEXT         NULL COMMENT 'JSON perubahan relevan',
  alasan          TEXT         NULL,
  dibuat_at       DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_izin_audit_permohonan (permohonan_id, dibuat_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS izin_notifikasi (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  penerima_user_id INT UNSIGNED NOT NULL,
  permohonan_id   INT UNSIGNED NOT NULL,
  peristiwa       VARCHAR(40)  NOT NULL,
  pesan           VARCHAR(255) NOT NULL,
  dibuat_at       DATETIME     NOT NULL,
  dibaca_at       DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_izin_notifikasi (permohonan_id, peristiwa, penerima_user_id),
  KEY idx_izin_notifikasi_penerima (penerima_user_id, dibaca_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS izin_wa_outbox (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  penerima_user_id INT UNSIGNED NOT NULL,
  permohonan_id   INT UNSIGNED NOT NULL,
  peristiwa       VARCHAR(40)  NOT NULL,
  tahap_id        INT UNSIGNED NULL COMMENT 'diisi utk pesan tugas; dibatalkan bila tahap tidak lagi menunggu',
  nomor           VARCHAR(20)  NULL,
  pesan           TEXT         NOT NULL,
  status          ENUM('antre','terkirim','gagal','dibatalkan') NOT NULL DEFAULT 'antre',
  percobaan       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  coba_lagi_at    DATETIME     NOT NULL,
  error           VARCHAR(255) NULL,
  dibuat_at       DATETIME     NOT NULL,
  terkirim_at     DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_izin_wa (permohonan_id, peristiwa, penerima_user_id),
  KEY idx_izin_wa_antre (status, coba_lagi_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS izin_dokumen (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  permohonan_id   INT UNSIGNED NOT NULL,
  tahun           SMALLINT UNSIGNED NOT NULL,
  urutan          INT UNSIGNED NOT NULL,
  nomor           VARCHAR(30)  NOT NULL COMMENT 'IZN/{tahun}/{urutan} - nomor register internal, bukan nomor surat resmi',
  status          ENUM('diproses','siap','gagal') NOT NULL DEFAULT 'diproses',
  template_berkas  VARCHAR(100) NOT NULL COMMENT 'templates/izin_modul_*.docx yang dipakai',
  template_sha256 CHAR(64)     NOT NULL COMMENT 'salinan beku di arsip/template/{sha}.docx',
  snapshot_nilai  MEDIUMTEXT   NOT NULL COMMENT 'JSON nilai render, dibekukan saat final',
  path_pdf        VARCHAR(255) NULL COMMENT 'relatif thd direktori arsip izin (config direktori_arsip_izin)',
  sha256          CHAR(64)     NULL,
  error           VARCHAR(255) NULL,
  dibuat_at       DATETIME     NOT NULL,
  siap_at         DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_izin_dokumen_permohonan (permohonan_id),
  UNIQUE KEY uq_izin_dokumen_nomor (tahun, urutan),
  CONSTRAINT fk_izin_dokumen_permohonan FOREIGN KEY (permohonan_id) REFERENCES izin_permohonan (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
