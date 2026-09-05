-- Variabel nomor-otomatis, DIBAGI berdasarkan field tanggal yang dipakai
-- tiap jenis surat (bukan 1 per jenis surat - 'nomor_urut' sendiri gak
-- peduli jenis surat apa, sama kayak 'nomor_surat'/'tanggal_surat' yang
-- emang sudah dipakai bareng banyak jenis surat sejak awal):
--
-- - nomor_urut: SATU variabel manual, dipakai SEMUA jenis surat yang mau
--   nomor otomatis (nomor_sk lama & nomor_surat lama TETAP ada di katalog,
--   dilepas dari template yang pindah ke nomor otomatis - masih dipakai
--   jenis surat lain yang belum/gak mau pindah).
-- - nomor_lengkap: turunan (nomor_urut + tanggal_surat + kode_klasifikasi_
--   surat) - buat pernyataan_melaksanakan_tugas/undangan/surat_tugas/
--   surat_perintah_plh (4 jenis surat ini semua pakai tanggal_surat).
-- - nomor_lengkap_sk: sama tapi pakai tanggal_penetapan - SK (3 sub_jenis).
-- - nomor_lengkap_bas: sama tapi pakai pembukaan_tanggal - Berita Acara
--   Sumpah (satu-satunya yang field tanggalnya beda sendiri).
--
-- Pemasangan ke template_surat_variabel (lepas nomor_sk/nomor_surat lama,
-- pasang nomor_urut+nomor_lengkap* baru) + edit macro docx dikerjakan
-- migrasi/pasang_nomor_otomatis.php, bukan di sini (butuh PHP, bukan SQL
-- doang).

USE aurat;

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default)
SELECT 'nomor_urut', 'Nomor Urut Surat (angka saja, mis. 123)', 'text', 'manual', 1
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'nomor_urut');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca, parameter_variabel)
SELECT 'nomor_lengkap', 'Nomor Surat Lengkap (otomatis)', 'text', 'turunan', 'nomor_surat_otomatis',
       '["nomor_urut","tanggal_surat","kode_klasifikasi_surat"]'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'nomor_lengkap');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca, parameter_variabel)
SELECT 'nomor_lengkap_sk', 'Nomor SK Lengkap (otomatis)', 'text', 'turunan', 'nomor_surat_otomatis',
       '["nomor_urut","tanggal_penetapan","kode_klasifikasi_surat"]'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'nomor_lengkap_sk');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca, parameter_variabel)
SELECT 'nomor_lengkap_bas', 'Nomor Surat Lengkap (otomatis)', 'text', 'turunan', 'nomor_surat_otomatis',
       '["nomor_urut","pembukaan_tanggal","kode_klasifikasi_surat"]'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'nomor_lengkap_bas');
