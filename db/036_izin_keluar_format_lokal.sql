USE aurat;
-- Izin Keluar Kantor: Pegawai balik ke format lokal PA Rantau (versi 1
-- template Pegawai, commit a75cd14) atas permintaan user 2026-10-12 -
-- membatalkan 019 yang menyamakan Pegawai ke Lampiran 2 SK 071/2008 asli.
-- Format lokal: "Dengan ini saya" Nama/NIP/Jabatan/Unit Kerja pemberi izin,
-- penerima pakai NIP + Jabatan (bukan NIP/Gol), plus blok "Mengetahui
-- Ketua/Wakil Ketua PA Rantau". Hakim tetap format PERMA 7/2016 (variabel
-- sama seperti versi 1), cuma dirapikan: isian rata kiri, baris kosong yang
-- bikin label kiri gak sejajar dengan isian kanan dibuang, garis bawah nama
-- dihapus, jarak antar-baris seragam.
--
-- Sebelum migrasi ini dijalankan, salin ke templates/uploaded/:
--   templates/izin_keluar_kantor_pegawai.docx -> 60e04f650d983ea560b6983e641143cb.docx
--   templates/izin_keluar_kantor_hakim.docx   -> ca2238181153015d47f55994a36a6c89.docx
-- Idempotent.

SET @js_izin = (SELECT id FROM jenis_surat WHERE kode = 'izin_keluar_kantor');

-- Peran + variabel "mengetahui" yang dihapus di 019.
INSERT INTO peran_pegawai_surat (jenis_surat_id, kode, label, wajib, urutan_tampil)
SELECT @js_izin, 'mengetahui', 'Mengetahui (Ketua/Wakil Ketua PA Rantau)', 0, 30
WHERE NOT EXISTS (SELECT 1 FROM peran_pegawai_surat WHERE jenis_surat_id = @js_izin AND kode = 'mengetahui');

INSERT INTO variabel_surat (kode, label, tipe_input, opsi_pilihan, sumber, field_pegawai, fungsi_pasca, parameter_variabel, wajib_default, urutan_tampil)
SELECT 'mengetahui_nama_lengkap', 'Nama (Mengetahui)', 'text', NULL, 'pegawai', NULL, 'nama_bergelar', NULL, 1, 40
WHERE NOT EXISTS (SELECT 1 FROM variabel_surat WHERE kode = 'mengetahui_nama_lengkap');

-- Daftar berkas baru per sub_jenis + variabel yang dipasang ke masing-masing.
CREATE TEMPORARY TABLE tmp_izin_tpl (sub_kode VARCHAR(20), nama_berkas VARCHAR(100), nama_asli VARCHAR(100));
INSERT INTO tmp_izin_tpl VALUES
    ('pegawai', '60e04f650d983ea560b6983e641143cb.docx', 'izin_keluar_kantor_pegawai.docx'),
    ('hakim',   'ca2238181153015d47f55994a36a6c89.docx', 'izin_keluar_kantor_hakim.docx');

CREATE TEMPORARY TABLE tmp_izin_var (sub_kode VARCHAR(20), kode VARCHAR(100), peran VARCHAR(50), urutan SMALLINT);
INSERT INTO tmp_izin_var VALUES
    ('pegawai', 'pemberi_izin_nama_lengkap', 'pemberi_izin', 10),
    ('pegawai', 'pemberi_izin_nip', 'pemberi_izin', 15),
    ('pegawai', 'pemberi_izin_jabatan', 'pemberi_izin', 20),
    ('pegawai', 'pemberi_izin_unit_kerja', 'pemberi_izin', 30),
    ('pegawai', 'penerima_izin_nama_lengkap', 'penerima_izin', 40),
    ('pegawai', 'penerima_izin_nip', 'penerima_izin', 50),
    ('pegawai', 'penerima_izin_jabatan', 'penerima_izin', 55),
    ('pegawai', 'penerima_izin_unit_kerja', 'penerima_izin', 70),
    ('pegawai', 'keperluan_izin', NULL, 80),
    ('pegawai', 'tanggal_surat', NULL, 90),
    ('pegawai', 'mengetahui_nama_lengkap', 'mengetahui', 100),
    ('hakim', 'pemberi_izin_nama_lengkap', 'pemberi_izin', 10),
    ('hakim', 'pemberi_izin_jabatan', 'pemberi_izin', 20),
    ('hakim', 'penerima_izin_nama_lengkap', 'penerima_izin', 30),
    ('hakim', 'penerima_izin_nip', 'penerima_izin', 40),
    ('hakim', 'hari', NULL, 50),
    ('hakim', 'tanggal_izin_keluar', NULL, 60),
    ('hakim', 'jam_mulai_izin', NULL, 70),
    ('hakim', 'jam_selesai_izin', NULL, 80),
    ('hakim', 'keperluan_izin', NULL, 90),
    ('hakim', 'tanggal_surat', NULL, 100);

-- Versi template baru per sub_jenis (dibuat nonaktif dulu, diaktifkan di bawah).
INSERT INTO template_surat (jenis_surat_id, sub_jenis_surat_id, tipe_dokumen, nama_berkas, nama_asli, versi, status_aktif, diunggah_oleh)
SELECT @js_izin, s.id, 'utama', b.nama_berkas, b.nama_asli,
       (SELECT COALESCE(MAX(t.versi), 0) + 1 FROM template_surat t
        WHERE t.jenis_surat_id = @js_izin AND t.sub_jenis_surat_id = s.id AND t.tipe_dokumen = 'utama'),
       0, NULL
FROM tmp_izin_tpl b
JOIN sub_jenis_surat s ON s.jenis_surat_id = @js_izin AND s.kode = b.sub_kode
WHERE NOT EXISTS (SELECT 1 FROM template_surat x WHERE x.nama_berkas = b.nama_berkas);

-- Satu versi aktif per sub_jenis: yang baru.
UPDATE template_surat t
JOIN sub_jenis_surat s ON s.id = t.sub_jenis_surat_id
JOIN tmp_izin_tpl b ON b.sub_kode = s.kode
SET t.status_aktif = (t.nama_berkas = b.nama_berkas)
WHERE t.jenis_surat_id = @js_izin AND t.tipe_dokumen = 'utama';

INSERT INTO template_surat_variabel (template_surat_id, variabel_surat_id, peran_pegawai_surat_id, urutan_tampil)
SELECT t.id, v.id, p.id, src.urutan
FROM tmp_izin_var src
JOIN tmp_izin_tpl b ON b.sub_kode = src.sub_kode
JOIN template_surat t ON t.nama_berkas = b.nama_berkas
JOIN variabel_surat v ON v.kode = src.kode
LEFT JOIN peran_pegawai_surat p ON p.jenis_surat_id = @js_izin AND p.kode = src.peran
WHERE NOT EXISTS (SELECT 1 FROM template_surat_variabel x WHERE x.template_surat_id = t.id AND x.variabel_surat_id = v.id);

DROP TEMPORARY TABLE tmp_izin_tpl;
DROP TEMPORARY TABLE tmp_izin_var;
