-- Perbaikan db/027: turunan (nomor_lengkap_*) di NilaiResolver mewarisi
-- nilai MENTAH parameternya (sebelum fungsi_pasca diterapkan) - fungsi_
-- pasca 'kode_penandatangan_dari_jabatan' yang dipasang ke kode_
-- penandatangan_* SEBELUMNYA gak pernah kepanggil pas dipakai sbg
-- parameter nomor_lengkap_* (Warning: Array to string conversion,
-- ketauan pas tes generate beneran). Sama gotcha yg sudah pernah kejadian
-- di project ini (sd_tanggal_klausa/nama_dan_nip, lihat catatan lama).
--
-- Fix: Formatter::nomorSuratOtomatis() sendiri yang ngerjain resolusi
-- jabatan->kode (nerima baris pegawai mentah ATAU teks jabatan langsung,
-- deteksi otomatis via is_array()) - variabel kode_penandatangan_* jadi
-- cuma passthrough baris pegawai MENTAH (sumber=pegawai, field_pegawai=
-- NULL, TANPA fungsi_pasca).
--
-- surat_perintah_plh malah lebih simpel lagi: hapus kode_penandatangan_plh
-- (turunan yg gak perlu, jabatan_diplh SUDAH teks langsung, is_array()
-- di Formatter otomatis kirim ke jalur teks) - nomor_lengkap_plh langsung
-- referensi jabatan_diplh di parameter_variabel-nya.

USE aurat;

UPDATE variabel_surat SET fungsi_pasca = NULL
  WHERE kode IN ('kode_penandatangan_penetap', 'kode_penandatangan_menyatakan',
                 'kode_penandatangan_pengambil_sumpah', 'kode_penandatangan_penandatangan');

UPDATE variabel_surat SET parameter_variabel = '["nomor_urut","tanggal_surat","jabatan_diplh","kode_satker_surat","kode_klasifikasi_surat"]'
  WHERE kode = 'nomor_lengkap_plh';

DELETE FROM variabel_surat WHERE kode = 'kode_penandatangan_plh';
