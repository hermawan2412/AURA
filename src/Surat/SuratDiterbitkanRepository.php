<?php

namespace Aurat\Surat;

use Aurat\Database;

/**
 * Ledger surat yang diterbitkan (surat_diterbitkan) - 1 baris per generate
 * sukses, snapshot semua nilai form (dasar fitur "isi ulang dari surat
 * sebelumnya") + kolom denormalisasi nomor/tanggal/ringkasan (dari 3 kode
 * variabel yang admin pilih per jenis_surat, lihat jenis_surat.variabel_
 * nomor_kode dkk) buat daftar & badge status kadaluwarsa.
 */
class SuratDiterbitkanRepository
{
    /**
     * Dipanggil sesudah $resolver->resolveSemua() sukses, sebelum dokumen
     * di-stream - $nilai adalah array kode_variabel => nilai_string.
     */
    public static function catat(array $jenisSurat, $subJenisSuratId, $templateSuratId, array $nilai, $dibuatOleh)
    {
        $ambil = function ($kode) use ($nilai) {
            return ($kode !== null && $kode !== '' && isset($nilai[$kode])) ? $nilai[$kode] : null;
        };

        $nomor = $ambil($jenisSurat['variabel_nomor_kode']);
        $ringkasan = $ambil($jenisSurat['variabel_ringkasan_kode']);

        $tanggalKode = $jenisSurat['variabel_tanggal_kode'];
        $tanggalMentah = $ambil($tanggalKode);
        // Nilai tanggal yg sudah lewat fungsi_pasca (mis. tanggal_indonesia)
        // berbentuk "5 September 2026", bukan format DATE - kalau begitu,
        // fallback ke input mentah dari $_POST (belum diformat) supaya
        // masih bisa disimpan sbg DATE beneran, bukan dipaksa NULL.
        $tanggalDokumen = null;
        if ($tanggalMentah !== null) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalMentah)) {
                $tanggalDokumen = $tanggalMentah;
            } elseif (isset($_POST['var'][$tanggalKode]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['var'][$tanggalKode])) {
                $tanggalDokumen = $_POST['var'][$tanggalKode];
            }
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO surat_diterbitkan
                (jenis_surat_id, sub_jenis_surat_id, template_surat_id, nomor, tanggal_dokumen, ringkasan, nilai_lengkap, dibuat_oleh)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $jenisSurat['id'], $subJenisSuratId, $templateSuratId,
            $nomor, $tanggalDokumen, $ringkasan !== null ? mb_substr((string) $ringkasan, 0, 255) : null,
            json_encode($nilai, JSON_UNESCAPED_UNICODE),
            $dibuatOleh,
        ));

        return (int) Database::pdo()->lastInsertId();
    }

    /** @return array daftar surat_diterbitkan + kolom jenis_surat/sub_jenis_surat terkait, terbaru dulu */
    public static function semua($jenisSuratId = null)
    {
        $sql = 'SELECT sd.*, js.nama AS jenis_nama, js.icon AS jenis_icon, sjs.label AS sub_jenis_label
                FROM surat_diterbitkan sd
                JOIN jenis_surat js ON js.id = sd.jenis_surat_id
                LEFT JOIN sub_jenis_surat sjs ON sjs.id = sd.sub_jenis_surat_id';
        $params = array();
        if ($jenisSuratId !== null) {
            $sql .= ' WHERE sd.jenis_surat_id = ?';
            $params[] = $jenisSuratId;
        }
        $sql .= ' ORDER BY sd.created_at DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function muatById($id)
    {
        $stmt = Database::pdo()->prepare(
            'SELECT sd.*, js.nama AS jenis_nama, js.kode AS jenis_kode, sjs.kode AS sub_jenis_kode, sjs.label AS sub_jenis_label
             FROM surat_diterbitkan sd
             JOIN jenis_surat js ON js.id = sd.jenis_surat_id
             LEFT JOIN sub_jenis_surat sjs ON sjs.id = sd.sub_jenis_surat_id
             WHERE sd.id = ? LIMIT 1'
        );
        $stmt->execute(array($id));
        $baris = $stmt->fetch();
        return $baris ? $baris : null;
    }

    /** Update manual admin: tanggal berlaku sampai + link dokumentasi (NAS/path). */
    public static function ubahMetadata($id, $berlakuSampai, $linkDokumentasi)
    {
        Database::pdo()->prepare(
            'UPDATE surat_diterbitkan SET berlaku_sampai = ?, link_dokumentasi = ?, updated_at = NOW() WHERE id = ?'
        )->execute(array(
            $berlakuSampai !== '' ? $berlakuSampai : null,
            $linkDokumentasi !== '' ? $linkDokumentasi : null,
            $id,
        ));
    }

    public static function hapus($id)
    {
        Database::pdo()->prepare('DELETE FROM surat_diterbitkan WHERE id = ?')->execute(array($id));
    }

    /**
     * Status badge dari berlaku_sampai: null=belum diisi admin, "aktif",
     * "segera" (<=60 hari lagi), "kedaluwarsa". Ambang 60 hari - sama pola
     * yang sudah dipakai KGB/KNP di app sebelah (RESTU), bukan angka
     * baru dikarang di sini.
     */
    public static function statusBerlaku($berlakuSampai)
    {
        if ($berlakuSampai === null) {
            return null;
        }
        $sisaHari = (int) floor((strtotime($berlakuSampai) - strtotime(date('Y-m-d'))) / 86400);
        if ($sisaHari < 0) {
            return 'kedaluwarsa';
        }
        if ($sisaHari <= 60) {
            return 'segera';
        }
        return 'aktif';
    }
}
