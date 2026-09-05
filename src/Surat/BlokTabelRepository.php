<?php

namespace Aurat\Surat;

use Aurat\Database;

/**
 * Blok tabel berulang (mis. daftar anggota Tim Kerja di SK) + kolomnya.
 * Menerapkan pola "override_per_sub_jenis" yang sebelumnya ada di JSON
 * tabel_pegawai: baris default (sub_jenis_surat_id NULL) dipakai kecuali
 * ada baris override dengan kode blok yang sama untuk sub_jenis terpilih.
 */
class BlokTabelRepository
{
    /**
     * @param int      $jenisSuratId
     * @param int|null $subJenisSuratId
     * @return array daftar blok (masing-masing + key 'kolom' berisi blok_tabel_surat_kolom-nya)
     */
    public static function blokUntuk($jenisSuratId, $subJenisSuratId = null)
    {
        $pdo = Database::pdo();

        $stmtDefault = $pdo->prepare(
            'SELECT * FROM blok_tabel_surat WHERE jenis_surat_id = ? AND sub_jenis_surat_id IS NULL
             ORDER BY urutan_tampil, label'
        );
        $stmtDefault->execute(array($jenisSuratId));
        $default = $stmtDefault->fetchAll();

        $override = array();
        if ($subJenisSuratId !== null) {
            $stmtOverride = $pdo->prepare(
                'SELECT * FROM blok_tabel_surat WHERE jenis_surat_id = ? AND sub_jenis_surat_id = ?
                 ORDER BY urutan_tampil, label'
            );
            $stmtOverride->execute(array($jenisSuratId, $subJenisSuratId));
            foreach ($stmtOverride->fetchAll() as $blok) {
                $override[$blok['kode']] = $blok;
            }
        }

        // nonaktif=1 pada baris override berarti: sub_jenis ini SENGAJA gak
        // punya blok ini sama sekali (mis. sk/umum yang gak punya lampiran
        // daftar pegawai, beda dari tim_kerja/panitia yang defaultnya
        // berlaku) - bukan "ganti kolom" kayak override biasa, makanya di-
        // unset, bukan diganti isinya.
        $hasil = array();
        foreach ($default as $blok) {
            if (isset($override[$blok['kode']]) && !empty($override[$blok['kode']]['nonaktif'])) {
                continue;
            }
            $hasil[$blok['kode']] = isset($override[$blok['kode']]) ? $override[$blok['kode']] : $blok;
        }
        foreach ($override as $kode => $blok) {
            if (!empty($blok['nonaktif'])) {
                continue;
            }
            if (!isset($hasil[$kode])) {
                $hasil[$kode] = $blok; // override tanpa padanan default = blok khusus sub_jenis ini
            }
        }

        $hasil = array_values($hasil);
        foreach ($hasil as &$blok) {
            $blok['kolom'] = self::kolomUntuk($blok['id']);
        }
        unset($blok);

        return $hasil;
    }

    public static function kolomUntuk($blokTabelSuratId)
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM blok_tabel_surat_kolom WHERE blok_tabel_surat_id = ? ORDER BY urutan_kolom'
        );
        $stmt->execute(array($blokTabelSuratId));
        return $stmt->fetchAll();
    }
}
