-- Blok tabel "Lampiran Daftar Pegawai" (default, dipakai tim_kerja) ternyata
-- otomatis ke-warisi ke SEMUA sub_jenis "sk" termasuk yang baru (umum) -
-- ketauan pas tes generate beneran: form nolak "Tabel Lampiran Daftar
-- Pegawai minimal harus berisi 1 baris" padahal sk/umum sengaja gak punya
-- lampiran sama sekali. Mekanisme override_per_sub_jenis yang sudah ada
-- (BlokTabelRepository::blokUntuk()) cuma bisa GANTI kolom blok default,
-- gak bisa BILANG "utk sub_jenis ini, blok default-nya gak berlaku" - jadi
-- ditambah kolom `nonaktif` (lihat perubahan di
-- src/Surat/BlokTabelRepository.php, komit sama) buat itu.

USE aurat;

ALTER TABLE blok_tabel_surat ADD COLUMN IF NOT EXISTS nonaktif TINYINT(1) NOT NULL DEFAULT 0 AFTER minimal_baris;

SET @js_sk = (SELECT id FROM jenis_surat WHERE kode = 'sk');
SET @sub_umum = (SELECT id FROM sub_jenis_surat WHERE jenis_surat_id = @js_sk AND kode = 'umum');

INSERT INTO blok_tabel_surat (jenis_surat_id, sub_jenis_surat_id, kode, nama_anchor_kolom, label, minimal_baris, nonaktif, urutan_tampil)
SELECT @js_sk, @sub_umum, 'no', 'no', '(dinonaktifkan utk sub_jenis ini)', 0, 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM blok_tabel_surat WHERE jenis_surat_id = @js_sk AND sub_jenis_surat_id = @sub_umum AND kode = 'no'
);
