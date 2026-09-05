-- Koreksi db/028: NULL-in fungsi_pasca di kode_penandatangan_* ternyata
-- bikin crash BEDA (bukan "Array to string conversion" lagi, tapi
-- "menghasilkan data majemuk ... tidak ada fungsi_pasca") - soalnya
-- NilaiResolver::resolveSemua() manggil nilaiAkhir() ATAS SETIAP variabel
-- yang terpasang ke template SECARA MANDIRI (bukan cuma yang dipakai
-- sbg parameter turunan lain) buat ngisi array $nilai penuh - variabel ini
-- terpasang eksplisit ke template (perlu, biar bisa jadi parameter
-- nomor_lengkap_*), jadi nilaiAkhir()-nya ikut kepanggil sendiri juga,
-- dan tanpa fungsi_pasca dia nolak nilai array (baris pegawai mentah).
--
-- Pasang lagi fungsi_pasca='kode_penandatangan_dari_jabatan' (dites lokal,
-- konfirmasi 2 hal ini gak saling ganggu: nomorSuratOtomatis() baca
-- nilaiMentah() yg SELALU mentah terlepas fungsi_pasca-nya apa, jadi
-- fungsi_pasca di sini cuma buat nyegah crash pas nilaiAkhir()
-- dipanggil MANDIRI, gak pernah kepakai buat parameter turunan).

USE aurat;

UPDATE variabel_surat SET fungsi_pasca = 'kode_penandatangan_dari_jabatan'
  WHERE kode IN ('kode_penandatangan_penetap', 'kode_penandatangan_menyatakan',
                 'kode_penandatangan_pengambil_sumpah', 'kode_penandatangan_penandatangan');
