-- Tambahan: kolom `tipe` pada blok_tabel_surat_kolom, dipakai kalau sumber='manual_per_baris'
-- supaya admin bisa pilih kolom itu jadi input tanggal (bukan cuma teks bebas yang ambigu).
-- Juga membenahi variabel bersama `tanggal_surat` (dipakai 5 jenis surat: cuti, undangan,
-- pelaksana_harian, pernyataan_melaksanakan_tugas, surat_tugas) yang selama ini bersumber
-- 'sistem' (otomatis tanggal hari ini, tanpa input) -- diubah jadi input manual bertipe tanggal,
-- sama seperti variabel tanggal lain di aplikasi (tanggal_mulai, tanggal_acara, dst).
--
-- Jalankan SETELAH db/002_generic_surat_engine.sql s.d. db/005_peran_admin_user_login.sql:
--   mysql -u <user> -p aurat < db/006_tipe_kolom_manual_dan_tanggal_surat.sql

USE aurat;

ALTER TABLE blok_tabel_surat_kolom
  ADD COLUMN tipe VARCHAR(10) NOT NULL DEFAULT 'text' AFTER sumber;
-- Nilai dipakai: 'text' (bawaan) atau 'date'. Diabaikan kecuali sumber='manual_per_baris'.

-- Kolom "Tanggal" pada tabel "Pegawai yang Ditugaskan" (Surat Tugas) -- sebelumnya teks bebas,
-- sekarang jadi date picker + label diperjelas jadi "Tanggal Tugas". Aman dijalankan meski
-- data jenis surat belum ada (tidak mengubah baris apa pun kalau tidak ketemu).
UPDATE blok_tabel_surat_kolom k
  JOIN blok_tabel_surat b ON b.id = k.blok_tabel_surat_id
  JOIN jenis_surat j ON j.id = b.jenis_surat_id
  SET k.tipe = 'date', k.label = 'Tanggal Tugas'
  WHERE j.kode = 'surat_tugas' AND k.kode = 'tanggal';

-- tanggal_surat: sistem (otomatis hari ini) -> manual + date picker, wajib diisi.
UPDATE variabel_surat
  SET sumber = 'manual', tipe_input = 'date', wajib_default = 1
  WHERE kode = 'tanggal_surat';
