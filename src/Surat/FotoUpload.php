<?php

namespace Aurat\Surat;

use RuntimeException;

/**
 * Validasi & penyimpanan foto dokumentasi (mis. Notula) - dipasang ke docx
 * lewat TemplateProcessor::setImageValue(), bukan setValue() biasa (lihat
 * DocxGenerator). Pola validasinya sama kayak TemplateUpload (MIME asli via
 * finfo, bukan percaya ekstensi/Content-Type dari browser), tapi target &
 * jenis berkasnya beda - gambar, bukan .docx.
 */
class FotoUpload
{
    const UKURAN_MAKSIMUM_BYTE = 5242880; // 5MB

    private static $mimeDiizinkan = array(
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
    );

    /**
     * @param array $berkas satu entri $_FILES (mis. $_FILES['var_file']['kode'] yg sudah dirakit ulang)
     * @return string|null path absolut berkas tersimpan, atau null kalau field dikosongkan (opsional)
     * @throws RuntimeException kalau berkas dipilih tapi tidak valid
     */
    public static function simpan(array $berkas)
    {
        $error = isset($berkas['error']) ? $berkas['error'] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Unggahan gagal (kode error: ' . $error . ').');
        }
        if (!isset($berkas['size']) || $berkas['size'] > self::UKURAN_MAKSIMUM_BYTE) {
            throw new RuntimeException('Ukuran berkas melebihi batas maksimum 5MB.');
        }
        if (!is_uploaded_file($berkas['tmp_name'])) {
            throw new RuntimeException('Berkas tidak valid.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($berkas['tmp_name']);
        if (!isset(self::$mimeDiizinkan[$mime])) {
            throw new RuntimeException('Berkas harus berformat JPG atau PNG.');
        }

        $direktori = self::direktoriUpload();
        if (!is_dir($direktori) && !mkdir($direktori, 0755, true) && !is_dir($direktori)) {
            throw new RuntimeException('Gagal menyiapkan folder penyimpanan foto di server.');
        }

        $namaBerkas = self::namaBerkasAcak(self::$mimeDiizinkan[$mime]);
        $tujuan = $direktori . '/' . $namaBerkas;

        if (!move_uploaded_file($berkas['tmp_name'], $tujuan)) {
            throw new RuntimeException('Gagal menyimpan berkas ke server.');
        }

        return $tujuan;
    }

    public static function direktoriUpload()
    {
        return __DIR__ . '/../../uploads/notula_foto';
    }

    private static function namaBerkasAcak($ekstensi)
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16)) . '.' . $ekstensi;
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes(16)) . '.' . $ekstensi;
        }
        return uniqid('foto_', true) . '.' . $ekstensi;
    }
}
