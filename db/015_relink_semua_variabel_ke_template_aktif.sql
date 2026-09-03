-- PENTING -- BACA DULU: setiap kali templates/cuti.docx diupload ulang lewat cara
-- yang BENAR (admin: Kelola Jenis Surat > Surat Cuti > Kelola Template & Variabel >
-- Ganti Template), sistem membuat baris template_surat BARU (versi baru, id baru) --
-- dan ke-25 variabel yang selama ini terpasang TIDAK ikut pindah ke versi baru itu
-- secara otomatis. Selama ini itu harus disambung ulang manual satu-satu lewat
-- halaman "Kelola Variabel" (23x klik "Pasang ke Template" + 2 variabel tambahan
-- lewat cara khusus) -- skrip ini menggantikan SEMUA itu jadi satu kali jalan.
--
-- KAPAN DIJALANKAN: jalankan skrip ini SETELAH setiap kali upload versi baru
-- templates/cuti.docx lewat halaman admin (bukan lewat transfer file biasa --
-- baca catatan sesi 2026-08-06 kalau lupa kenapa). Aman dijalankan berkali-kali
-- kapan saja, termasuk sekarang walau belum ada upload baru (idempoten, NOT EXISTS).
--
-- Mencakup SEMUA 25 variabel surat Cuti: 23 placeholder asli (termasuk 13 yang
-- sudah ada sejak sebelum sesi ini dan TIDAK punya migrasi sendiri di db/007-014)
-- + 2 variabel "internal" yang cuma dipakai sbg parameter (pemohon_tmt,
-- nomor_urut_cuti) yang tidak pernah muncul di daftar placeholder-belum-terpasang.

USE aurat;

INSERT INTO template_surat_variabel (template_surat_id, variabel_surat_id, peran_pegawai_surat_id, urutan_tampil, terdeteksi_otomatis)
SELECT ts.id, vs.id, pp.id, daftar.urut, 0
FROM template_surat ts
JOIN jenis_surat j ON j.id = ts.jenis_surat_id AND j.kode = 'cuti' AND ts.status_aktif = 1 AND ts.sub_jenis_surat_id IS NULL
JOIN (
  -- kode variabel, kode peran (NULL kalau bukan sumber=pegawai), urutan tampil
  SELECT 'pemohon_nip' AS kode, 'pemohon' AS peran, 10 AS urut
  UNION ALL SELECT 'pemohon_nama_lengkap', 'pemohon', 20
  UNION ALL SELECT 'pemohon_golongan_ruang', 'pemohon', 30
  UNION ALL SELECT 'pemohon_jabatan', 'pemohon', 40
  UNION ALL SELECT 'pemohon_unit_kerja', 'pemohon', 45
  UNION ALL SELECT 'pemohon_tmt', 'pemohon', 44
  UNION ALL SELECT 'pemohon_masa_kerja', NULL, 46
  UNION ALL SELECT 'nomor_urut_cuti', NULL, 48
  UNION ALL SELECT 'nomor_surat_cuti_lengkap', NULL, 49
  UNION ALL SELECT 'tanggal_surat', NULL, 60
  UNION ALL SELECT 'jenis_cuti', NULL, 70
  UNION ALL SELECT 'catatan_cuti', NULL, 75
  UNION ALL SELECT 'tanggal_mulai', NULL, 80
  UNION ALL SELECT 'tanggal_selesai', NULL, 90
  UNION ALL SELECT 'lama_cuti_hari', NULL, 100
  UNION ALL SELECT 'alamat_cuti', NULL, 110
  UNION ALL SELECT 'telepon_cuti', NULL, 115
  UNION ALL SELECT 'alasan', NULL, 120
  UNION ALL SELECT 'atasan_langsung_nama_lengkap', 'atasan_langsung', 150
  UNION ALL SELECT 'atasan_langsung_nip', 'atasan_langsung', 160
  UNION ALL SELECT 'atasan_langsung_jabatan', 'atasan_langsung', 170
  UNION ALL SELECT 'pejabat_berwenang_nama_lengkap', 'pejabat_berwenang', 180
  UNION ALL SELECT 'pejabat_berwenang_nip', 'pejabat_berwenang', 190
  UNION ALL SELECT 'pejabat_berwenang_jabatan', 'pejabat_berwenang', 200
  UNION ALL SELECT 'petugas_cuti_nama', 'petugas_cuti', 210
) daftar ON 1 = 1
JOIN variabel_surat vs ON vs.kode = daftar.kode
LEFT JOIN peran_pegawai_surat pp ON pp.jenis_surat_id = j.id AND pp.kode = daftar.peran
WHERE NOT EXISTS (
  SELECT 1 FROM template_surat_variabel x
  WHERE x.template_surat_id = ts.id AND x.variabel_surat_id = vs.id
);

-- Verifikasi -- jalankan terpisah kalau mau lihat hasilnya:
-- SELECT COUNT(*) AS jumlah_variabel_terpasang
-- FROM template_surat_variabel tsv
-- JOIN template_surat ts ON ts.id = tsv.template_surat_id
-- JOIN jenis_surat j ON j.id = ts.jenis_surat_id AND j.kode = 'cuti'
-- WHERE ts.status_aktif = 1;
-- (harus menunjukkan 25 kalau semua berhasil tersambung)
