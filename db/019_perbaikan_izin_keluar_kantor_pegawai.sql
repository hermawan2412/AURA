-- Perbaikan format Izin Keluar Kantor (sub_jenis Pegawai) supaya SAMA PERSIS
-- dengan Lampiran 2 SK Ketua MA RI No 071/KMA/SK/V/2008 asli (dicek langsung
-- dari scan resminya, bukan cuma percaya file .docx user - ternyata file itu
-- versi lokal PA Rantau yang udah dimodifikasi: nambah blok "Mengetahui
-- Ketua/Wakil Ketua" yang GAK ADA di SK asli, ganti field bebas "Pejabat"
-- jadi Nama/NIP/Jabatan/Unit Kerja terstruktur, dan pakai "NIP" bukan
-- "NIP/Gol" utk penerima izin). User diberi pilihan (AskUserQuestion) dan
-- pilih "samakan persis ke Lampiran 2 resmi", bukan pertahankan versi lokal.
--
-- Perubahan (lihat templates/izin_keluar_kantor_pegawai.docx versi baru,
-- diunggah manual lewat admin UI - migrasi ini CUMA nyiapin variabel_surat-nya):
-- - Blok "Pejabat" (pemberi izin) sekarang 4 baris tanpa label (persis SK
--   asli), diisi otomatis: nama lengkap/jabatan/unit kerja pemberi izin,
--   baris ke-4 kosong + tanda "*" (posisi tanda footnote sesuai SK asli).
-- - "NIP/Gol" penerima izin sekarang benar-benar gabungan NIP + Golongan
--   Ruang (dulu cuma NIP) - butuh variabel baru 'penerima_izin_golongan_ruang'.
-- - Baris "Jabatan" penerima izin DIHAPUS (SK asli gak punya field ini utk
--   penerima).
-- - Blok "Mengetahui Ketua/Wakil Ketua PA Rantau" DIHAPUS TOTAL (gak ada di
--   SK asli) - peran 'mengetahui' + variabel mengetahui_nama_lengkap/
--   mengetahui_nip jadi gak terpakai di mana pun, dihapus dari katalog
--   (bukan cuma dilepas dari template) di bagian akhir migrasi ini, setelah
--   versi lama template (yg masih mereferensikannya) juga dihapus manual
--   lewat admin UI.
-- - Teks footnote "*" diganti ke wording SK asli persis ("Nama pejabat
--   atasan langsung dan Hakim atau Pegawai Negeri yang memohon izin keluar
--   kantor"), bukan versi lokal yg nyebut "diketahui oleh Ketua/Wakil Ketua".
--
-- pemberi_izin_nip dan penerima_izin_jabatan (variabel lama) TETAP ada di
-- katalog variabel_surat (gak dihapus) - cuma gak dipasang lagi ke template
-- Pegawai yang baru. Masih reusable kalau jenis surat lain nanti butuh pola
-- serupa.

USE aurat;

INSERT INTO variabel_surat (kode, label, tipe_input, opsi_pilihan, sumber, field_pegawai, fungsi_pasca, parameter_variabel, wajib_default, urutan_tampil)
SELECT 'penerima_izin_golongan_ruang', 'Golongan Ruang Penerima Izin', 'text', NULL, 'pegawai', 'golongan_ruang', NULL, NULL, 1, 24
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'penerima_izin_golongan_ruang');

-- Hapus peran 'mengetahui' + variabelnya dari katalog - HANYA jalan kalau
-- versi lama template Pegawai (yg masih mereferensikannya lewat
-- template_surat_variabel) sudah dihapus duluan lewat admin UI (Kelola
-- Template > Hapus Versi Lama), krn fk_tsv_peran tidak ON DELETE CASCADE.
-- Kalau masih ada yg mereferensikan, statement DELETE di bawah gagal
-- (error 1451) - itu tandanya versi lama belum dihapus, bukan bug migrasi.
DELETE FROM variabel_surat WHERE kode IN ('mengetahui_nama_lengkap', 'mengetahui_nip');
DELETE FROM peran_pegawai_surat
WHERE jenis_surat_id = (SELECT id FROM jenis_surat WHERE kode = 'izin_keluar_kantor')
  AND kode = 'mengetahui';
