-- Menghapus jenis surat Cuti — sudah diakomodir sepenuhnya oleh aplikasi LUCU
-- (Aplikasi Untuk Cuti, ~/repo/lucu/LUCU/), termasuk cetak formulir cuti sendiri
-- (docx via PhpWord TemplateProcessor, sama polanya dgn DocxGenerator di sini).
-- AURAT tidak perlu lagi menangani surat cuti sama sekali.
--
-- Aman dijalankan berulang (DELETE, bukan CREATE) — kalau jenis_surat 'cuti'
-- sudah tidak ada, kedua pernyataan di bawah cukup menghapus 0 baris.

USE aurat;

-- Hapus dulu pivot template_surat_variabel milik Cuti secara eksplisit.
-- Wajib dilakukan SEBELUM DELETE jenis_surat di bawah: fk_tsv_peran
-- (template_surat_variabel.peran_pegawai_surat_id -> peran_pegawai_surat.id)
-- TIDAK ON DELETE CASCADE, jadi CASCADE dari jenis_surat->peran_pegawai_surat
-- gagal (error 1451) selama pivot row yang menunjuk peran itu masih ada —
-- meski pivot row itu sendiri juga akan ikut CASCADE lewat template_surat,
-- MySQL tidak menjamin urutan itu selesai duluan dalam satu DELETE.
DELETE tsv FROM template_surat_variabel tsv
JOIN template_surat ts ON ts.id = tsv.template_surat_id
JOIN jenis_surat js ON js.id = ts.jenis_surat_id
WHERE js.kode = 'cuti';

-- Baru sekarang aman: CASCADE bawaan skema (lihat db/002_generic_surat_engine.sql)
-- otomatis membereskan sub_jenis_surat, peran_pegawai_surat, template_surat,
-- dan blok_tabel_surat (+ kolomnya) yang terkait jenis surat ini. Berkas fisik
-- .docx-nya (templates/uploaded/..., dirujuk lewat template_surat.nama_berkas
-- yang baru saja ikut terhapus) TIDAK ikut kehapus otomatis oleh SQL ini —
-- hapus manual di server kalau perlu, lihat kolom nama_berkas dari
-- cadangan/backup sebelum migrasi ini dijalankan kalau butuh tahu nama filenya.
DELETE FROM jenis_surat WHERE kode = 'cuti';

-- Bersihkan variabel_surat yang jadi yatim (tidak lagi dipakai template
-- manapun) akibat penghapusan di atas. Variabel yang dipakai bareng jenis
-- surat lain (mis. nomor_surat, tanggal_surat, alasan — dicek lewat
-- template_surat_variabel) TIDAK ikut terhapus karena pivot row-nya di jenis
-- surat lain masih ada.
DELETE FROM variabel_surat
WHERE id NOT IN (SELECT DISTINCT variabel_surat_id FROM template_surat_variabel);
