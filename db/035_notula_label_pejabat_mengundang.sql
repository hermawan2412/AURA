USE aurat;
-- Notula: label "Pejabat yang Punya Acara" -> "Pejabat yang Mengundang".
-- Cuma label; kode (pejabat_acara*) tetap biar template & nilai tersimpan gak putus. Idempotent.

UPDATE peran_pegawai_surat SET label = 'Pejabat yang Mengundang'
WHERE kode = 'pejabat_acara' AND label = 'Pejabat yang Punya Acara';

UPDATE variabel_surat SET label = 'Nama Lengkap (Pejabat yang Mengundang)'
WHERE kode = 'pejabat_acara_nama_lengkap' AND label = 'Nama Lengkap (Pejabat yang Punya Acara)';

UPDATE variabel_surat SET label = 'NIP (Pejabat yang Mengundang)'
WHERE kode = 'pejabat_acara_nip' AND label = 'NIP (Pejabat yang Punya Acara)';
