<?php

namespace Aurat;

class Pengaturan
{
    /** @var array|null */
    private static $baris = null;

    /**
     * Baris tunggal pengaturan_aplikasi (id=1), di-cache per-request.
     * Nilai identitas (nama_aplikasi dkk) selalu ada karena migrasi 032
     * ngisi default - fallback string di sini cuma jaga-jaga migrasi belum
     * jalan.
     */
    private static function baris()
    {
        if (self::$baris === null) {
            self::$baris = Database::pdo()
                ->query('SELECT * FROM pengaturan_aplikasi WHERE id = 1')
                ->fetch();
            if (self::$baris === false) {
                self::$baris = array();
            }
        }
        return self::$baris;
    }

    public static function namaAplikasi()
    {
        $b = self::baris();
        return !empty($b['nama_aplikasi']) ? $b['nama_aplikasi'] : 'AURA';
    }

    public static function subjudulLogin()
    {
        $b = self::baris();
        return !empty($b['subjudul_login']) ? $b['subjudul_login'] : 'Aplikasi Untuk suRAt';
    }

    public static function namaInstansi()
    {
        $b = self::baris();
        return !empty($b['nama_instansi']) ? $b['nama_instansi'] : 'Sekretariat · Bagian Kepegawaian';
    }
}
