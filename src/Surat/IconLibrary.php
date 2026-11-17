<?php

namespace Aurat\Surat;

/**
 * Set ikon SVG bawaan untuk kartu jenis surat di dasbor. jenis_surat.icon hanya
 * menyimpan slug (bukan markup SVG) -- lihat db/004_ikon_jenis_surat.sql.
 * Geometri ikon dari Lucide v1.66.0 (https://lucide.dev, lisensi ISC); slug lama dipertahankan.
 */
class IconLibrary
{
    const DEFAULT_SLUG = 'dokumen';

    private static $icons = array(
        'dokumen' => array(
            'label' => 'Dokumen (umum)',
            'svg'   => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        ),
        'kalender' => array(
            'label' => 'Kalender',
            'svg'   => '<path d="M8 2v3"/><path d="M16 2v3"/><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/>',
        ),
        'centang_orang' => array(
            'label' => 'Orang Bercentang',
            'svg'   => '<path d="m16 11 2 2 4-4"/><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
        ),
        'papan_clip' => array(
            'label' => 'Papan Klip Bercentang',
            'svg'   => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
        ),
        'perisai' => array(
            'label' => 'Perisai',
            'svg'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
        ),
        'amplop' => array(
            'label' => 'Amplop',
            'svg'   => '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/>',
        ),
        'tas_kerja' => array(
            'label' => 'Tas Kerja',
            'svg'   => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
        ),
        'medali' => array(
            'label' => 'Medali',
            'svg'   => '<path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"/><circle cx="12" cy="8" r="6"/>',
        ),
        'kelompok' => array(
            'label' => 'Kelompok/Tim',
            'svg'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/>',
        ),
        'bendera' => array(
            'label' => 'Bendera',
            'svg'   => '<path d="M4 22V4a1 1 0 0 1 .4-.8A6 6 0 0 1 8 2c3 0 5 2 7.333 2q2 0 3.067-.8A1 1 0 0 1 20 4v10a1 1 0 0 1-.4.8A6 6 0 0 1 16 16c-3 0-5-2-8-2a6 6 0 0 0-4 1.528"/>',
        ),
        'lonceng' => array(
            'label' => 'Lonceng',
            'svg'   => '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
        ),
        'kartu_id' => array(
            'label' => 'Kartu Identitas',
            'svg'   => '<path d="M13 19a4 4 0 00-8 0"/><path d="M16 10h2"/><path d="M16 14h2"/><circle cx="9" cy="12" r="3"/><rect x="2" y="5" width="20" height="14" rx="2"/>',
        ),
    );

    /** @return array slug => label, buat opsi <select> di admin */
    public static function opsi()
    {
        $opsi = array();
        foreach (self::$icons as $slug => $ikon) {
            $opsi[$slug] = $ikon['label'];
        }
        return $opsi;
    }

    public static function ada($slug)
    {
        return isset(self::$icons[$slug]);
    }

    /** @return string markup <svg>, jatuh balik ke ikon default kalau slug tak dikenal */
    public static function render($slug)
    {
        $slug  = self::ada($slug) ? $slug : self::DEFAULT_SLUG;
        $inner = self::$icons[$slug]['svg'];

        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" '
            . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $inner . '</svg>';
    }
}
