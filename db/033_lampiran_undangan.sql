-- Lampiran Daftar Hadir buat Undangan (docs/ide-jenis-surat-undangan.md item 1+4).
-- Daftar Hadir digenerate BARENG Undangan dari 1 submit form, bukan jenis_surat
-- terpisah - makanya template_surat dikasih dimensi baru "tipe_dokumen" (utama/
-- lampiran), bukan bikin jenis_surat baru. Peserta diinput lewat blok_tabel_surat
-- baru, terpisah dari field "Kepada Yth." (variabel `tujuan`) yang sudah ada.
--
-- Notula (record terpisah, susulan) BELUM di sini - lihat docs/ide-jenis-surat-
-- undangan.md, itu tahap 2, nunggu build terpisah.

USE aurat;

-- 1 template_surat sekarang bisa jadi dokumen utama ATAU lampiran dari jenis
-- surat yang sama - keduanya diversion/diupload lewat alur admin yang sama
-- (template_surat.php), cuma discope terpisah (1 versi aktif per
-- jenis_surat+sub_jenis+tipe_dokumen, bukan cuma jenis_surat+sub_jenis kayak
-- sebelumnya - lihat TemplateSuratRepository).
ALTER TABLE template_surat
  ADD COLUMN IF NOT EXISTS tipe_dokumen ENUM('utama','lampiran') NOT NULL DEFAULT 'utama' AFTER sub_jenis_surat_id;

-- Link opsional: baris lampiran (mis. daftar hadir) menunjuk baris utama dari
-- submission yang sama, biar histori surat_diterbitkan bisa nampilin mereka
-- sebagai 1 paket, bukan 2 entry lepas yang gak nyambung.
ALTER TABLE surat_diterbitkan
  ADD COLUMN IF NOT EXISTS induk_id INT UNSIGNED NULL AFTER template_surat_id;

SET @fk_induk_ada = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'surat_diterbitkan' AND CONSTRAINT_NAME = 'fk_surat_diterbitkan_induk'
);
SET @sql_fk_induk = IF(@fk_induk_ada = 0,
  'ALTER TABLE surat_diterbitkan ADD CONSTRAINT fk_surat_diterbitkan_induk FOREIGN KEY (induk_id) REFERENCES surat_diterbitkan(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql_fk_induk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Blok tabel peserta / daftar hadir - baru, scoped ke jenis_surat 'undangan'
-- (sub_jenis_surat_id NULL, undangan single_dokumen gak punya sub_jenis).
-- nama_anchor_kolom='no' cocok sama kode kolom pertama (pola sama kayak blok
-- tabel Surat Tugas/SK yang udah ada).
INSERT INTO blok_tabel_surat (jenis_surat_id, sub_jenis_surat_id, kode, nama_anchor_kolom, label, minimal_baris, urutan_tampil)
SELECT js.id, NULL, 'peserta', 'no', 'Peserta / Daftar Hadir', 1, 10
FROM jenis_surat js WHERE js.kode = 'undangan'
  AND NOT EXISTS (
    SELECT 1 FROM blok_tabel_surat b WHERE b.jenis_surat_id = js.id AND b.sub_jenis_surat_id IS NULL AND b.kode = 'peserta'
  );

INSERT INTO blok_tabel_surat_kolom (blok_tabel_surat_id, kode, label, sumber, tipe, field_pegawai, fungsi_pasca, urutan_kolom)
SELECT b.id, 'no', 'No', 'auto_nomor', 'text', NULL, NULL, 10
FROM blok_tabel_surat b JOIN jenis_surat js ON js.id = b.jenis_surat_id
WHERE js.kode = 'undangan' AND b.sub_jenis_surat_id IS NULL AND b.kode = 'peserta'
  AND NOT EXISTS (SELECT 1 FROM blok_tabel_surat_kolom k WHERE k.blok_tabel_surat_id = b.id AND k.kode = 'no');

INSERT INTO blok_tabel_surat_kolom (blok_tabel_surat_id, kode, label, sumber, tipe, field_pegawai, fungsi_pasca, urutan_kolom)
SELECT b.id, 'nama', 'Nama', 'pegawai_fungsi', 'text', NULL, 'nama_bergelar', 20
FROM blok_tabel_surat b JOIN jenis_surat js ON js.id = b.jenis_surat_id
WHERE js.kode = 'undangan' AND b.sub_jenis_surat_id IS NULL AND b.kode = 'peserta'
  AND NOT EXISTS (SELECT 1 FROM blok_tabel_surat_kolom k WHERE k.blok_tabel_surat_id = b.id AND k.kode = 'nama');

INSERT INTO blok_tabel_surat_kolom (blok_tabel_surat_id, kode, label, sumber, tipe, field_pegawai, fungsi_pasca, urutan_kolom)
SELECT b.id, 'bagian', 'Bagian', 'pegawai_field', 'text', 'unit_kerja', NULL, 30
FROM blok_tabel_surat b JOIN jenis_surat js ON js.id = b.jenis_surat_id
WHERE js.kode = 'undangan' AND b.sub_jenis_surat_id IS NULL AND b.kode = 'peserta'
  AND NOT EXISTS (SELECT 1 FROM blok_tabel_surat_kolom k WHERE k.blok_tabel_surat_id = b.id AND k.kode = 'bagian');

INSERT INTO blok_tabel_surat_kolom (blok_tabel_surat_id, kode, label, sumber, tipe, field_pegawai, fungsi_pasca, urutan_kolom)
SELECT b.id, 'ket', 'Ket.', 'manual_per_baris', 'text', NULL, NULL, 40
FROM blok_tabel_surat b JOIN jenis_surat js ON js.id = b.jenis_surat_id
WHERE js.kode = 'undangan' AND b.sub_jenis_surat_id IS NULL AND b.kode = 'peserta'
  AND NOT EXISTS (SELECT 1 FROM blok_tabel_surat_kolom k WHERE k.blok_tabel_surat_id = b.id AND k.kode = 'ket');

-- 2 variabel baru dibutuhkan Daftar Hadir yang belum ada sama sekali di
-- katalog (sisanya - nama_acara/hari/tanggal_acara/waktu/tempat/tanggal_surat/
-- penandatangan_nama_lengkap/penandatangan_jabatan - dipakai ULANG by-kode
-- dari yang sudah terpasang di Undangan, cukup dipasangkan ke template baru
-- lewat template_surat_variabel, gak perlu row variabel_surat baru).
INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default)
SELECT 'jenis_kegiatan', 'Jenis Kegiatan (mis. Rapat, Sosialisasi, Bimtek)', 'text', 'manual', 1
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'jenis_kegiatan');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai)
SELECT 'penandatangan_nip', 'NIP (Penandatangan)', 'text', 'pegawai', 'nip'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'penandatangan_nip');
