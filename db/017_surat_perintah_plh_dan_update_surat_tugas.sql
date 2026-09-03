-- Dua perubahan berdasarkan draf .docx baru dari user (folder "draf template"):
--
-- 1) Jenis surat "Surat Penunjukan Pelaksana Harian" (pelaksana_harian) DIHAPUS,
--    digantikan jenis surat baru "Surat Perintah Pelaksana Harian"
--    (surat_perintah_plh) — format SURAT PERINTAH, satu template, field pilihan
--    jabatan (Ketua/Panitera/Sekretaris) alih-alih 3 sub-jenis terpisah.
-- 2) Template Surat Tugas (surat_tugas) diperbarui mengikuti "Naskah Dinas ST
--    Kelompok.docx": tabel lampiran nambah kolom Gol + gabung Nama+NIP jadi satu
--    kolom, dasar hukum & bagian Menimbang jadi field bebas (bukan lagi PMK
--    Perjalanan Dinas yang lama).
--
-- Berkas .docx-nya sendiri di-unggah lewat menu admin (Kelola Template), BUKAN
-- lewat migrasi ini (lihat catatan project_aurat_mail_app.md soal kenapa file
-- transfer ke templates/*.docx tidak pernah cukup). Migrasi ini hanya menyiapkan
-- data jenis_surat/peran_pegawai/variabel/blok_tabel-nya.

USE aurat;

-- ============================================================
-- 1) Hapus jenis surat lama "pelaksana_harian"
-- ============================================================
-- Sama seperti db/016 (kasus Cuti): hapus dulu template_surat_variabel-nya
-- secara eksplisit sebelum DELETE jenis_surat, karena fk_tsv_peran
-- (peran_pegawai_surat_id) tidak ON DELETE CASCADE.
DELETE tsv FROM template_surat_variabel tsv
JOIN template_surat ts ON ts.id = tsv.template_surat_id
JOIN jenis_surat js ON js.id = ts.jenis_surat_id
WHERE js.kode = 'pelaksana_harian';

DELETE FROM jenis_surat WHERE kode = 'pelaksana_harian';

-- ============================================================
-- 2) Jenis surat baru "surat_perintah_plh"
-- ============================================================
INSERT INTO jenis_surat (kode, nama, deskripsi, kategori, icon, kop_surat, pola_nama_unduhan, status_aktif, urutan_tampil)
SELECT 'surat_perintah_plh', 'Surat Perintah Pelaksana Harian',
       'Surat Perintah (bukan Surat Penunjukan) untuk PLH pucuk pimpinan: Ketua, Panitera, atau Sekretaris.',
       'single_dokumen', 'bendera', 'standar', 'Surat_Perintah_PLH_{diperintah_nama_lengkap}', 1, 20
WHERE NOT EXISTS (SELECT 1 FROM jenis_surat WHERE kode = 'surat_perintah_plh');

SET @js_plh = (SELECT id FROM jenis_surat WHERE kode = 'surat_perintah_plh');

INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil)
SELECT @js_plh, 'diperintah', 'Pegawai yang Diperintah (PLH)', 1, 10
WHERE NOT EXISTS (SELECT 1 FROM peran_pegawai_surat WHERE jenis_surat_id = @js_plh AND kode = 'diperintah');

SET @peran_diperintah = (SELECT id FROM peran_pegawai_surat WHERE jenis_surat_id = @js_plh AND kode = 'diperintah');

-- Variabel baru khusus surat_perintah_plh (nomor_surat & tanggal_surat pakai
-- variabel bersama yang sudah ada, dipasang ke template lewat template_surat_variabel
-- setelah berkas diunggah, bukan dibuat ulang di sini).
INSERT INTO variabel_surat (kode, label, tipe_input, opsi_pilihan, sumber, field_pegawai, fungsi_pasca, parameter_variabel, fungsi_parameter_1, fungsi_parameter_2, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
    'jabatan_diplh' AS kode, 'Jabatan yang Di-PLH-kan' AS label, 'select' AS tipe_input,
    '["Ketua","Panitera","Sekretaris"]' AS opsi_pilihan, 'manual' AS sumber,
    NULL AS field_pegawai, NULL AS fungsi_pasca, NULL AS parameter_variabel,
    NULL AS fungsi_parameter_1, NULL AS fungsi_parameter_2, 1 AS wajib_default, 10 AS urutan_tampil
) t WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'jabatan_diplh');

-- Kode 'jabatan_diplh' ternyata SUDAH ADA sejak jenis surat pelaksana_harian lama
-- (field teks bebas "Jabatan yang Di-Plh-kan (lengkap, mis. ...)") — baris itu jadi
-- yatim (bukan terhapus) begitu jenis surat lamanya dihapus di atas, tapi kode-nya
-- tetap bentrok dengan yang di-INSERT barusan (INSERT jadi no-op krn WHERE NOT
-- EXISTS). Timpa jadi spek yang benar (dropdown, bukan teks bebas) — aman,
-- baris lama itu memang sudah tidak dipakai jenis surat manapun.
UPDATE variabel_surat
SET label = 'Jabatan yang Di-PLH-kan', tipe_input = 'select',
    opsi_pilihan = '["Ketua","Panitera","Sekretaris"]', sumber = 'manual',
    field_pegawai = NULL, fungsi_pasca = NULL, parameter_variabel = NULL,
    fungsi_parameter_1 = NULL, fungsi_parameter_2 = NULL, wajib_default = 1
WHERE kode = 'jabatan_diplh';

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default, urutan_tampil)
SELECT 'dasar_tambahan', 'Dasar Tambahan (Item ke-5 Dasar Hukum — mis. surat cuti/surat tugas atasan yang melatarbelakangi)', 'textarea', 'manual', 1, 20
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'dasar_tambahan');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca, wajib_default, urutan_tampil)
SELECT 'tanggal_mulai', 'Terhitung Mulai Tanggal', 'date', 'manual', 'tanggal_indonesia', 1, 30
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'tanggal_mulai');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca, wajib_default, urutan_tampil)
SELECT 'tanggal_selesai', 's.d. Tanggal (kosongkan jika tidak ada batas akhir)', 'date', 'manual', 'tanggal_indonesia', 0, 40
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'tanggal_selesai');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca, parameter_variabel, wajib_default, urutan_tampil)
SELECT 'sd_klausa', 'Klausa "s.d ..." (otomatis)', 'text', 'turunan', 'sd_tanggal_klausa', '["tanggal_selesai"]', 0, 50
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'sd_klausa');

-- Perbaikan: NilaiResolver mewariskan nilai MENTAH (belum lewat fungsi_pasca variabel
-- lain) ke parameter turunan (lihat komentar NilaiResolver::nilaiMentah()) — jadi
-- 'tanggal_selesai' TIDAK BOLEH punya fungsi_pasca sendiri (kalau ada, itu cuma
-- berlaku kalau ia jadi placeholder langsung, yang mana ia bukan), dan 'sd_klausa'
-- harus pakai fungsi_pasca 'sd_tanggal_klausa' (baru, Formatter::sdTanggalKlausa,
-- format tanggalnya sendiri dari nilai mentah) — bukan 'klausa_jika_ada' yang
-- mengasumsikan nilainya sudah diformat.
UPDATE variabel_surat SET fungsi_pasca = NULL WHERE kode = 'tanggal_selesai';
UPDATE variabel_surat SET fungsi_pasca = 'sd_tanggal_klausa', fungsi_parameter_1 = NULL, fungsi_parameter_2 = NULL WHERE kode = 'sd_klausa';

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca, wajib_default, urutan_tampil)
SELECT 'diperintah_nama_lengkap', 'Nama Lengkap (Diperintah)', 'text', 'pegawai', 'nama_bergelar', 0, 60
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'diperintah_nama_lengkap');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai, wajib_default, urutan_tampil)
SELECT 'diperintah_nip', 'NIP (Diperintah)', 'text', 'pegawai', 'nip', 0, 70
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'diperintah_nip');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, fungsi_pasca, wajib_default, urutan_tampil)
SELECT 'diperintah_pangkat_golongan', 'Pangkat/Golongan (Diperintah)', 'text', 'pegawai', 'pangkat_golongan', 0, 80
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'diperintah_pangkat_golongan');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, field_pegawai, wajib_default, urutan_tampil)
SELECT 'diperintah_jabatan', 'Jabatan Asli (Diperintah)', 'text', 'pegawai', 'jabatan', 0, 90
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'diperintah_jabatan');

-- ============================================================
-- 3) Update variabel & blok tabel Surat Tugas (template-nya diunggah manual
--    lewat admin UI setelah migrasi ini, sama seperti surat_perintah_plh)
-- ============================================================
INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default, urutan_tampil)
SELECT 'menimbang_konteks', 'Konteks/Latar Belakang Tugas (mengisi ".... dalam rangka ...., maka dipandang perlu")', 'text', 'manual', 1, 15
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'menimbang_konteks');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default, urutan_tampil)
SELECT 'dasar_hukum_tugas', 'Dasar Hukum (ketik lengkap bernomor, mis. "1. Surat ...; 2. Berdasarkan Perintah Pimpinan ... tanggal ....")', 'textarea', 'manual', 1, 16
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'dasar_hukum_tugas');

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, wajib_default, urutan_tampil)
SELECT 'tahun_anggaran_dipa', 'Tahun Anggaran DIPA (4 digit, mis. 2026)', 'text', 'manual', 1, 55
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'tahun_anggaran_dipa');

-- Blok tabel lampiran Surat Tugas (id sudah ada, kode='no', jenis_surat_id=9):
-- ganti kolom 'nama'+'nip' jadi satu kolom 'nama_nip' (fungsi nama_dan_nip yang
-- baru), tambah kolom 'gol' (field golongan_ruang), 'jabatan_satker' & 'tanggal'
-- tetap sama seperti sebelumnya.
SET @blok_no = (SELECT id FROM blok_tabel_surat WHERE jenis_surat_id = (SELECT id FROM jenis_surat WHERE kode = 'surat_tugas') AND kode = 'no');

DELETE FROM blok_tabel_surat_kolom WHERE blok_tabel_surat_id = @blok_no AND kode IN ('nama', 'nip');

INSERT INTO blok_tabel_surat_kolom (blok_tabel_surat_id, kode, label, sumber, fungsi_pasca, urutan_kolom)
SELECT @blok_no, 'nama_nip', 'Nama dan NIP', 'pegawai_fungsi', 'nama_dan_nip', 20
WHERE NOT EXISTS (SELECT 1 FROM blok_tabel_surat_kolom WHERE blok_tabel_surat_id = @blok_no AND kode = 'nama_nip');

INSERT INTO blok_tabel_surat_kolom (blok_tabel_surat_id, kode, label, sumber, field_pegawai, urutan_kolom)
SELECT @blok_no, 'gol', 'Gol', 'pegawai_field', 'golongan_ruang', 45
WHERE NOT EXISTS (SELECT 1 FROM blok_tabel_surat_kolom WHERE blok_tabel_surat_id = @blok_no AND kode = 'gol');
