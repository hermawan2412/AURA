-- Gambar tanda tangan pegawai untuk surat izin (2026-11-17). Disalin dari RESTU oleh
-- cron/sync_pegawai_dari_restu.php ke <direktori_arsip_izin>/ttd/<sha256>.<ext>; berkas
-- tidak pernah ditimpa/dihapus sehingga surat lama yang merujuknya tetap identik.
ALTER TABLE pegawai ADD COLUMN IF NOT EXISTS ttd_berkas VARCHAR(80) NULL AFTER no_telp;
