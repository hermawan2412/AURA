<?php

namespace Aurat\Izin;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Aturan waktu izin (rancangan bagian 7). Murni - jadwal, libur, dan "sekarang"
 * dioper dari luar supaya bisa dites (tests/izin-waktu.php).
 */
class Waktu
{
    /**
     * @param string $jenis 'keluar_sementara' | 'pulang_awal'
     * @param bool   $mulaiSekarang true = tanggal & jam mulai diambil dari $sekarang (server), input diabaikan
     * @param string $tanggal 'Y-m-d' (lokal kantor)
     * @param string $jamMulai 'H:i'
     * @param string|null $jamKembali 'H:i'
     * @param array  $jadwal ISO hari (1-7) => array('mulai'=>'H:i','selesai'=>'H:i')
     * @param string[] $libur daftar 'Y-m-d'
     * @return array array('tanggal','jam_mulai','jam_kembali','jam_akhir') format H:i:s, atau array('error'=>...)
     */
    public static function validasi($jenis, $mulaiSekarang, $tanggal, $jamMulai, $jamKembali, array $jadwal, array $libur, $zona, DateTimeImmutable $sekarang)
    {
        if (!in_array($jenis, array('keluar_sementara', 'pulang_awal'), true)) {
            return array('error' => 'Jenis izin tidak dikenal.');
        }
        $tz = new DateTimeZone($zona);
        $kini = $sekarang->setTimezone($tz);

        if ($mulaiSekarang) {
            $tanggal = $kini->format('Y-m-d');
            $jamMulai = $kini->format('H:i');
        } else {
            $cekTanggal = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $tanggal, $tz);
            if (!$cekTanggal || $cekTanggal->format('Y-m-d') !== $tanggal) {
                return array('error' => 'Tanggal izin tidak valid.');
            }
            if (!self::jamValid($jamMulai)) {
                return array('error' => 'Jam mulai tidak valid.');
            }
            if ($tanggal . ' ' . $jamMulai < $kini->format('Y-m-d H:i')) {
                return array('error' => 'Jam mulai sudah lewat. Pilih "Mulai sekarang" bila izin dimulai saat ini.');
            }
        }

        $hari = (int) DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal, $tz)->format('N');
        if (!isset($jadwal[$hari]) || in_array($tanggal, $libur, true)) {
            return array('error' => 'Tanggal ' . $tanggal . ' bukan hari kerja.');
        }
        $masuk = $jadwal[$hari]['mulai'];
        $pulang = $jadwal[$hari]['selesai'];
        if ($jamMulai < $masuk || $jamMulai >= $pulang) {
            return array('error' => 'Jam mulai harus di dalam jam kerja (' . $masuk . '–' . $pulang . ').');
        }

        if ($jenis === 'pulang_awal') {
            $jamKembali = null;
            $jamAkhir = $pulang;
        } else {
            if (!self::jamValid($jamKembali)) {
                return array('error' => 'Rencana jam kembali wajib diisi untuk izin keluar sementara.');
            }
            if ($jamKembali <= $jamMulai) {
                return array('error' => 'Jam kembali harus setelah jam mulai.');
            }
            if ($jamKembali > $pulang) {
                return array('error' => 'Jam kembali melewati jam pulang (' . $pulang . '). Gunakan jenis "Pulang awal".');
            }
            $jamAkhir = $jamKembali;
        }

        return array(
            'tanggal' => $tanggal,
            'jam_mulai' => $jamMulai . ':00',
            'jam_kembali' => $jamKembali === null ? null : $jamKembali . ':00',
            'jam_akhir' => $jamAkhir . ':00',
        );
    }

    /** Persetujuan baru hanya boleh sampai akhir tanggal izin (kebijakan nonretrospektif). */
    public static function masihBolehDiputus($tanggal, $zona, DateTimeImmutable $sekarang)
    {
        return $tanggal >= $sekarang->setTimezone(new DateTimeZone($zona))->format('Y-m-d');
    }

    public static function jadwalDariJson($json)
    {
        $data = json_decode((string) $json, true);
        $jadwal = array();
        foreach (is_array($data) ? $data : array() as $hari => $j) {
            if (isset($j['mulai'], $j['selesai']) && self::jamValid($j['mulai']) && self::jamValid($j['selesai'])) {
                $jadwal[(int) $hari] = array('mulai' => $j['mulai'], 'selesai' => $j['selesai']);
            }
        }
        return $jadwal;
    }

    private static function jamValid($jam)
    {
        return is_string($jam) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jam) === 1;
    }
}
