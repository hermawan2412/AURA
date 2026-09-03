-- Struktur database — AURAT (Aplikasi Untuk Persuratan)
-- Tambahan: mesin template/variabel generic (menggantikan config/jenis_surat/*.json
-- dan file surat/*.php hardcode per jenis surat).
--
-- Cara pakai: jalankan SETELAH schema.sql / schema_tabel_saja.sql (butuh tabel
-- pegawai dan user_login sudah ada, dirujuk lewat FOREIGN KEY di bawah).
--   mysql -u <user> -p aurat < db/002_generic_surat_engine.sql
-- Ada "USE aurat" di bawah supaya aman dijalankan juga lewat tool GUI (SQLyog,
-- phpMyAdmin, dst) yang tidak selalu ikut memilihkan database dari argumen CLI.

USE aurat;

-- ============================================================
-- jenis_surat — menggantikan config/jenis_surat/{kode}.json
-- ============================================================
CREATE TABLE jenis_surat (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode              VARCHAR(50)  NOT NULL,
  nama              VARCHAR(150) NOT NULL,
  deskripsi         VARCHAR(255) NULL,
  kategori          VARCHAR(20)  NOT NULL DEFAULT 'single_dokumen', -- single_dokumen | dua_dokumen
  kop_surat         VARCHAR(30)  NOT NULL DEFAULT 'standar',        -- standar | tanpa_kop | kode custom lain
  pola_nama_unduhan VARCHAR(150) NULL,       -- mis. "SK_{nomor_sk}"; {kode_variabel} disubstitusi saat unduh
  status_aktif      TINYINT(1)   NOT NULL DEFAULT 1,
  urutan_tampil     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  dibuat_oleh       INT UNSIGNED NULL,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    NULL     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jenis_surat_kode (kode),
  CONSTRAINT fk_jenis_surat_dibuat_oleh FOREIGN KEY (dibuat_oleh) REFERENCES user_login (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sub_jenis_surat — menggantikan JSON "sub_jenis": [{kode,label}]
-- ============================================================
CREATE TABLE sub_jenis_surat (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jenis_surat_id    INT UNSIGNED NOT NULL,
  kode              VARCHAR(50)  NOT NULL,
  label             VARCHAR(150) NOT NULL,
  urutan_tampil     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sub_jenis_surat (jenis_surat_id, kode),
  CONSTRAINT fk_sub_jenis_surat_jenis FOREIGN KEY (jenis_surat_id) REFERENCES jenis_surat (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- peran_pegawai_surat — menggantikan JSON "peran_pegawai":
-- [{kode,label,sumber:"pegawai_single",wajib}]. Ini "slot" pemilih
-- pegawai tunggal yang tampil di formulir (mis. "pemohon", "penetap").
-- ============================================================
CREATE TABLE peran_pegawai_surat (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jenis_surat_id    INT UNSIGNED NOT NULL,
  kode              VARCHAR(50)  NOT NULL,
  label             VARCHAR(150) NOT NULL,
  wajib             TINYINT(1)   NOT NULL DEFAULT 1,
  urutan_tampil     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_peran_pegawai_surat (jenis_surat_id, kode),
  CONSTRAINT fk_peran_pegawai_surat_jenis FOREIGN KEY (jenis_surat_id) REFERENCES jenis_surat (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- template_surat — menggantikan JSON "template_file" (string
-- atau object per sub_jenis). Satu baris = satu berkas .docx aktif.
-- ============================================================
CREATE TABLE template_surat (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jenis_surat_id      INT UNSIGNED NOT NULL,
  sub_jenis_surat_id  INT UNSIGNED NULL,      -- NULL = single_dokumen (atau default dua_dokumen)
  nama_berkas         VARCHAR(255) NOT NULL,  -- nama file fisik di templates/, dibangkitkan sistem (bukan dari input user)
  nama_asli           VARCHAR(255) NOT NULL,  -- nama file asli saat diunggah, hanya untuk ditampilkan
  versi               INT UNSIGNED NOT NULL DEFAULT 1,
  status_aktif        TINYINT(1)   NOT NULL DEFAULT 1,
  diunggah_oleh       INT UNSIGNED NULL,
  diunggah_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_template_surat_scope (jenis_surat_id, sub_jenis_surat_id, status_aktif),
  CONSTRAINT fk_template_surat_jenis FOREIGN KEY (jenis_surat_id) REFERENCES jenis_surat (id) ON DELETE CASCADE,
  CONSTRAINT fk_template_surat_sub FOREIGN KEY (sub_jenis_surat_id) REFERENCES sub_jenis_surat (id) ON DELETE CASCADE,
  CONSTRAINT fk_template_surat_pengunggah FOREIGN KEY (diunggah_oleh) REFERENCES user_login (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- CATATAN: "hanya satu versi AKTIF per (jenis_surat_id, sub_jenis_surat_id)" tidak
-- bisa dipaksakan lewat UNIQUE KEY biasa (MariaDB menganggap tiap NULL pada
-- sub_jenis_surat_id sebagai nilai berbeda). Ditegakkan di kode aplikasi:
-- dalam SATU transaksi, sebelum INSERT versi baru berstatus aktif jalankan:
--   UPDATE template_surat SET status_aktif=0
--     WHERE jenis_surat_id=? AND sub_jenis_surat_id <=> ? AND status_aktif=1;

-- ============================================================
-- variabel_surat — katalog variabel. Disederhanakan dari
-- master_variabel (dokumen/) sesuai sumber data yang benar-benar
-- dipakai di sini: manual, pegawai, turunan (rantai sub-variabel),
-- dan sistem (mis. tanggal hari ini).
-- ============================================================
CREATE TABLE variabel_surat (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode                  VARCHAR(100) NOT NULL,  -- HARUS SAMA PERSIS dgn nama placeholder ${..} di file docx
  label                 VARCHAR(150) NOT NULL,
  tipe_input            VARCHAR(20)  NOT NULL DEFAULT 'text', -- text|textarea|date|select|textarea_datalist (jika sumber='manual')
  opsi_pilihan          TEXT         NULL,      -- JSON array string, utk tipe_input='select'/'textarea_datalist'
  sumber                VARCHAR(20)  NOT NULL DEFAULT 'manual', -- manual|pegawai|turunan|sistem
  field_pegawai         VARCHAR(50)  NULL,      -- nama kolom tabel pegawai (di-whitelist di kode PHP); jika sumber='pegawai'
  fungsi_pasca          VARCHAR(50)  NULL,      -- kunci whitelist fungsi Formatter, lihat NilaiResolver::$FUNGSI
  parameter_variabel    TEXT         NULL,      -- JSON array kode variabel_surat lain (input utk fungsi_pasca); jika sumber='turunan'
  fungsi_parameter_1    VARCHAR(255) NULL,      -- argumen literal tambahan opsional utk fungsi_pasca (mis. teks awalan klausa)
  fungsi_parameter_2    VARCHAR(255) NULL,
  sistem_kode           VARCHAR(50)  NULL,      -- mis. 'tanggal_sekarang'; jika sumber='sistem'
  wajib_default         TINYINT(1)   NOT NULL DEFAULT 0,
  placeholder_default   VARCHAR(255) NULL,      -- nilai isian jika kosong & tidak wajib
  urutan_tampil         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  dibuat_oleh           INT UNSIGNED NULL,
  created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP    NULL     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_variabel_surat_kode (kode),
  CONSTRAINT fk_variabel_surat_dibuat_oleh FOREIGN KEY (dibuat_oleh) REFERENCES user_login (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- template_surat_variabel — pivot: variabel apa saja yang
-- dipakai satu template, dan (jika sumber='pegawai') dari
-- peran_pegawai_surat mana nilainya diambil.
-- ============================================================
CREATE TABLE template_surat_variabel (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_surat_id      INT UNSIGNED NOT NULL,
  variabel_surat_id      INT UNSIGNED NOT NULL,
  peran_pegawai_surat_id INT UNSIGNED NULL,   -- wajib diisi jika variabel_surat.sumber='pegawai'; harus milik jenis_surat yg sama (dicek di aplikasi)
  wajib_override         TINYINT(1)   NULL,   -- override variabel_surat.wajib_default khusus template ini; NULL = pakai default
  urutan_tampil          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  terdeteksi_otomatis    TINYINT(1)   NOT NULL DEFAULT 0,  -- riwayat: baris ini hasil auto-scan atau input manual admin
  PRIMARY KEY (id),
  UNIQUE KEY uq_template_surat_variabel (template_surat_id, variabel_surat_id),
  CONSTRAINT fk_tsv_template FOREIGN KEY (template_surat_id) REFERENCES template_surat (id) ON DELETE CASCADE,
  CONSTRAINT fk_tsv_variabel FOREIGN KEY (variabel_surat_id) REFERENCES variabel_surat (id) ON DELETE RESTRICT,
  CONSTRAINT fk_tsv_peran FOREIGN KEY (peran_pegawai_surat_id) REFERENCES peran_pegawai_surat (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- blok_tabel_surat / blok_tabel_surat_kolom — menggantikan JSON
-- "tabel_pegawai": {kolom_default, override_per_sub_jenis}.
-- Satu baris blok_tabel_surat = satu blok tabel berulang di docx
-- (dipetakan lewat TemplateProcessor::cloneRow pakai kolom anchor).
-- ============================================================
CREATE TABLE blok_tabel_surat (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jenis_surat_id      INT UNSIGNED NOT NULL,
  sub_jenis_surat_id  INT UNSIGNED NULL,     -- NULL = berlaku default; diisi = override khusus sub_jenis itu (spt sk.json override_per_sub_jenis)
  kode                VARCHAR(50)  NOT NULL,
  nama_anchor_kolom   VARCHAR(50)  NOT NULL, -- nama placeholder kolom pertama di docx (dipakai sbg $search di cloneRow)
  label               VARCHAR(150) NULL,
  minimal_baris       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  urutan_tampil       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blok_tabel_surat (jenis_surat_id, sub_jenis_surat_id, kode),
  CONSTRAINT fk_blok_tabel_surat_jenis FOREIGN KEY (jenis_surat_id) REFERENCES jenis_surat (id) ON DELETE CASCADE,
  CONSTRAINT fk_blok_tabel_surat_sub FOREIGN KEY (sub_jenis_surat_id) REFERENCES sub_jenis_surat (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blok_tabel_surat_kolom (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  blok_tabel_surat_id INT UNSIGNED NOT NULL,
  kode                VARCHAR(50)  NOT NULL,  -- nama placeholder dasar di docx (jadi kode#N setelah cloneRow)
  label               VARCHAR(150) NOT NULL,
  sumber              VARCHAR(20)  NOT NULL,  -- auto_nomor | pegawai_field | manual_per_baris
  field_pegawai       VARCHAR(50)  NULL,      -- dipakai jika sumber='pegawai_field'
  urutan_kolom        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blok_tabel_surat_kolom (blok_tabel_surat_id, kode),
  CONSTRAINT fk_btsk_blok FOREIGN KEY (blok_tabel_surat_id) REFERENCES blok_tabel_surat (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
