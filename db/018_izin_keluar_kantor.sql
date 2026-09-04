-- Jenis surat baru "Izin Keluar Kantor" (izin_keluar_kantor), dari 2 file .docx
-- asli user: "Izin_Keluar_Kantor_Pegawai.docx" (Lampiran 2 SK Ketua MA RI No
-- 071/KMA/SK/V/2008) dan "SURAT IZIN KANTOR HAKIM.docx" (PERMA No 7/2016) —
-- 2 dokumen beda struktur, jadi SATU jenis_surat lewat mekanisme sub_jenis_surat
-- yang sudah ada (persis pola "sk" -> tim_kerja/panitia, lihat db/002), bukan
-- 2 jenis_surat terpisah.
--
-- Berkas .docx-nya sendiri diunggah lewat menu admin (Kelola Template), bukan
-- lewat migrasi ini (lihat catatan project_aurat_mail_app.md "SOLVED 2026-08-06"
-- soal kenapa file transfer ke templates/*.docx tidak pernah cukup). Migrasi
-- ini hanya menyiapkan jenis_surat/sub_jenis/peran/variabel-nya.
--
-- Peran & variabel PEGAWAI-nama_lengkap/jabatan, PENERIMA-nama_lengkap/nip,
-- dan "keperluan_izin" dipakai BERSAMA oleh kedua sub_jenis (peran_pegawai_surat
-- adalah slot jenis_surat-wide, lihat db/002) — sisanya (NIP/Unit Kerja pemberi,
-- Jabatan/Unit Kerja penerima, peran "mengetahui", field jam/tanggal keluar)
-- cuma dipasang ke SALAH SATU sub_jenis lewat template_surat_variabel nanti
-- (bukan di migrasi ini), sesuai field yang benar-benar ada di masing-masing
-- dokumen sumber.
--
-- Catatan (dikonfirmasi user via AskUserQuestion, 2026-09-04):
-- - Baris "Mengetahui: Ketua/Wakil Ketua PA Rantau" di form Pegawai (footnote
--   asli "** Coret salah satu") DI-AUTO-FILL lewat pegawai-picker baru (peran
--   "mengetahui"), bukan dibiarkan blank manual.
-- - Baris "Selaku" (jabatan pemberi izin) di form Hakim diambil OTOMATIS dari
--   data pegawai yang dipilih sbg pemberi_izin (field_pegawai='jabatan'),
--   bukan input manual terpisah.

USE aurat;

-- ============================================================
-- 1) jenis_surat + sub_jenis_surat (2 varian)
-- ============================================================
INSERT INTO jenis_surat (kode, nama, deskripsi, kategori, icon, kop_surat, pola_nama_unduhan, status_aktif, urutan_tampil)
SELECT 'izin_keluar_kantor', 'Izin Keluar Kantor',
       'Surat izin keluar kantor pada jam kerja - varian Pegawai (SK Ketua MA No 071/KMA/SK/V/2008) atau Hakim (PERMA No 7/2016).',
       'dua_dokumen', 'tas_kerja', 'standar', 'Izin_Keluar_Kantor_{penerima_izin_nama_lengkap}', 1, 30
WHERE NOT EXISTS (SELECT 1 FROM jenis_surat WHERE kode = 'izin_keluar_kantor');

SET @js_izin = (SELECT id FROM jenis_surat WHERE kode = 'izin_keluar_kantor');

INSERT INTO sub_jenis_surat (jenis_surat_id, kode, label, urutan_tampil)
SELECT @js_izin, 'pegawai', 'Pegawai', 10
WHERE NOT EXISTS (SELECT 1 FROM sub_jenis_surat WHERE jenis_surat_id = @js_izin AND kode = 'pegawai');

INSERT INTO sub_jenis_surat (jenis_surat_id, kode, label, urutan_tampil)
SELECT @js_izin, 'hakim', 'Hakim', 20
WHERE NOT EXISTS (SELECT 1 FROM sub_jenis_surat WHERE jenis_surat_id = @js_izin AND kode = 'hakim');

-- ============================================================
-- 2) peran_pegawai_surat (3 slot, jenis_surat-wide; "mengetahui" cuma
--    kepasang ke template Pegawai nanti, tapi slotnya tetap didaftar di sini)
-- ============================================================
INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil)
SELECT @js_izin, 'pemberi_izin', 'Pejabat/Atasan yang Memberikan Izin', 1, 10
WHERE NOT EXISTS (SELECT 1 FROM peran_pegawai_surat WHERE jenis_surat_id = @js_izin AND kode = 'pemberi_izin');

INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil)
SELECT @js_izin, 'penerima_izin', 'Pegawai/Hakim yang Diberi Izin', 1, 20
WHERE NOT EXISTS (SELECT 1 FROM peran_pegawai_surat WHERE jenis_surat_id = @js_izin AND kode = 'penerima_izin');

-- wajib=0: peran ini cuma dipasang ke template sub_jenis Pegawai, tapi picker-nya
-- tetap tampil di form manapun jenis_surat ini dirender (peran_pegawai_surat
-- gak scoped per sub_jenis) - lihat fix di surat/index.php ($peranDipakai,
-- 2026-09-04) yang udah nyaring picker biar cuma nongol di sub_jenis yang
-- beneran pasang variabelnya. wajib=0 dipertahankan sbg jaga-jaga kedua.
INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil)
SELECT @js_izin, 'mengetahui', 'Mengetahui (Ketua/Wakil Ketua PA Rantau)', 0, 30
WHERE NOT EXISTS (SELECT 1 FROM peran_pegawai_surat WHERE jenis_surat_id = @js_izin AND kode = 'mengetahui');

-- ============================================================
-- 3) variabel_surat baru (dipasang ke template_surat_variabel manual lewat
--    admin UI sesudah upload - lihat catatan di atas)
-- ============================================================
INSERT INTO variabel_surat (kode, label, tipe_input, opsi_pilihan, sumber, field_pegawai, fungsi_pasca, parameter_variabel, wajib_default, urutan_tampil)
SELECT * FROM (
    -- dipakai KEDUA sub_jenis
    SELECT 'pemberi_izin_nama_lengkap' kode, 'Nama Pemberi Izin' label, 'text' tipe_input, NULL opsi_pilihan, 'pegawai' sumber, NULL field_pegawai, 'nama_bergelar' fungsi_pasca, NULL parameter_variabel, 1 wajib_default, 10 urutan_tampil UNION ALL
    SELECT 'pemberi_izin_jabatan', 'Jabatan Pemberi Izin', 'text', NULL, 'pegawai', 'jabatan', NULL, NULL, 1, 11 UNION ALL
    SELECT 'penerima_izin_nama_lengkap', 'Nama Penerima Izin', 'text', NULL, 'pegawai', NULL, 'nama_bergelar', NULL, 1, 20 UNION ALL
    SELECT 'penerima_izin_nip', 'NIP Penerima Izin', 'text', NULL, 'pegawai', 'nip', NULL, NULL, 1, 21 UNION ALL
    SELECT 'keperluan_izin', 'Untuk Keperluan', 'textarea', NULL, 'manual', NULL, NULL, NULL, 1, 30 UNION ALL
    -- khusus sub_jenis Pegawai
    SELECT 'pemberi_izin_nip', 'NIP Pemberi Izin', 'text', NULL, 'pegawai', 'nip', NULL, NULL, 1, 12 UNION ALL
    SELECT 'pemberi_izin_unit_kerja', 'Unit Kerja Pemberi Izin', 'text', NULL, 'pegawai', 'unit_kerja', NULL, NULL, 1, 13 UNION ALL
    SELECT 'penerima_izin_jabatan', 'Jabatan Penerima Izin', 'text', NULL, 'pegawai', 'jabatan', NULL, NULL, 1, 22 UNION ALL
    SELECT 'penerima_izin_unit_kerja', 'Unit Kerja Penerima Izin', 'text', NULL, 'pegawai', 'unit_kerja', NULL, NULL, 1, 23 UNION ALL
    SELECT 'mengetahui_nama_lengkap', 'Nama (Mengetahui)', 'text', NULL, 'pegawai', NULL, 'nama_bergelar', NULL, 1, 40 UNION ALL
    SELECT 'mengetahui_nip', 'NIP (Mengetahui)', 'text', NULL, 'pegawai', 'nip', NULL, NULL, 1, 41 UNION ALL
    -- khusus sub_jenis Hakim
    SELECT 'tanggal_izin_keluar', 'Tanggal Keluar Kantor', 'date', NULL, 'manual', NULL, 'tanggal_indonesia', NULL, 1, 31 UNION ALL
    SELECT 'jam_mulai_izin', 'Jam Mulai (HH:MM)', 'text', NULL, 'manual', NULL, NULL, NULL, 1, 32 UNION ALL
    SELECT 'jam_selesai_izin', 'Jam Selesai (HH:MM)', 'text', NULL, 'manual', NULL, NULL, NULL, 1, 33
) src WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = src.kode);
