-- Perjelas label field yang ambigu di form (ketemu pas audit "cek semua template,
-- masih ada isian yang gak jelas?", 2026-09-04). Cuma UPDATE teks label, gak
-- nyentuh docx/logic - variabel ini dipakai bareng di beberapa jenis_surat
-- sekaligus (nomor_surat: 6 template, hari: 2 template), efeknya otomatis
-- kepakai di semua template yang mereferensikannya.
--
-- Konvensi label "Field — Peran" (berita_acara_sumpah doang) vs "Field
-- (Peran)" (semua template lain) SENGAJA gak diseragamkan di sini - beda
-- gaya doang, gak bikin bingung isi form-nya, di luar scope audit ini.

USE aurat;

UPDATE variabel_surat SET label = 'Nomor Surat (diisi apa adanya - nomor lengkap sesuai format surat resmi institusi, termasuk kode klasifikasi kalau ada, dicetak persis)' WHERE kode = 'nomor_surat';
UPDATE variabel_surat SET label = 'Nomor SK (diisi apa adanya - nomor lengkap sesuai format resmi, dicetak persis)' WHERE kode = 'nomor_sk';
UPDATE variabel_surat SET label = 'Sifat (mis. Biasa / Penting / Segera / Rahasia)' WHERE kode = 'sifat';
UPDATE variabel_surat SET label = 'Hari (nama hari, mis. Senin)' WHERE kode = 'hari';
UPDATE variabel_surat SET label = 'Lampiran (mis. "1 Berkas", atau kosongkan jika tidak ada)' WHERE kode = 'lampiran';
UPDATE variabel_surat SET label = 'Waktu (mis. "09.00 WITA" atau "09.00 s.d. selesai")' WHERE kode = 'waktu';
