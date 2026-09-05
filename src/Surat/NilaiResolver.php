<?php

namespace Aurat\Surat;

use RuntimeException;

/**
 * Meresolusi definisi variabel_surat (dari VariabelRepository::variabelUntukTemplate())
 * menjadi teks siap pakai sebagai $nilai untuk Aurat\DocxGenerator::generateDanUnduh().
 *
 * Mendukung rantai sub-variabel (sumber='turunan', variabel merujuk variabel lain lewat
 * parameter_variabel) dengan deteksi siklus, dan HANYA memanggil fungsi Formatter lewat
 * whitelist tetap di bawah — bukan nama fungsi bebas dari kolom fungsi_pasca di database
 * (fungsi_pasca cuma dipakai sebagai KUNCI ke whitelist ini, tidak pernah di-call_user_func
 * langsung dari string kolom itu sendiri).
 */
class NilaiResolver
{
    private static $FUNGSI = array(
        'tanggal_indonesia'     => array('Aurat\Formatter', 'tanggalIndonesia'),
        'tanggal_naratif'       => array('Aurat\Formatter', 'tanggalNaratif'),
        'terbilang'             => array('Aurat\Formatter', 'terbilang'),
        'nama_bergelar'         => array('Aurat\Formatter', 'namaBergelar'),
        'nama_dan_nip'          => array('Aurat\Formatter', 'namaDanNip'),
        'sd_tanggal_klausa'     => array('Aurat\Formatter', 'sdTanggalKlausa'),
        'pangkat_golongan'      => array('Aurat\Formatter', 'pangkatGolongan'),
        'klausa_jika_ada'       => array('Aurat\Formatter', 'klausaJikaAda'),
        'selisih_hari_inklusif' => array('Aurat\Formatter', 'selisihHariInklusif'),
        'masa_kerja_dari_tmt'   => array('Aurat\Formatter', 'masaKerjaDariTmt'),
        'narasi_penunjukan_plh' => array('Aurat\Formatter', 'narasiPenunjukanPlh'),
        'narasi_pelaksanaan_tugas' => array('Aurat\Formatter', 'narasiPelaksanaanTugas'),
        'jabatan_satuan_kerja'  => array('Aurat\Formatter', 'jabatanSatuanKerja'),
        'nomor_surat_otomatis'  => array('Aurat\Formatter', 'nomorSuratOtomatis'),
    );

    /** Kolom pegawai yang boleh dipakai sebagai field_pegawai — whitelist tetap, bukan dari input. */
    private static $KOLOM_PEGAWAI = array(
        'nip', 'nama_lengkap', 'gelar_depan', 'gelar_belakang',
        'pangkat', 'golongan_ruang', 'jabatan', 'unit_kerja', 'tmt',
    );

    /** @var array kode => baris variabel_surat (+peran_kode dari pivot, lihat VariabelRepository) */
    private $definisi = array();

    /** @var array kode => nilai mentah dari $_POST */
    private $inputManual;

    /** @var array peran_kode => baris pegawai (assoc) */
    private $pegawaiTerpilih;

    /** @var array kode_sistem => nilai, mis. ['tanggal_sekarang' => '2026-08-02'] */
    private $konteksSistem;

    /** @var array kode => nilai mentah (memo) */
    private $memo = array();

    /** @var string[] kode yang sedang diresolusi — dipakai deteksi siklus */
    private $stackAktif = array();

    public function __construct(array $variabelList, array $inputManual, array $pegawaiTerpilih, array $konteksSistem = array())
    {
        foreach ($variabelList as $v) {
            $this->definisi[$v['kode']] = $v;
        }
        $this->inputManual     = $inputManual;
        $this->pegawaiTerpilih = $pegawaiTerpilih;
        $this->konteksSistem   = $konteksSistem;
    }

    /** Daftar kunci fungsi_pasca yang valid (dipakai UI admin utk dropdown) — satu-satunya sumber kebenaran, lihat $FUNGSI di atas. */
    public static function daftarFungsiPasca()
    {
        return array_keys(self::$FUNGSI);
    }

    /**
     * Whitelist kolom pegawai yang boleh dipakai sbg field_pegawai — dipakai di sini DAN
     * oleh controller (surat/index.php) saat membangun kolom blok_tabel_surat_kolom, supaya
     * satu-satunya sumber kebenaran whitelist ini tidak dobel-tulis di dua tempat.
     */
    public static function kolomPegawaiDiizinkan()
    {
        return self::$KOLOM_PEGAWAI;
    }

    /** @return array kode => nilai akhir (string), siap dipakai sbg $nilai di DocxGenerator::generateDanUnduh() */
    public function resolveSemua()
    {
        $hasil = array();
        foreach ($this->definisi as $kode => $def) {
            $hasil[$kode] = $this->nilaiAkhir($kode);
        }
        return $hasil;
    }

