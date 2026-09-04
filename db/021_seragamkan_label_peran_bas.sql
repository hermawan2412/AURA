-- Seragamkan konvensi label field ber-peran di Berita Acara Pengambilan
-- Sumpah - dulu "Field — Peran" (satu-satunya yang beda sendiri), sekarang
-- "Field (Peran)" sama kayak semua jenis surat lain (sk, surat_perintah_plh,
-- izin_keluar_kantor, dll). Kosmetik doang, gak nyentuh docx/logic.

USE aurat;

UPDATE variabel_surat SET label = 'Nama Lengkap (Pengambil Sumpah)' WHERE kode = 'pengambil_sumpah_nama_lengkap';
UPDATE variabel_surat SET label = 'NIP (Pengambil Sumpah)' WHERE kode = 'pengambil_sumpah_nip';
UPDATE variabel_surat SET label = 'Pangkat/Golongan (Pengambil Sumpah)' WHERE kode = 'pengambil_sumpah_pangkat_golongan';
UPDATE variabel_surat SET label = 'Jabatan (Pengambil Sumpah)' WHERE kode = 'pengambil_sumpah_jabatan';
UPDATE variabel_surat SET label = 'Nama Lengkap (Disumpah)' WHERE kode = 'disumpah_nama_lengkap';
UPDATE variabel_surat SET label = 'NIP (Disumpah)' WHERE kode = 'disumpah_nip';
UPDATE variabel_surat SET label = 'Pangkat/Golongan (Disumpah)' WHERE kode = 'disumpah_pangkat_golongan';
UPDATE variabel_surat SET label = 'Nama Lengkap (Saksi 1)' WHERE kode = 'saksi_1_nama_lengkap';
UPDATE variabel_surat SET label = 'NIP (Saksi 1)' WHERE kode = 'saksi_1_nip';
UPDATE variabel_surat SET label = 'Pangkat/Golongan (Saksi 1)' WHERE kode = 'saksi_1_pangkat_golongan';
UPDATE variabel_surat SET label = 'Nama Lengkap (Saksi 2)' WHERE kode = 'saksi_2_nama_lengkap';
UPDATE variabel_surat SET label = 'NIP (Saksi 2)' WHERE kode = 'saksi_2_nip';
UPDATE variabel_surat SET label = 'Pangkat/Golongan (Saksi 2)' WHERE kode = 'saksi_2_pangkat_golongan';
UPDATE variabel_surat SET label = 'Nama Lengkap (Rohaniawan Pendamping)' WHERE kode = 'rohaniawan_nama_lengkap';
UPDATE variabel_surat SET label = 'NIP (Rohaniawan Pendamping)' WHERE kode = 'rohaniawan_nip';
UPDATE variabel_surat SET label = 'Pangkat/Golongan (Rohaniawan Pendamping)' WHERE kode = 'rohaniawan_pangkat_golongan';
