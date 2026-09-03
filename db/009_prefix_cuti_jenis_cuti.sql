-- Opsi dropdown "Jenis Cuti" diubah supaya masing-masing sudah mengandung kata
-- "Cuti" di depannya, sehingga bagian "II. JENIS CUTI YANG DIAMBIL" di dokumen
-- terisi "Cuti Tahunan", "Cuti Sakit", dst -- bukan cuma "Tahunan"/"Sakit" polos.
-- Tidak perlu ubah kode PHP atau templates/cuti.docx -- placeholder ${jenis_cuti}
-- yang sudah ada langsung menampilkan apa pun isi opsi_pilihan ini, dan opsi ini
-- dipakai persis sebagai teks di <option> pada dropdown-nya juga (surat/index.php).
-- Aman dijalankan ulang berkali-kali (UPDATE dengan nilai sama, bukan INSERT).
--
-- Jalankan SETELAH db/001-008 (lewat SQLyog: File -> Open -> Execute All / F9).

USE aurat;

UPDATE variabel_surat
SET opsi_pilihan = '["Cuti Tahunan","Cuti Sakit","Cuti Melahirkan","Cuti Besar","Cuti Alasan Penting","Cuti Luar Tanggungan Negara"]'
WHERE kode = 'jenis_cuti';
