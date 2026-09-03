<?php

namespace Aurat\Surat;

use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use Exception;

/**
 * Validasi & penyimpanan berkas .docx yang diunggah admin, plus deteksi placeholder.
 * Beda dari dokumen/ (yang men-deteksi placeholder RTF lewat regex mentah "#nnnn#"):
 * di sini berkasnya OOXML, jadi dipakai TemplateProcessor::getVariables() bawaan
 * PhpWord (ada sejak 0.18, terkonfirmasi di versi terpasang 0.18.3) — sekaligus jadi
 * validator "apakah ini benar-benar .docx yang valid".
 */
class TemplateUpload
{
    /** Batas ukuran eksplisit di kode — jangan hanya andalkan php.ini. */
    const UKURAN_MAKSIMUM_BYTE = 10485760; // 10MB

    /**
     * @param array $berkas satu entri $_FILES (mis. $_FILES['template'])
     * @return array{nama_berkas: string, nama_asli: string}
     * @throws RuntimeException jika berkas tidak valid
     */
    public static function simpan(array $berkas)
    {
        if (!isset($berkas['error']) || $berkas['error'] !== UPLOAD_ERR_OK) {
            $kodeError = isset($berkas['error']) ? $berkas['error'] : '?';
            throw new RuntimeException('Unggahan berkas gagal (kode error: ' . $kodeError . ').');
        }

        if (!isset($berkas['size']) || $berkas['size'] > self::UKURAN_MAKSIMUM_BYTE) {
            throw new RuntimeException('Ukuran berkas melebihi batas maksimum 10MB.');
        }

        $namaAsli = isset($berkas['name']) ? $berkas['name'] : 'template.docx';
        if (strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION)) !== 'docx') {
            throw new RuntimeException('Berkas harus berformat .docx.');
        }

        if (!is_uploaded_file($berkas['tmp_name'])) {
            throw new RuntimeException('Berkas tidak valid.');
        }

        // Validasi OOXML sungguhan (bukan sekadar cek MIME/ekstensi) — gate gratis dari PhpWord.
        try {
            $processor = new TemplateProcessor($berkas['tmp_name']);
        } catch (Exception $e) {
            throw new RuntimeException('Berkas bukan dokumen .docx yang valid: ' . $e->getMessage());
        }

        if (count($processor->getVariables()) === 0) {
            throw new RuntimeException(
                'Tidak ditemukan placeholder ${...} di dalam berkas. Periksa kembali berkas yang diunggah.'
            );
        }

        $direktori = self::direktoriUpload();
        if (!is_dir($direktori) && !mkdir($direktori, 0755, true) && !is_dir($direktori)) {
            throw new RuntimeException('Gagal menyiapkan folder penyimpanan template di server.');
        }

        // Nama file fisik dibangkitkan sistem — bukan dari input user — cegah path traversal/overwrite.
        $namaBerkas = self::namaBerkasAcak();
        $tujuan = $direktori . '/' . $namaBerkas;

        if (!move_uploaded_file($berkas['tmp_name'], $tujuan)) {
            throw new RuntimeException('Gagal menyimpan berkas ke server.');
        }

        return array('nama_berkas' => $namaBerkas, 'nama_asli' => $namaAsli);
    }

    /**
     * Sama seperti simpan(), tapi sumbernya berkas lokal di server (bukan $_FILES hasil
     * unggahan HTTP) — dipakai skrip migrasi CLI (migrasi/import_jenis_surat.php) untuk
     * menyalin .docx lama ke templates/uploaded/ tanpa mengubah/memindahkan berkas asli
     * (berkas asli tetap dipakai surat/*.php lama selama masa transisi).
     *
     * @return array{nama_berkas: string, nama_asli: string}
     */
    public static function simpanDariPath($absPath, $namaAsli)
    {
        if (!is_file($absPath)) {
            throw new RuntimeException('Berkas sumber tidak ditemukan: ' . $absPath);
        }
        if (filesize($absPath) > self::UKURAN_MAKSIMUM_BYTE) {
            throw new RuntimeException('Ukuran berkas melebihi batas maksimum 10MB.');
        }
        if (strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION)) !== 'docx') {
            throw new RuntimeException('Berkas harus berformat .docx.');
        }

        try {
            $processor = new TemplateProcessor($absPath);
        } catch (Exception $e) {
            throw new RuntimeException('Berkas bukan dokumen .docx yang valid: ' . $e->getMessage());
        }

        if (count($processor->getVariables()) === 0) {
            throw new RuntimeException('Tidak ditemukan placeholder ${...} di dalam berkas: ' . $absPath);
        }

        $direktori = self::direktoriUpload();
        if (!is_dir($direktori) && !mkdir($direktori, 0755, true) && !is_dir($direktori)) {
            throw new RuntimeException('Gagal menyiapkan folder penyimpanan template di server.');
        }

        $namaBerkas = self::namaBerkasAcak();
        $tujuan = $direktori . '/' . $namaBerkas;

        if (!copy($absPath, $tujuan)) {
            throw new RuntimeException('Gagal menyalin berkas ke ' . $tujuan);
        }

        return array('nama_berkas' => $namaBerkas, 'nama_asli' => $namaAsli);
    }

    /** @return string[] daftar nama placeholder mentah (belum dibedakan skalar vs kolom tabel) */
    public static function deteksiPlaceholder($templateAbsPath)
    {
        $processor = new TemplateProcessor($templateAbsPath);
        return $processor->getVariables();
    }

    public static function direktoriUpload()
    {
        return __DIR__ . '/../../templates/uploaded';
    }

    /** Nama acak tak-tertebak; random_bytes() baru ada sejak PHP 7 jadi disiapkan fallback utk PHP 5.6. */
    private static function namaBerkasAcak()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16)) . '.docx';
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes(16)) . '.docx';
        }
        return uniqid('tpl_', true) . '.docx';
    }
}
