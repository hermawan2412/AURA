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
    public static function generateDanUnduh($templateRelPath, array $nilai, array $tabel, $namaUnduhan, array $gambar = array())
    {
        $tempPath = self::generate($templateRelPath, $nilai, $tabel, $gambar);
        self::streamDanHapus($tempPath, $namaUnduhan);
    }

    /**
     * Sama seperti generateDanUnduh(), tapi return path file sementara tanpa
     * langsung stream - dipakai kalau 1 submission perlu menghasilkan lebih
     * dari 1 dokumen sekaligus (mis. Undangan + lampiran Daftar Hadir, lihat
     * generateZipDanUnduh()). Pemanggil WAJIB unlink() sendiri kalau tidak
     * jadi dipakai (generateZipDanUnduh() sudah menangani ini).
     *
     * @param array $gambar placeholder => path absolut gambar (mis. ['foto_notula' => '/path/foto.jpg']) -
     *                       disuntik pakai setImageValue(), BUKAN setValue(). Placeholder gambar yang
     *                       kosong (opsional, tidak diisi) sudah otomatis ke-blank lewat $nilai (lihat
     *                       NilaiResolver: sumber='manual' tanpa input jatuh ke placeholder_default=''),
     *                       jadi tidak perlu ditangani terpisah di sini.
     * @return string path file .docx sementara
     */
    public static function generate($templateRelPath, array $nilai, array $tabel, array $gambar = array())
    {
        $templatePath = __DIR__ . '/../' . $templateRelPath;

        if (!is_file($templatePath)) {
            throw new RuntimeException(
                'Berkas template belum tersedia: ' . $templateRelPath . '. ' .
                'Template asli perlu disiapkan dulu di folder templates/ sebelum surat ini bisa dibuat.'
            );
        }

        $processor = new TemplateProcessor($templatePath);
        $variabelDiTemplate = $processor->getVariables();

        foreach ($gambar as $placeholder => $pathGambar) {
            if (!in_array($placeholder, $variabelDiTemplate, true) || !is_file($pathGambar)) {
                continue; // dokumen ini gak pakai placeholder gambar ini, atau berkas gak ada
            }
            $processor->setImageValue($placeholder, array('path' => $pathGambar, 'width' => 350, 'ratio' => true));
        }

        foreach ($nilai as $placeholder => $teks) {
            if (isset($gambar[$placeholder])) {
                continue; // sudah ditangani sebagai gambar di atas, jangan di-setValue() teks jadi
            }
            $processor->setValue($placeholder, self::escape($teks));
        }

        foreach ($tabel as $namaBlok => $baris) {
            // Blok tabel di-scope per JENIS SURAT (lihat BlokTabelRepository), bukan
            // per template_surat - jadi jenis surat dgn dokumen lampiran (mis. Undangan
            // + Daftar Hadir) wajar kalau 1 blok cuma dipakai salah satu dokumennya.
            // Placeholder yg gak ada di file INI dilewati, bukan error - dokumen ini
            // memang gak pakai tabel tsb.
            if (!in_array($namaBlok, $variabelDiTemplate, true)) {
                continue;
            }

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

        return $tempPath;
    }

    /**
     * Generate beberapa dokumen sekaligus (1 submission -> N template aktif,
     * mis. Undangan [utama] + Daftar Hadir [lampiran]) dan alirkan sebagai
     * SATU berkas .zip - lebih simpel buat user daripada 2 unduhan terpisah.
     * $dokumen: array of ['nilai' => array, 'tabel' => array, 'gambar' => array, 'templateRelPath' => string, 'namaUnduhan' => string]
     * ('gambar' opsional per entri)
     */
    public static function generateZipDanUnduh(array $dokumen, $namaZip)
    {
        $tempPaths = array();
        try {
            foreach ($dokumen as $d) {
                $tempPaths[] = array(
                    'path' => self::generate($d['templateRelPath'], $d['nilai'], $d['tabel'], isset($d['gambar']) ? $d['gambar'] : array()),
                    'namaUnduhan' => $d['namaUnduhan'],
                );
            }
        } catch (RuntimeException $e) {
            foreach ($tempPaths as $t) {
                @unlink($t['path']);
            }
            throw $e;
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'surat_zip_');
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            foreach ($tempPaths as $t) {
                @unlink($t['path']);
            }
            throw new RuntimeException('Gagal membuat berkas .zip.');
        }
        foreach ($tempPaths as $t) {
            $zip->addFile($t['path'], $t['namaUnduhan']);
        }
        $zip->close();

        foreach ($tempPaths as $t) {
            unlink($t['path']);
        }

        self::streamZipDanHapus($zipPath, $namaZip);
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

    private static function streamZipDanHapus($filePath, $namaUnduhan)
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
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
