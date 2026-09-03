<?php

namespace Aurat;

class Formatter
{
    private static $namaBulan = array(
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    );

    /** @param string $ymd format Y-m-d dari <input type="date"> */
    public static function tanggalIndonesia($ymd)
    {
        if ($ymd === '' || $ymd === null) {
            return '';
        }
        $waktu = strtotime($ymd);
        if ($waktu === false) {
            return '';
        }
        $tanggal = (int) date('j', $waktu);
        $bulan   = self::$namaBulan[(int) date('n', $waktu)];
        $tahun   = date('Y', $waktu);

        return $tanggal . ' ' . $bulan . ' ' . $tahun;
    }

    private static $namaHari = array(
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    );

    private static $satuan = array(
        '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    );

    /** Angka ke kata Indonesia (0 - 999.999), cukup untuk tanggal/tahun. */
    public static function terbilang($n)
    {
        $n = (int) $n;
        if ($n < 0) {
            return 'minus ' . self::terbilang(-$n);
        }
        if ($n < 12) {
            return $n === 0 ? 'nol' : self::$satuan[$n];
        }
        if ($n < 20) {
            return self::terbilang($n - 10) . ' belas';
        }
        if ($n < 100) {
            $sisa = $n % 10;
            return trim(self::terbilang((int) ($n / 10)) . ' puluh ' . ($sisa ? self::terbilang($sisa) : ''));
        }
        if ($n < 200) {
            return trim('seratus ' . ($n - 100 ? self::terbilang($n - 100) : ''));
        }
        if ($n < 1000) {
            $sisa = $n % 100;
            return trim(self::terbilang((int) ($n / 100)) . ' ratus ' . ($sisa ? self::terbilang($sisa) : ''));
        }
        if ($n < 2000) {
            return trim('seribu ' . ($n - 1000 ? self::terbilang($n - 1000) : ''));
        }
        if ($n < 1000000) {
            $sisa = $n % 1000;
            return trim(self::terbilang((int) ($n / 1000)) . ' ribu ' . ($sisa ? self::terbilang($sisa) : ''));
        }
        $sisa = $n % 1000000;
        return trim(self::terbilang((int) ($n / 1000000)) . ' juta ' . ($sisa ? self::terbilang($sisa) : ''));
    }

    /** "Selasa tanggal Dua Bulan Juni Tahun Dua Ribu Dua Puluh Enam" */
    public static function tanggalNaratif($ymd)
    {
        if ($ymd === '' || $ymd === null) {
            return '';
        }
        $waktu = strtotime($ymd);
        if ($waktu === false) {
            return '';
        }
        $namaHari  = self::$namaHari[(int) date('N', $waktu)];
        $tanggal   = ucwords(self::terbilang((int) date('j', $waktu)));
        $namaBulan = self::$namaBulan[(int) date('n', $waktu)];
        $tahun     = ucwords(self::terbilang((int) date('Y', $waktu)));

        return $namaHari . ' tanggal ' . $tanggal . ' Bulan ' . $namaBulan . ' Tahun ' . $tahun;
    }

    public static function pangkatGolongan(array $pegawai)
    {
        $pangkat  = isset($pegawai['pangkat']) ? trim($pegawai['pangkat']) : '';
        $golongan = isset($pegawai['golongan_ruang']) ? trim($pegawai['golongan_ruang']) : '';
        if ($pangkat !== '' && $golongan !== '') {
            return $pangkat . ', ' . $golongan;
        }
        return $pangkat !== '' ? $pangkat : $golongan;
    }

    public static function namaBergelar(array $pegawai)
    {
        $bagian = array();
        if (!empty($pegawai['gelar_depan'])) {
            $bagian[] = $pegawai['gelar_depan'];
        }
        $bagian[] = $pegawai['nama_lengkap'];
        $nama = implode(' ', $bagian);
        if (!empty($pegawai['gelar_belakang'])) {
            $nama .= ', ' . $pegawai['gelar_belakang'];
        }
        return $nama;
    }

    /** "Nama Bergelar" + baris baru + "NIP. ..." — utk kolom tabel yang menggabungkan nama & NIP jadi satu sel (mis. tabel lampiran Surat Tugas Kelompok). */
    public static function namaDanNip(array $pegawai)
    {
        $nip = isset($pegawai['nip']) ? $pegawai['nip'] : '';

        return self::namaBergelar($pegawai) . "\nNIP. " . $nip;
    }

    /**
     * '' kalau $tanggalSelesaiYmd kosong, selain itu ' s.d {tanggal Indonesia}' — dipakai variabel
     * turunan (mis. sd_klausa Surat Perintah PLH) yang parameternya diwariskan MENTAH (belum lewat
     * fungsi_pasca variabel lain, lihat komentar NilaiResolver::nilaiMentah()), jadi pemformatan
     * tanggalnya harus dilakukan di sini, bukan mengandalkan klausaJikaAda() dgn nilai yg sudah jadi.
     */
    public static function sdTanggalKlausa($tanggalSelesaiYmd)
    {
        $tanggalSelesaiYmd = trim((string) $tanggalSelesaiYmd);
        if ($tanggalSelesaiYmd === '') {
            return '';
        }
        return ' s.d ' . self::tanggalIndonesia($tanggalSelesaiYmd);
    }

    /** '' jika $isi kosong, selain itu $awalan . $isi . $akhiran — untuk klausa opsional dalam dokumen. */
    public static function klausaJikaAda($isi, $awalan = '', $akhiran = '')
    {
        $isi = trim((string) $isi);
        if ($isi === '') {
            return '';
        }
        return $awalan . $isi . $akhiran;
    }

    /** Selisih hari inklusif antara dua tanggal Y-m-d (mulai & selesai sama = 1 hari). */
    public static function selisihHariInklusif($mulaiYmd, $selesaiYmd)
    {
        $mulai   = strtotime((string) $mulaiYmd);
        $selesai = strtotime((string) $selesaiYmd);
        if ($mulai === false || $selesai === false) {
            return 0;
        }
        $hari = (int) (($selesai - $mulai) / 86400) + 1;
        return $hari > 0 ? $hari : 0;
    }

    /** "Jabatan — Unit Kerja", trim rapi kalau salah satu kosong — dipindah apa adanya dari surat/surat_tugas.php lama. */
    public static function jabatanSatuanKerja(array $pegawai)
    {
        $jabatan = isset($pegawai['jabatan']) ? $pegawai['jabatan'] : '';
        $unitKerja = isset($pegawai['unit_kerja']) ? $pegawai['unit_kerja'] : '';
        return trim($jabatan . ' — ' . $unitKerja, ' —');
    }

    /** Kalimat narasi SPMT (+ klausa tunjangan opsional) — dipindah apa adanya dari surat/pernyataan_melaksanakan_tugas.php lama. */
    public static function narasiPelaksanaanTugas($dasarSkNomor, $dasarSkTanggalYmd, $tmtYmd, $instansi, $besaranTunjanganRaw)
    {
        $angkaTunjangan = (int) preg_replace('/[^0-9]/', '', (string) $besaranTunjanganRaw);
        if ($angkaTunjangan > 0) {
            $formatRupiah = 'Rp' . number_format($angkaTunjangan, 0, ',', '.') . ',-';
            $terbilangRupiah = ucwords(self::terbilang($angkaTunjangan)) . ' Rupiah';
            $klausaTunjangan = ' dan berdasarkan Peraturan Presiden RI nomor 24 tahun 2007 yang bersangkutan diberi tunjangan umum sebesar '
                . $formatRupiah . ' (' . $terbilangRupiah . ').';
        } else {
            $klausaTunjangan = '.';
        }

        return 'Berdasarkan Petikan Keputusan Sekretaris Mahkamah Agung Republik Indonesia Nomor: '
            . $dasarSkNomor . ' tanggal ' . self::tanggalIndonesia($dasarSkTanggalYmd)
            . ' terhitung mulai tanggal ' . self::tanggalIndonesia($tmtYmd)
            . ' telah nyata melaksanakan tugasnya sebagai Pegawai Negeri Sipil pada ' . $instansi
            . $klausaTunjangan;
    }

    /**
     * "X Tahun Y Bulan" masa kerja, dihitung dari kolom TMT pegawai (diisi manual di
     * halaman Data Pegawai) terhadap tanggal referensi (biasanya tanggal_surat, bukan
     * tanggal hari ini, supaya angkanya tetap konsisten kalau surat dibuat/dicetak
     * belakangan). '' kalau TMT belum diisi atau tidak valid -- lebih baik kosong
     * daripada menampilkan angka yang mungkin salah di dokumen resmi.
     *
     * CATATAN: sebelumnya dihitung dengan membaca posisi digit tahun+bulan yang
     * tertanam di NIP PNS 18-digit (posisi 9-12 & 13-14, setelah 8 digit tanggal
     * lahir) -- ternyata NIP PPPK TIDAK mengikuti struktur yang sama (posisi itu
     * bukan bulan yang valid), jadi hasilnya kosong utk pegawai PPPK. Kolom TMT
     * eksplisit ini berlaku sama utk PNS maupun PPPK, tidak bergantung format NIP.
     */
    public static function masaKerjaDariTmt($tmtYmd, $tanggalReferensiYmd)
    {
        $tmtYmd = trim((string) $tmtYmd);
        if ($tmtYmd === '') {
            return '';
        }
        $waktuTmt = strtotime($tmtYmd);
        $waktuReferensi = strtotime((string) $tanggalReferensiYmd);
        if ($waktuTmt === false || $waktuReferensi === false) {
            return '';
        }

        $tmt = new \DateTime(date('Y-m-d', $waktuTmt));
        $referensi = new \DateTime(date('Y-m-d', $waktuReferensi));
        if ($referensi < $tmt) {
            return '0 Tahun 0 Bulan';
        }

        $selisih = $tmt->diff($referensi);
        return $selisih->y . ' Tahun ' . $selisih->m . ' Bulan';
    }

    /** Kalimat narasi penunjukan Plh — dipindah apa adanya dari surat/pelaksana_harian.php lama. */
    public static function narasiPenunjukanPlh($jabatan, $tanggalPelaksanaanYmd, $alasan, $dasarSuratTugas)
    {
        $alasanFinal = trim((string) $alasan) !== '' ? trim((string) $alasan) : 'melaksanakan dinas';
        $dasarSuratTugas = trim((string) $dasarSuratTugas);
        $klausaDasar = $dasarSuratTugas !== '' ? ' berdasarkan surat tugas nomor ' . $dasarSuratTugas . '.' : '.';

        return 'Ditunjuk sebagai Pelaksana tugas harian (Plh) ' . $jabatan
            . ' pada tanggal ' . self::tanggalIndonesia($tanggalPelaksanaanYmd)
            . ' karena ' . $jabatan . ' ' . $alasanFinal . $klausaDasar;
    }
}
