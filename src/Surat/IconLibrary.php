<?php

namespace Aurat\Surat;

/**
 * Set ikon SVG bawaan untuk kartu jenis surat di dasbor. jenis_surat.icon hanya
 * menyimpan slug (bukan markup SVG) -- lihat db/004_ikon_jenis_surat.sql.
 */
class IconLibrary
{
    const DEFAULT_SLUG = 'dokumen';

    private static $icons = array(
        'dokumen' => array(
            'label' => 'Dokumen (umum)',
            'svg'   => '<rect x="5" y="3" width="14" height="18" rx="2"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="13" y2="16"/>',
        ),
        'kalender' => array(
            'label' => 'Kalender',
            'svg'   => '<rect x="3" y="5" width="18" height="16" rx="2"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/><line x1="3" y1="10" x2="21" y2="10"/>',
        ),
        'centang_orang' => array(
            'label' => 'Orang Bercentang',
            'svg'   => '<circle cx="9" cy="8" r="3.5"/><path d="M3 21v-1a6 6 0 0 1 6-6h0a6 6 0 0 1 6 6v1"/><polyline points="16 11 18.5 13.5 22 9"/>',
        ),
        'papan_clip' => array(
            'label' => 'Papan Klip Bercentang',
            'svg'   => '<rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><polyline points="9 13 11 15 15 10"/>',
        ),
        'perisai' => array(
            'label' => 'Perisai',
            'svg'   => '<path d="M12 3 19 6 19 12C19 16.5 16 19.5 12 21 8 19.5 5 16.5 5 12L5 6Z"/>',
        ),
        'amplop' => array(
            'label' => 'Amplop',
            'svg'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 14 21 7"/>',
        ),
        'tas_kerja' => array(
            'label' => 'Tas Kerja',
            'svg'   => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="3" y1="13" x2="21" y2="13"/>',
        ),
        'medali' => array(
            'label' => 'Medali',
            'svg'   => '<circle cx="12" cy="8" r="5"/><path d="M8.5 12.5 7 21 12 18 17 21 15.5 12.5"/>',
        ),
        'kelompok' => array(
            'label' => 'Kelompok/Tim',
            'svg'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 21v-1a6 6 0 0 1 6-6h1a6 6 0 0 1 6 6v1"/><circle cx="18" cy="7.5" r="2.5"/><path d="M16.3 12a5 5 0 0 1 4.7 5v1"/>',
        ),
        'bendera' => array(
            'label' => 'Bendera',
            'svg'   => '<line x1="5" y1="3" x2="5" y2="21"/><path d="M5 4h13l-3 4.5 3 4.5H5"/>',
        ),
        'lonceng' => array(
            'label' => 'Lonceng',
            'svg'   => '<path d="M6 10a6 6 0 0 1 12 0v4l2 3H4l2-3Z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        ),
        'kartu_id' => array(
            'label' => 'Kartu Identitas',
            'svg'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="12" r="2"/><line x1="14" y1="10" x2="18" y2="10"/><line x1="14" y1="14" x2="18" y2="14"/>',
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
            . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $inner . '</svg>';
    }
}
