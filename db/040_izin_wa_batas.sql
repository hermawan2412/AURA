-- Batas harian WhatsApp + kuota bulanan Fonnte untuk modul izin (2026-11-17).
-- prosesAntrean() menahan antrean bila pesan terkirim hari ini (zona kantor) sudah
-- mencapai wa_batas_harian; halaman pengaturan izin memberi peringatan pada 80% kuota.
ALTER TABLE izin_pengaturan
  ADD COLUMN IF NOT EXISTS wa_batas_harian INT UNSIGNED NOT NULL DEFAULT 100 AFTER wa_token,
  ADD COLUMN IF NOT EXISTS wa_kuota_bulanan INT UNSIGNED NOT NULL DEFAULT 1000 AFTER wa_batas_harian;