    /** Nilai satu variabel, sudah lewat fungsi_pasca jika ada. */
    public function nilaiAkhir($kode)
    {
        $def    = $this->cariDefinisi($kode);
        $mentah = $this->nilaiMentah($kode);

        if (empty($def['fungsi_pasca'])) {
            if (is_array($mentah)) {
                throw new RuntimeException(
                    'Variabel "' . $kode . '" menghasilkan data majemuk (baris pegawai / daftar parameter turunan) ' .
                    'tapi tidak ada fungsi_pasca yang dikonfigurasi untuk mengubahnya jadi teks.'
                );
            }
            return (string) $mentah;
        }

        // sumber='turunan' -> $mentah adalah DAFTAR nilai parameter, di-spread sbg argumen posisional.
        // sumber lain (termasuk 'pegawai' dgn field_pegawai=NULL, berupa baris asosiatif) -> dikirim
        // sbg SATU argumen array, supaya cocok dgn signature Formatter::namaBergelar(array $pegawai) dkk.
        // (array($mentah) membungkusnya dgn key integer 0, jadi call_user_func_array tidak keliru
        // menganggap key string di dalam baris pegawai sbg named argument.)
        $argumen = ($def['sumber'] === 'turunan' && is_array($mentah)) ? $mentah : array($mentah);

        if (!empty($def['fungsi_parameter_1'])) {
            $argumen[] = $def['fungsi_parameter_1'];
        }
        if (!empty($def['fungsi_parameter_2'])) {
            $argumen[] = $def['fungsi_parameter_2'];
        }

        return self::panggilFungsiPasca($def['fungsi_pasca'], $argumen);
    }

    /**
     * Memanggil fungsi HANYA lewat whitelist $FUNGSI tetap (bukan call_user_func dari
     * string bebas) — dipakai internal di sini, DAN oleh controller (surat/index.php)
     * saat kolom blok_tabel_surat_kolom bersumber 'pegawai_fungsi' (mis. kolom "Nama"
     * yg butuh nama_bergelar, bukan satu field mentah) — satu-satunya titik pemanggilan
     * fungsi whitelist di seluruh aplikasi.
     */
    public static function panggilFungsiPasca($kunci, array $argumen)
    {
        if (!isset(self::$FUNGSI[$kunci])) {
            throw new RuntimeException('fungsi_pasca tidak dikenal: ' . $kunci);
        }
        return (string) call_user_func_array(self::$FUNGSI[$kunci], $argumen);
    }

    /**
     * Nilai SEBELUM fungsi_pasca diterapkan. Untuk sumber='turunan', argumen yang
     * diwariskan ke variabel ini adalah nilai mentah tiap parameter (bukan nilai
     * tampilan yang sudah diformat) — supaya tidak ambigu, fungsi_pasca adalah
     * langkah pemformatan akhir untuk placeholder itu sendiri, bukan bagian dari
     * nilai yang diwariskan ke variabel lain.
     */
    private function nilaiMentah($kode)
    {
        if (array_key_exists($kode, $this->memo)) {
            return $this->memo[$kode];
        }
        if (in_array($kode, $this->stackAktif, true)) {
            throw new RuntimeException(
                'Rantai variabel berputar (siklus): ' . implode(' -> ', $this->stackAktif) . ' -> ' . $kode
            );
        }
        $this->stackAktif[] = $kode;

        $def = $this->cariDefinisi($kode);

        switch ($def['sumber']) {
            case 'manual':
                $nilai = (isset($this->inputManual[$kode]) && $this->inputManual[$kode] !== '')
                    ? $this->inputManual[$kode]
                    : (string) $def['placeholder_default'];
                break;

            case 'pegawai':
                $peranKode = $this->peranKodeUntuk($def);
                if (!isset($this->pegawaiTerpilih[$peranKode])) {
                    throw new RuntimeException('Pegawai untuk peran "' . $peranKode . '" belum dipilih.');
                }
                $baris = $this->pegawaiTerpilih[$peranKode];

                if ($def['field_pegawai'] !== null && $def['field_pegawai'] !== '') {
                    if (!in_array($def['field_pegawai'], self::$KOLOM_PEGAWAI, true)) {
                        throw new RuntimeException('Kolom pegawai tidak diizinkan: ' . $def['field_pegawai']);
                    }
                    $nilai = isset($baris[$def['field_pegawai']]) ? $baris[$def['field_pegawai']] : '';
                } else {
                    // baris penuh — dipakai fungsi_pasca yg butuh beberapa kolom (nama_bergelar, pangkat_golongan)
                    $nilai = $baris;
                }
                break;

            case 'turunan':
                $params = array();
                $kodeParamList = json_decode((string) $def['parameter_variabel'], true);
                if (!is_array($kodeParamList)) {
                    $kodeParamList = array();
                }
                foreach ($kodeParamList as $kodeParam) {
                    $params[] = $this->nilaiMentah($kodeParam); // rekursi — di sinilah siklus terdeteksi
                }
                $nilai = $params;
                break;

            case 'sistem':
                $nilai = isset($this->konteksSistem[$def['sistem_kode']]) ? $this->konteksSistem[$def['sistem_kode']] : '';
                break;

            default:
                throw new RuntimeException('sumber variabel tidak dikenal: ' . $def['sumber']);
        }

        array_pop($this->stackAktif);
        $this->memo[$kode] = $nilai;

        return $nilai;
    }

    private function cariDefinisi($kode)
    {
        if (!isset($this->definisi[$kode])) {
            throw new RuntimeException('Variabel "' . $kode . '" dipakai sbg parameter tapi tidak terpasang ke template ini.');
        }
        return $this->definisi[$kode];
    }

    private function peranKodeUntuk(array $def)
    {
        if (empty($def['peran_kode'])) {
            throw new RuntimeException('Variabel "' . $def['kode'] . '" bersumber pegawai tapi tidak terhubung ke peran manapun.');
        }
        return $def['peran_kode'];
    }
}
