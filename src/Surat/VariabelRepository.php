<?php

namespace Aurat\Surat;

use Aurat\Database;

/**
 * Katalog variabel_surat + pemetaannya ke template lewat template_surat_variabel.
 */
class VariabelRepository
{
    /**
     * Variabel yang terpasang ke satu template, urut tampil, dengan 'wajib' hasil
     * gabungan wajib_override (template_surat_variabel) dan wajib_default (variabel_surat),
     * dan 'peran_kode' (dari peran_pegawai_surat, jika sumber='pegawai').
     */
    public static function variabelUntukTemplate($templateSuratId)
    {
        $stmt = Database::pdo()->prepare(
            'SELECT v.*, tsv.id AS template_surat_variabel_id, tsv.peran_pegawai_surat_id, tsv.wajib_override,
                    pps.kode AS peran_kode, tsv.urutan_tampil AS urutan_template
             FROM template_surat_variabel tsv
             INNER JOIN variabel_surat v ON v.id = tsv.variabel_surat_id
             LEFT JOIN peran_pegawai_surat pps ON pps.id = tsv.peran_pegawai_surat_id
             WHERE tsv.template_surat_id = ?
             ORDER BY tsv.urutan_tampil, v.urutan_tampil'
        );
        $stmt->execute(array($templateSuratId));
        $baris = $stmt->fetchAll();

        foreach ($baris as &$v) {
            $v['wajib'] = $v['wajib_override'] !== null ? (bool) $v['wajib_override'] : (bool) $v['wajib_default'];
        }
        unset($v);

        return $baris;
    }

    public static function cariByKode($kode)
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM variabel_surat WHERE kode = ? LIMIT 1');
        $stmt->execute(array($kode));
        $baris = $stmt->fetch();
        return $baris ? $baris : null;
    }

    public static function muatById($id)
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM variabel_surat WHERE id = ? LIMIT 1');
        $stmt->execute(array($id));
        $baris = $stmt->fetch();
        return $baris ? $baris : null;
    }

    /** @return array semua variabel, urut kode — untuk layar admin "reuse variabel existing" */
    public static function semua()
    {
        return Database::pdo()->query('SELECT * FROM variabel_surat ORDER BY kode')->fetchAll();
    }

    /**
     * Kode-kode placeholder yang sudah dipetakan ke template ini (untuk dibandingkan
     * dengan hasil TemplateUpload::deteksiPlaceholder() saat admin scan/re-scan).
     */
    public static function kodeTerpasangUntukTemplate($templateSuratId)
    {
        $stmt = Database::pdo()->prepare(
            'SELECT v.kode FROM template_surat_variabel tsv
             INNER JOIN variabel_surat v ON v.id = tsv.variabel_surat_id
             WHERE tsv.template_surat_id = ?'
        );
        $stmt->execute(array($templateSuratId));
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Memasang variabel (existing, dicari by kode) ke sebuah template. */
    public static function pasangKeTemplate($templateSuratId, $variabelSuratId, $peranPegawaiSuratId, $wajibOverride, $urutanTampil, $terdeteksiOtomatis)
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO template_surat_variabel
                (template_surat_id, variabel_surat_id, peran_pegawai_surat_id, wajib_override, urutan_tampil, terdeteksi_otomatis)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array($templateSuratId, $variabelSuratId, $peranPegawaiSuratId, $wajibOverride, $urutanTampil, $terdeteksiOtomatis ? 1 : 0));

        return (int) Database::pdo()->lastInsertId();
    }

    /** Melepas pemasangan variabel dari template (tidak menghapus variabel_surat itu sendiri, bisa dipakai template lain). */
    public static function lepasDariTemplate($templateSuratVariabelId)
    {
        Database::pdo()->prepare('DELETE FROM template_surat_variabel WHERE id = ?')->execute(array($templateSuratVariabelId));
    }
}
