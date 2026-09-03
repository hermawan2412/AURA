-- Menambahkan variabel turunan "catatan_cuti" ke surat Cuti: isi bagian "V. Catatan
-- Cuti" sekarang menyesuaikan jenis_cuti yang dipilih --
--   Cuti Tahunan  -> tabel sisa cuti N / N-1 / N-2 (diisi manual oleh petugas)
--   selain itu    -> nama jenis cuti + satu baris "Keterangan"
-- Lihat Aurat\Formatter::catatanCutiKonten() (src/Formatter.php) dan whitelist
-- fungsi_pasca 'catatan_cuti_konten' di src/Surat/NilaiResolver.php.
--
-- PENTING (sama seperti migrasi 008): perubahan ini juga BUTUH src/Formatter.php dan
-- src/Surat/NilaiResolver.php diupload, bukan cuma docx + SQL ini.
--
-- Placeholder baru yang WAJIB sudah ada sbg ${...} literal di templates/cuti.docx
-- SEBELUM migrasi ini dijalankan: catatan_cuti (menggantikan tabel statis Tahun/
-- Sisa Cuti/Keterangan yang sebelumnya tidak py placeholder sama sekali).
--
-- Jalankan SETELAH db/001-009 (lewat SQLyog: File -> Open -> Execute All / F9).
-- Aman dijalankan ulang berkali-kali (idempoten, NOT EXISTS).

USE aurat;

INSERT INTO variabel_surat (kode, label, tipe_input, sumber, parameter_variabel, fungsi_pasca, wajib_default, urutan_tampil)
SELECT * FROM (SELECT
  'catatan_cuti' AS kode, 'Catatan Cuti (otomatis sesuai jenis cuti)' AS label,
  'text' AS tipe_input, 'turunan' AS sumber,
  '["jenis_cuti"]' AS parameter_variabel,
  'catatan_cuti_konten' AS fungsi_pasca,
  0 AS wajib_default, 75 AS urutan_tampil
) tmp WHERE NOT EXISTS (SELECT 1 FROM variabel_surat v WHERE v.kode = tmp.kode);

INSERT INTO template_surat_variabel (template_surat_id, variabel_surat_id, peran_pegawai_surat_id, urutan_tampil, terdeteksi_otomatis)
SELECT ts.id, vs.id, NULL, 75, 0
FROM template_surat ts
JOIN jenis_surat j ON j.id = ts.jenis_surat_id AND j.kode = 'cuti' AND ts.status_aktif = 1 AND ts.sub_jenis_surat_id IS NULL
JOIN variabel_surat vs ON vs.kode = 'catatan_cuti'
WHERE NOT EXISTS (
  SELECT 1 FROM template_surat_variabel x
  WHERE x.template_surat_id = ts.id AND x.variabel_surat_id = vs.id
);
