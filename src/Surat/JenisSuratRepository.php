<?php

namespace Aurat\Surat;

use Aurat\Database;

/**
 * Data jenis surat dari tabel jenis_surat (+ sub_jenis_surat, peran_pegawai_surat).
 * Menggantikan Aurat\JenisSurat (pemuat config/jenis_surat/*.json) di mesin generic.
 */
class JenisSuratRepository
{
    /** @return array|null baris jenis_surat + 'sub_jenis' + 'peran_pegawai', atau null jika tidak ada */
    public static function muat($kode)
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM jenis_surat WHERE kode = ? LIMIT 1');
        $stmt->execute(array($kode));
        $jenis = $stmt->fetch();

        if (!$jenis) {
            return null;
        }

        $jenis['sub_jenis']     = self::subJenisUntuk($jenis['id']);
        $jenis['peran_pegawai'] = self::peranPegawaiUntuk($jenis['id']);

        return $jenis;
    }

    /** @return array|null sama seperti muat(), tapi lookup lewat id */
    public static function muatById($id)
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM jenis_surat WHERE id = ? LIMIT 1');
        $stmt->execute(array($id));
        $jenis = $stmt->fetch();

        if (!$jenis) {
            return null;
        }

        $jenis['sub_jenis']     = self::subJenisUntuk($jenis['id']);
        $jenis['peran_pegawai'] = self::peranPegawaiUntuk($jenis['id']);

        return $jenis;
    }

    public static function semua($hanyaAktif = true)
    {
        $sql = 'SELECT * FROM jenis_surat';
        if ($hanyaAktif) {
            $sql .= ' WHERE status_aktif = 1';
        }
        $sql .= ' ORDER BY urutan_tampil, nama';

        return Database::pdo()->query($sql)->fetchAll();
    }

    public static function subJenisUntuk($jenisSuratId)
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM sub_jenis_surat WHERE jenis_surat_id = ? ORDER BY urutan_tampil, label'
        );
        $stmt->execute(array($jenisSuratId));
        return $stmt->fetchAll();
    }

    public static function subJenisByKode($jenisSuratId, $kode)
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM sub_jenis_surat WHERE jenis_surat_id = ? AND kode = ? LIMIT 1'
        );
        $stmt->execute(array($jenisSuratId, $kode));
        $baris = $stmt->fetch();
        return $baris ? $baris : null;
    }

    public static function peranPegawaiUntuk($jenisSuratId)
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM peran_pegawai_surat WHERE jenis_surat_id = ? ORDER BY urutan_tampil, label'
        );
        $stmt->execute(array($jenisSuratId));
        return $stmt->fetchAll();
    }
}
