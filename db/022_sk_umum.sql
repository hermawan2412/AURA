-- Sub_jenis baru "SK Umum" (biaya proses, ketentuan internal kantor lain
-- yang gak melibatkan tim/panitia - lihat sk/tim_kerja & sk/panitia yang
-- sudah ada) - diminta user 2026-09-05. Cuma nyiapin sub_jenis_surat-nya;
-- TIDAK ada blok_tabel_surat baru (memang sengaja gak ada lampiran daftar
-- pegawai sama sekali utk varian ini, beda dari tim_kerja/panitia).
--
-- Field (nomor_sk, tanggal_penetapan, tentang, menimbang, mengingat,
-- diktum, penetap_nama_lengkap, penetap_nip) semuanya REUSE variabel_surat
-- yang sudah ada (identik body-nya dgn tim_kerja/panitia) - dipasang ke
-- template_surat lewat migrasi/tambah_sk_umum.php (upload docx + attach
-- variabel), bukan di sini.
--
-- Ditanya dulu ke user (AskUserQuestion): sk/tim_kerja vs sk/panitia
-- TIDAK digabung - kolom lampirannya beneran beda struktur (tim_kerja
-- punya kolom Jabatan, panitia gak), user pilih tetap dipisah.

USE aurat;

SET @js_sk = (SELECT id FROM jenis_surat WHERE kode = 'sk');

INSERT INTO sub_jenis_surat (jenis_surat_id, kode, label, urutan_tampil)
SELECT @js_sk, 'umum', 'Umum (Biaya Proses / Ketentuan Internal)', 30
WHERE NOT EXISTS (SELECT 1 FROM sub_jenis_surat WHERE jenis_surat_id = @js_sk AND kode = 'umum');
