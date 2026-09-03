-- Tambahan: ikon unik per jenis surat, dipakai di kartu dasbor (index.php).
-- Ikon dipilih dari set SVG bawaan di Aurat\Surat\IconLibrary (kode aplikasi),
-- kolom ini hanya menyimpan SLUG-nya (bukan markup SVG) supaya tetap konsisten
-- gayanya dan tidak butuh sanitasi HTML/SVG dari input admin.
--
-- Jalankan SETELAH db/002_generic_surat_engine.sql dan db/003_blok_tabel_fungsi_pasca.sql:
--   mysql -u <user> -p aurat < db/004_ikon_jenis_surat.sql

USE aurat;

ALTER TABLE jenis_surat
  ADD COLUMN icon VARCHAR(30) NOT NULL DEFAULT 'dokumen' AFTER kategori;
-- Slug tak dikenal (mis. sisa migrasi lama, atau salah ketik manual di DB) otomatis
-- jatuh balik ke ikon 'dokumen' lewat IconLibrary::render(), bukan galat.

-- Isi ikon yang masuk akal utk 7 jenis surat bawaan (lihat migrasi/import_jenis_surat.php).
UPDATE jenis_surat SET icon = 'kalender'      WHERE kode = 'cuti';
UPDATE jenis_surat SET icon = 'centang_orang' WHERE kode = 'pelaksana_harian';
UPDATE jenis_surat SET icon = 'papan_clip'    WHERE kode = 'pernyataan_melaksanakan_tugas';
UPDATE jenis_surat SET icon = 'perisai'       WHERE kode = 'berita_acara_sumpah';
UPDATE jenis_surat SET icon = 'amplop'        WHERE kode = 'undangan';
UPDATE jenis_surat SET icon = 'tas_kerja'     WHERE kode = 'surat_tugas';
UPDATE jenis_surat SET icon = 'medali'        WHERE kode = 'sk';
