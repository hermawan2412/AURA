-- Tambahan: dukungan kolom tabel bersumber FUNGSI (bukan cuma satu kolom pegawai mentah).
-- Ditemukan saat migrasi 'surat_tugas': kolom "Nama" di tabel lampiran butuh nama bergelar
-- (gabungan gelar_depan+nama_lengkap+gelar_belakang), bukan satu kolom `pegawai` mentah —
-- sama seperti kebutuhan fungsi_pasca di variabel_surat, tapi utk kolom tabel berulang.
--
-- Jalankan SETELAH db/002_generic_surat_engine.sql:
--   mysql -u <user> -p aurat < db/003_blok_tabel_fungsi_pasca.sql

USE aurat;

ALTER TABLE blok_tabel_surat_kolom
  ADD COLUMN fungsi_pasca VARCHAR(50) NULL AFTER field_pegawai;
-- Dipakai kalau sumber='pegawai_fungsi': kunci whitelist fungsi di NilaiResolver::$FUNGSI
-- (sama dgn whitelist fungsi_pasca variabel_surat), dipanggil dgn SATU argumen: baris
-- pegawai penuh utk row itu. Contoh: fungsi_pasca='nama_bergelar' utk kolom "Nama".
