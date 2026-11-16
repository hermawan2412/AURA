-- Data awal modul izin untuk PA Rantau (dikonfirmasi pengguna 2026-11-16).
-- BUKAN migrasi struktur: jalankan SEKALI setelah db/037, lalu periksa di Admin > Pengaturan Izin.
--   mysql aura < db/data_izin_pemetaan_parantau.sql
-- Setiap baris dicocokkan ke id DAN jabatan produksi saat itu; bila jabatan sudah berubah, baris
-- dilewati (tidak salah orang) - cek hitungan di akhir: harus 32 pemetaan + 4 pejabat.
-- Panitera Pengganti PTA (10, 36, 37) belum punya akun RESTU: buat akunnya di Kelola Pengguna
-- dengan memilih pegawainya, baru bisa mengajukan izin.

SET @kini = UTC_TIMESTAMP();

INSERT INTO izin_pejabat (kode, pegawai_id, updated_at) SELECT 'ketua', id, @kini FROM pegawai WHERE id = 11 AND jabatan = 'KETUA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE pegawai_id = VALUES(pegawai_id), updated_at = VALUES(updated_at);
INSERT INTO izin_pejabat (kode, pegawai_id, updated_at) SELECT 'wakil', id, @kini FROM pegawai WHERE id = 7 AND jabatan = 'WAKIL KETUA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE pegawai_id = VALUES(pegawai_id), updated_at = VALUES(updated_at);
INSERT INTO izin_pejabat (kode, pegawai_id, updated_at) SELECT 'panitera', id, @kini FROM pegawai WHERE id = 9 AND jabatan = 'PANITERA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE pegawai_id = VALUES(pegawai_id), updated_at = VALUES(updated_at);
INSERT INTO izin_pejabat (kode, pegawai_id, updated_at) SELECT 'sekretaris', id, @kini FROM pegawai WHERE id = 19 AND jabatan = 'SEKRETARIS' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE pegawai_id = VALUES(pegawai_id), updated_at = VALUES(updated_at);

INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'ketua', @kini FROM pegawai WHERE id = 11 AND jabatan = 'KETUA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'wakil', @kini FROM pegawai WHERE id = 7 AND jabatan = 'WAKIL KETUA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'panitera', @kini FROM pegawai WHERE id = 9 AND jabatan = 'PANITERA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'sekretaris', @kini FROM pegawai WHERE id = 19 AND jabatan = 'SEKRETARIS' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'hakim', @kini FROM pegawai WHERE id = 15 AND jabatan = 'HAKIM' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'hakim', @kini FROM pegawai WHERE id = 20 AND jabatan = 'HAKIM' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'hakim', @kini FROM pegawai WHERE id = 21 AND jabatan = 'HAKIM' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'hakim', @kini FROM pegawai WHERE id = 27 AND jabatan = 'HAKIM' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'hakim', @kini FROM pegawai WHERE id = 30 AND jabatan = 'HAKIM' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'hakim', @kini FROM pegawai WHERE id = 35 AND jabatan = 'HAKIM' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 8 AND jabatan = 'PANITERA MUDA GUGATAN' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 6 AND jabatan = 'PANITERA MUDA HUKUM' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 14 AND jabatan = 'JURUSITA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 16 AND jabatan = 'PENGELOLA PENANGANAN PERKARA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 31 AND jabatan = 'ANALIS PERKARA PERADILAN' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 34 AND jabatan = 'ANALIS PERKARA PERADILAN' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 17 AND jabatan = 'DOKUMENTALIS HUKUM' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 25 AND jabatan = 'DOKUMENTALIS HUKUM' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 32 AND jabatan = 'DOKUMENTALIS HUKUM' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 10 AND jabatan = 'Panitera Penganti PTA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 36 AND jabatan = 'Panitera Pengganti PTA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kepaniteraan', @kini FROM pegawai WHERE id = 37 AND jabatan = 'Panitera Pengganti PTA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kesekretariatan', @kini FROM pegawai WHERE id = 12 AND jabatan = 'KASUBAG PERENCANAAN, TI DAN PELAPORAN' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kesekretariatan', @kini FROM pegawai WHERE id = 33 AND jabatan = 'KASUBAG UMUM DAN KEUANGAN' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kesekretariatan', @kini FROM pegawai WHERE id = 18 AND jabatan = 'PRANATA KOMPUTER' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kesekretariatan', @kini FROM pegawai WHERE id = 26 AND jabatan = 'TEKNISI SARANA DAN PRASARANA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kesekretariatan', @kini FROM pegawai WHERE id = 24 AND jabatan = 'OPERATOR - TEKNISI SARANA DAN PRASARANA' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kesekretariatan', @kini FROM pegawai WHERE id = 22 AND jabatan = 'OPERATOR LAYANAN OPERASIONAL' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kesekretariatan', @kini FROM pegawai WHERE id = 28 AND jabatan = 'OPERATOR LAYANAN OPERASIONAL' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kesekretariatan', @kini FROM pegawai WHERE id = 29 AND jabatan = 'OPERATOR LAYANAN OPERASIONAL' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kesekretariatan', @kini FROM pegawai WHERE id = 23 AND jabatan = 'PENATA LAYANAN OPERASIONAL' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);
INSERT INTO izin_pemetaan_pegawai (pegawai_id, kelompok, updated_at) SELECT id, 'kesekretariatan', @kini FROM pegawai WHERE id = 13 AND jabatan = 'PENATA LAYANAN OPERASIONAL' AND status_aktif = 1
  ON DUPLICATE KEY UPDATE kelompok = VALUES(kelompok), updated_at = VALUES(updated_at);

SELECT (SELECT COUNT(*) FROM izin_pejabat) AS pejabat_harus_4, (SELECT COUNT(*) FROM izin_pemetaan_pegawai) AS pemetaan_harus_32;
