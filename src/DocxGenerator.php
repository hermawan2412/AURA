<?php

namespace Aurat;

use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/**
 * Mengisi template DOCX (placeholder gaya ${nama_field}) lalu langsung
 * mengalirkan hasilnya ke browser sebagai unduhan. Tidak ada berkas yang
 * disimpan permanen di server (lihat rangkuman kebutuhan §"Format output").
 */
class DocxGenerator
{
    /**
     * @param string $templateRelPath  relatif ke folder app/, mis. "templates/cuti.docx"
     * @param array  $nilai            placeholder => teks pengganti, untuk ${placeholder} biasa
     * @param array  $tabel            opsional: [nama_blok => baris[]], tiap baris = [kolom => nilai]
     *                                 nama_blok harus cocok dengan placeholder di baris pertama
     *                                 tabel pada template (dipakai oleh TemplateProcessor::cloneRow)
     * @param string $namaUnduhan      nama file .docx yang dilihat pengguna saat mengunduh
     */
    public static function generateDanUnduh($templateRelPath, array $nilai, array $tabel, $namaUnduhan)
    {
        $templatePath = __DIR__ . '/../' . $templateRelPath;

        if (!is_file($templatePath)) {
            throw new RuntimeException(
                'Berkas template belum tersedia: ' . $templateRelPath . '. ' .
                'Template asli perlu disiapkan dulu di folder templates/ sebelum surat ini bisa dibuat.'
            );
        }

        $processor = new TemplateProcessor($templatePath);

        foreach ($nilai as $placeholder => $teks) {
            $processor->setValue($placeholder, self::escape($teks));
        }

        foreach ($tabel as $namaBlok => $baris) {
            $jumlahBaris = count($baris);

            if ($jumlahBaris === 0) {
                throw new RuntimeException('Tabel "' . $namaBlok . '" tidak boleh kosong — pilih minimal satu pegawai.');
            }

            $processor->cloneRow($namaBlok, $jumlahBaris);

            foreach ($baris as $indeks => $kolom) {
                $nomorBaris = $indeks + 1;
                foreach ($kolom as $namaKolom => $teksKolom) {
                    $processor->setValue($namaKolom . '#' . $nomorBaris, self::escape($teksKolom));
                }
            }
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'surat_');
        $processor->saveAs($tempPath);

        self::streamDanHapus($tempPath, $namaUnduhan);
    }

    /**
     * Escape untuk XML Word, plus konversi newline literal menjadi <w:br/>
     * — tanpa ini, isi field multi-baris (mis. textarea) akan tampil
     * menyatu jadi satu baris saat dibuka di Word.
     */
    private static function escape($teks)
    {
        $aman = htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
        $aman = str_replace(array("\r\n", "\r", "\n"), '</w:t><w:br/><w:t xml:space="preserve">', $aman);

        return $aman;
    }

    private static function streamDanHapus($filePath, $namaUnduhan)
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $namaUnduhan . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, no-store, no-cache');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        unlink($filePath);
        exit;
    }
}
