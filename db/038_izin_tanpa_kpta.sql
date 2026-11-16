-- Ruang lingkup modul izin hanya lingkungan kantor (keputusan pengguna 2026-11-16):
-- alur izin Ketua ke KPTA dihapus, beserta status 'diajukan_ke_pta' dan kolom pemberi_eksternal.
--
-- Aman diulang. MODIFY dijalankan lebih dulu: bila masih ada baris berstatus
-- 'diajukan_ke_pta', MariaDB (mode strict) menolak dan skrip berhenti sebelum
-- kolom apa pun dibuang.

ALTER TABLE izin_permohonan
  MODIFY status ENUM('draf','menunggu_persetujuan','disetujui','ditolak','dibatalkan','dicabut') NOT NULL DEFAULT 'draf';

ALTER TABLE izin_permohonan DROP COLUMN IF EXISTS pemberi_eksternal;
