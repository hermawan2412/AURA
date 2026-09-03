<?php

namespace Aurat;

use RuntimeException;

/**
 * Pemuat konfigurasi jenis surat dari config/jenis_surat/{kode}.json.
 * Jenis surat baru cukup ditambah lewat file JSON baru di folder itu,
 * tanpa mengubah kode inti (lihat rangkuman kebutuhan §6).
 */
class JenisSurat
{
    private static $cache = array();

    public static function muat($kode)
    {
        if (isset(self::$cache[$kode])) {
            return self::$cache[$kode];
        }

        $path = __DIR__ . '/../config/jenis_surat/' . basename($kode) . '.json';

        if (!is_file($path)) {
            throw new RuntimeException('Konfigurasi jenis surat "' . $kode . '" tidak ditemukan.');
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if ($data === null) {
            throw new RuntimeException('Konfigurasi jenis surat "' . $kode . '" tidak valid (JSON error).');
        }

        self::$cache[$kode] = $data;

        return $data;
    }

    public static function semua()
    {
        $dir = __DIR__ . '/../config/jenis_surat/';
        $hasil = array();

        foreach (glob($dir . '*.json') as $file) {
            $kode = basename($file, '.json');
            $hasil[] = self::muat($kode);
        }

        return $hasil;
    }
}
