-- Notula Rapat (docs/ide-jenis-surat-undangan.md item 3) - record TERPISAH dari
-- Undangan, dibuat SUSULAN setelah acara (bukan bareng kayak Daftar Hadir).
-- Link ke undangan sumbernya lewat surat/index.php?kode=notula&dari={surat_
-- diterbitkan.id} - form Notula prefill dari nilai_lengkap + tabel_lengkap
-- baris itu (lihat kolom baru di bawah), tapi tetap bisa diedit sebelum submit.
-- Notulis TIDAK dapat akun/login baru - cuma peran_pegawai_surat (dropdown
-- pilih pegawai), sama kayak petugas_cuti/diperintah yang sudah ada.

USE aurat;

-- Histori tabel (blok_tabel_surat) belum kesimpen sama sekali sebelumnya -
-- cuma nilai_lengkap (variabel skalar) yang direkam. Perlu buat prefill
-- peserta rapat Notula dari peserta Daftar Hadir undangan sumbernya.
ALTER TABLE surat_diterbitkan
  ADD COLUMN IF NOT EXISTS tabel_lengkap LONGTEXT NULL AFTER nilai_lengkap;

INSERT INTO jenis_surat (kode, nama, deskripsi, kategori, icon, kop_surat, status_aktif, urutan_tampil)
SELECT 'notula', 'Notula Rapat', 'Dibuat susulan dari Undangan yang sudah diterbitkan - lihat tombol "Buat Notula" di Riwayat Surat Diterbitkan.', 'single_dokumen', 'dokumen', 'standar', 1,
       (SELECT COALESCE(MAX(urutan_tampil), 0) + 10 FROM (SELECT urutan_tampil FROM jenis_surat) x)
WHERE NOT EXISTS (SELECT 1 FROM jenis_surat WHERE kode = 'notula');

INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil)
SELECT js.id, 'notulis', 'Notulis', 1, 10
FROM jenis_surat js WHERE js.kode = 'notula'
  AND NOT EXISTS (SELECT 1 FROM peran_pegawai_surat p WHERE p.jenis_surat_id = js.id AND p.kode = 'notulis');

INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil)
SELECT js.id, 'pejabat_acara', 'Pejabat yang Punya Acara', 1, 20
FROM jenis_surat js WHERE js.kode = 'notula'
  AND NOT EXISTS (SELECT 1 FROM peran_pegawai_surat p WHERE p.jenis_surat_id = js.id AND p.kode = 'pejabat_acara');

-- Variabel baru khusus Notula. hari/tanggal_acara/waktu/tempat/nama_acara
-- SENGAJA reuse by-kode dari Undangan (bukan bikin dasar/tanggal/pukul/acara
-- baru) - biar prefill dari nilai_lengkap Undangan tinggal cocok-in kode
-- langsung, gak perlu tabel mapping kode terpisah.
INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default)
SELECT 'dasar', 'Dasar (rujukan rapat, mis. nomor & tanggal surat undangan)', 'textarea', 'manual', 1
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'dasar');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default)
SELECT 'peserta_rapat', 'Peserta Rapat', 'textarea', 'manual', 1
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'peserta_rapat');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default)
SELECT 'isi_notula', 'Isi (uraian jalannya rapat/keputusan)', 'textarea', 'manual', 1
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'isi_notula');

-- tipe_input='file' - kode baru, ditangani khusus di surat/index.php (render
-- <input type=file>, validasi lewat Aurat\FotoUpload, disuntik ke docx pakai
-- TemplateProcessor::setImageValue() bukan setValue() biasa - lihat
-- DocxGenerator. Opsional (wajib_default=0), boleh kosong.
INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default)
SELECT 'foto_notula', 'Foto Dokumentasi Kegiatan', 'file', 'manual', 0
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'foto_notula');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai)
SELECT 'notulis_nama_lengkap', 'Nama Lengkap (Notulis)', 'text', 'pegawai', NULL
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'notulis_nama_lengkap');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai)
SELECT 'notulis_nip', 'NIP (Notulis)', 'text', 'pegawai', 'nip'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'notulis_nip');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai)
SELECT 'pejabat_acara_nama_lengkap', 'Nama Lengkap (Pejabat yang Punya Acara)', 'text', 'pegawai', NULL
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'pejabat_acara_nama_lengkap');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai)
SELECT 'pejabat_acara_nip', 'NIP (Pejabat yang Punya Acara)', 'text', 'pegawai', 'nip'
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'pejabat_acara_nip');

-- notulis_nama_lengkap butuh fungsi_pasca nama_bergelar (konsisten sama pola
-- penandatangan_nama_lengkap dkk) - guarded INSERT di atas gak bisa set ini
-- sekaligus krn kolom fungsi_pasca gak ada di statement itu; UPDATE terpisah,
-- aman idempoten (no-op kalau sudah kepasang).
UPDATE variabel_surat SET fungsi_pasca = 'nama_bergelar' WHERE kode IN ('notulis_nama_lengkap', 'pejabat_acara_nama_lengkap') AND (fungsi_pasca IS NULL OR fungsi_pasca = '');
