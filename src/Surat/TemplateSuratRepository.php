<?php

namespace Aurat\Surat;

use Aurat\Database;
use Exception;

/**
 * Template (berkas .docx) aktif per jenis surat / sub-jenis surat.
 */
class TemplateSuratRepository
{
    /**
     * @param int      $jenisSuratId
     * @param int|null $subJenisSuratId null utk single_dokumen / template default dua_dokumen
     * @param string   $tipeDokumen 'utama' (dokumen pokok) atau 'lampiran' (mis. Daftar Hadir Undangan)
     * @return array|null baris template_surat yang sedang aktif
     */
    public static function templateUntuk($jenisSuratId, $subJenisSuratId = null, $tipeDokumen = 'utama')
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM template_surat
             WHERE jenis_surat_id = ? AND sub_jenis_surat_id <=> ? AND tipe_dokumen = ? AND status_aktif = 1
             ORDER BY versi DESC LIMIT 1'
        );
        $stmt->execute(array($jenisSuratId, $subJenisSuratId, $tipeDokumen));
        $baris = $stmt->fetch();
        return $baris ? $baris : null;
    }

    /**
     * Semua template AKTIF utk 1 scope (utama + lampiran-lampirannya), utama
     * selalu di indeks 0 kalau ada - dipakai surat/index.php buat generate
     * lebih dari 1 dokumen dari 1 submit form (mis. Undangan + Daftar Hadir).
     * @return array daftar baris template_surat, urut tipe_dokumen (utama dulu)
     */
    public static function templateAktifSemua($jenisSuratId, $subJenisSuratId = null)
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM template_surat
             WHERE jenis_surat_id = ? AND sub_jenis_surat_id <=> ? AND status_aktif = 1
             ORDER BY FIELD(tipe_dokumen, 'utama', 'lampiran'), versi DESC"
        );
        $stmt->execute(array($jenisSuratId, $subJenisSuratId));
        return $stmt->fetchAll();
    }

    public static function muatById($templateSuratId)
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM template_surat WHERE id = ? LIMIT 1');
        $stmt->execute(array($templateSuratId));
        $baris = $stmt->fetch();
        return $baris ? $baris : null;
    }

    /** @return array semua versi (aktif & tidak) utk satu scope, terbaru dulu — untuk layar riwayat/rollback */
    public static function riwayat($jenisSuratId, $subJenisSuratId = null, $tipeDokumen = 'utama')
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM template_surat
             WHERE jenis_surat_id = ? AND sub_jenis_surat_id <=> ? AND tipe_dokumen = ?
             ORDER BY versi DESC'
        );
        $stmt->execute(array($jenisSuratId, $subJenisSuratId, $tipeDokumen));
        return $stmt->fetchAll();
    }

    /** @param array $template baris template_surat, dari templateUntuk()/muatById() */
    public static function path(array $template)
    {
        return 'templates/uploaded/' . $template['nama_berkas'];
    }

    /**
     * Menonaktifkan versi aktif lama, lalu menyimpan baris versi baru — dalam satu
     * transaksi (lihat catatan di db/002_generic_surat_engine.sql: UNIQUE biasa tidak
     * bisa menegakkan "1 versi aktif per scope" karena NULL pada sub_jenis_surat_id).
     *
     * @return int id baris template_surat yang baru dibuat
     */
    public static function simpanVersiBaru($jenisSuratId, $subJenisSuratId, $namaBerkas, $namaAsli, $diunggahOleh, $tipeDokumen = 'utama')
    {
        $pdo = Database::pdo();
        // PDO tidak mendukung transaksi bersarang — kalau pemanggil (mis. skrip migrasi)
        // sudah membuka transaksinya sendiri, jangan buka/commit/rollback transaksi baru di sini.
        $transaksiSendiri = !$pdo->inTransaction();
        if ($transaksiSendiri) {
            $pdo->beginTransaction();
        }

        try {
            $nonaktifkan = $pdo->prepare(
                'UPDATE template_surat SET status_aktif = 0
                 WHERE jenis_surat_id = ? AND sub_jenis_surat_id <=> ? AND tipe_dokumen = ? AND status_aktif = 1'
            );
            $nonaktifkan->execute(array($jenisSuratId, $subJenisSuratId, $tipeDokumen));

            $versiStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(versi), 0) AS versi_terakhir FROM template_surat
                 WHERE jenis_surat_id = ? AND sub_jenis_surat_id <=> ? AND tipe_dokumen = ?'
            );
            $versiStmt->execute(array($jenisSuratId, $subJenisSuratId, $tipeDokumen));
            $versiBaru = (int) $versiStmt->fetch()['versi_terakhir'] + 1;

            $insert = $pdo->prepare(
                'INSERT INTO template_surat
                    (jenis_surat_id, sub_jenis_surat_id, tipe_dokumen, nama_berkas, nama_asli, versi, status_aktif, diunggah_oleh)
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
            );
            $insert->execute(array($jenisSuratId, $subJenisSuratId, $tipeDokumen, $namaBerkas, $namaAsli, $versiBaru, $diunggahOleh));

            $id = (int) $pdo->lastInsertId();
            if ($transaksiSendiri) {
                $pdo->commit();
            }

            return $id;
        } catch (Exception $e) {
            if ($transaksiSendiri) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Mengaktifkan kembali versi lama tanpa upload ulang (rollback), menonaktifkan versi yang sekarang aktif. */
    public static function aktifkanVersi($templateSuratId)
    {
        $template = self::muatById($templateSuratId);
        if (!$template) {
            throw new \RuntimeException('Versi template tidak ditemukan.');
        }

        $pdo = Database::pdo();
        $transaksiSendiri = !$pdo->inTransaction();
        if ($transaksiSendiri) {
            $pdo->beginTransaction();
        }

        try {
            $nonaktifkan = $pdo->prepare(
                'UPDATE template_surat SET status_aktif = 0
                 WHERE jenis_surat_id = ? AND sub_jenis_surat_id <=> ? AND tipe_dokumen = ? AND status_aktif = 1'
            );
            $nonaktifkan->execute(array($template['jenis_surat_id'], $template['sub_jenis_surat_id'], $template['tipe_dokumen']));

            $aktifkan = $pdo->prepare('UPDATE template_surat SET status_aktif = 1 WHERE id = ?');
            $aktifkan->execute(array($templateSuratId));

            if ($transaksiSendiri) {
                $pdo->commit();
            }
        } catch (Exception $e) {
            if ($transaksiSendiri) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Hapus satu versi riwayat (baris DB + berkas fisiknya). Versi yang sedang aktif
     * TIDAK boleh dihapus lewat sini — aktifkan versi lain dulu (aktifkanVersi()),
     * supaya jenis surat tidak pernah tanpa template aktif secara tidak sengaja.
     * Pemetaan variabel (template_surat_variabel) ikut terhapus lewat ON DELETE CASCADE.
     */
    public static function hapus($templateSuratId)
    {
        $template = self::muatById($templateSuratId);
        if (!$template) {
            return;
        }
        if ((int) $template['status_aktif'] === 1) {
            throw new \RuntimeException('Versi yang sedang aktif tidak bisa dihapus — aktifkan versi lain dulu.');
        }

        Database::pdo()->prepare('DELETE FROM template_surat WHERE id = ?')->execute(array($templateSuratId));

        $path = TemplateUpload::direktoriUpload() . '/' . $template['nama_berkas'];
        if (is_file($path)) {
            unlink($path);
        }
    }
}
