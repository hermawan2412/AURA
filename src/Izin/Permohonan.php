<?php

namespace Aurat\Izin;

use Aurat\Formatter;
use DateTimeImmutable;
use PDO;

/**
 * Transisi permohonan izin (rancangan bagian 9). Setiap aksi:
 * transaksi -> kunci baris -> cek status/versi/aktor/waktu -> tulis status,
 * tahap, audit, notifikasi, outbox WA -> commit. Gagal di tengah (termasuk
 * audit) = rollback seluruhnya.
 *
 * $sekarang selalu UTC, dioper pemanggil (halaman: new DateTimeImmutable('now', UTC)),
 * supaya tes bisa mengatur waktu.
 */
class Permohonan
{
    const PEMBERI_PTA = 'Ketua Pengadilan Tinggi Agama Banjarmasin';
    const STATUS_MEMBLOKIR = array('menunggu_persetujuan', 'diajukan_ke_pta', 'disetujui');
    const LABEL_JENIS = array('keluar_sementara' => 'keluar sementara', 'pulang_awal' => 'pulang awal');
    const LABEL_STATUS = array(
        'draf' => 'Draf',
        'menunggu_persetujuan' => 'Menunggu persetujuan',
        'diajukan_ke_pta' => 'Diajukan ke KPTA',
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
        'dicabut' => 'Dicabut',
    );

    // ------------------------------------------------------------------
    // Baca
    // ------------------------------------------------------------------

    public static function pengaturan(PDO $db)
    {
        $p = $db->query('SELECT * FROM izin_pengaturan WHERE id = 1')->fetch();
        if (!$p) {
            throw new IzinException('Modul izin belum dipasang (migrasi 037 belum dijalankan).');
        }
        return $p;
    }

    /** Pemohon dari sesi: user_login aktif -> pegawai aktif (NIP) -> kelompok. */
    public static function identitas(PDO $db, $userId)
    {
        $s = $db->prepare(
            'SELECT p.*, p.id AS pegawai_id, u.id AS user_id, m.kelompok
             FROM user_login u
             JOIN pegawai p ON p.nip = u.nip AND p.status_aktif = 1
             LEFT JOIN izin_pemetaan_pegawai m ON m.pegawai_id = p.id
             WHERE u.id = ? AND u.status_aktif = 1 AND u.nip IS NOT NULL AND u.nip <> \'\''
        );
        $s->execute(array((int) $userId));
        $r = $s->fetch();
        if (!$r) {
            throw new IzinException('Akun Anda belum terhubung ke data pegawai aktif (NIP). Hubungi admin.');
        }
        $r['nama'] = Formatter::namaBergelar($r);
        return $r;
    }

    /** kode => data pejabat + user_id + user_aktif (format masukan Routing::tentukan). */
    public static function pejabat(PDO $db)
    {
        $rows = $db->query(
            'SELECT j.kode, p.*, p.id AS pegawai_id, u.id AS user_id,
                    (u.id IS NOT NULL AND u.status_aktif = 1 AND p.status_aktif = 1) AS user_aktif
             FROM izin_pejabat j
             JOIN pegawai p ON p.id = j.pegawai_id
             LEFT JOIN user_login u ON u.nip = p.nip AND u.nip <> \'\''
        )->fetchAll();
        $hasil = array();
        foreach ($rows as $r) {
            $r['user_aktif'] = (bool) $r['user_aktif'];
            $r['nama'] = Formatter::namaBergelar($r);
            $hasil[$r['kode']] = $r;
        }
        return $hasil;
    }

    public static function berhalangan(PDO $db, DateTimeImmutable $sekarang)
    {
        $t = self::utc($sekarang);
        $s = $db->prepare('SELECT pegawai_id FROM izin_berhalangan WHERE dibatalkan_at IS NULL AND mulai <= ? AND selesai > ?');
        $s->execute(array($t, $t));
        return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function muat(PDO $db, $id)
    {
        $s = $db->prepare('SELECT * FROM izin_permohonan WHERE id = ?');
        $s->execute(array((int) $id));
        return $s->fetch() ?: null;
    }

    public static function tahap(PDO $db, $permohonanId)
    {
        $s = $db->prepare('SELECT * FROM izin_tahap WHERE permohonan_id = ? ORDER BY id');
        $s->execute(array((int) $permohonanId));
        return $s->fetchAll();
    }

    /** Apakah akun ini milik pegawai yang saat ini tercatat sebagai Ketua/Wakil/Panitera/Sekretaris? */
    public static function pejabatAktif(PDO $db, $userId)
    {
        $s = $db->prepare(
            'SELECT 1 FROM izin_pejabat j JOIN pegawai p ON p.id = j.pegawai_id
             JOIN user_login u ON u.nip = p.nip AND u.nip <> \'\' WHERE u.id = ? AND u.status_aktif = 1 LIMIT 1'
        );
        $s->execute(array((int) $userId));
        return (bool) $s->fetchColumn();
    }

    /** Pemohon, pemberi (sekarang/dulu), penerima pemberitahuan, atau admin. */
    public static function bolehLihat(PDO $db, array $p, $userId, $isAdmin)
    {
        $userId = (int) $userId;
        if ($isAdmin || (int) $p['pemohon_user_id'] === $userId || (int) $p['mengetahui_user_id'] === $userId) {
            return true;
        }
        $s = $db->prepare('SELECT 1 FROM izin_tahap WHERE permohonan_id = ? AND pemberi_user_id = ? LIMIT 1');
        $s->execute(array((int) $p['id'], $userId));
        return (bool) $s->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Transisi
    // ------------------------------------------------------------------

    /**
     * @param array $in jenis, mulai_sekarang (bool), tanggal, jam_mulai, jam_kembali, alasan, asal_permohonan_id
     * @return int id permohonan baru
     */
    public static function ajukan(PDO $db, $userId, array $in, DateTimeImmutable $sekarang)
    {
        $peng = self::pengaturan($db);
        self::wajibAktif($peng);
        $alasan = self::wajibTeks(isset($in['alasan']) ? $in['alasan'] : '', 'Alasan', 10);

        return self::transaksi($db, function () use ($db, $userId, $in, $sekarang, $peng, $alasan) {
            $pem = self::identitas($db, $userId);
            // Serialisasi per pegawai: dua submit bersamaan tidak bisa sama-sama lolos cek bentrok.
            $db->prepare('SELECT id FROM pegawai WHERE id = ? FOR UPDATE')->execute(array($pem['pegawai_id']));

            $w = Waktu::validasi(
                isset($in['jenis']) ? $in['jenis'] : '',
                !empty($in['mulai_sekarang']),
                isset($in['tanggal']) ? $in['tanggal'] : '',
                isset($in['jam_mulai']) ? $in['jam_mulai'] : '',
                isset($in['jam_kembali']) && $in['jam_kembali'] !== '' ? $in['jam_kembali'] : null,
                Waktu::jadwalDariJson($peng['jadwal_kerja']),
                $db->query('SELECT tanggal FROM izin_hari_libur')->fetchAll(PDO::FETCH_COLUMN),
                $peng['zona_waktu'],
                $sekarang
            );
            if (isset($w['error'])) {
                throw new IzinException($w['error']);
            }

            $s = $db->prepare(
                'SELECT COUNT(*) FROM izin_permohonan
                 WHERE pemohon_pegawai_id = ? AND tanggal = ? AND status IN (' . self::daftarIn(self::STATUS_MEMBLOKIR) . ')
                   AND jam_mulai < ? AND jam_akhir > ?'
            );
            $s->execute(array($pem['pegawai_id'], $w['tanggal'], $w['jam_akhir'], $w['jam_mulai']));
            if ((int) $s->fetchColumn() > 0) {
                throw new IzinException('Waktu izin bentrok dengan permohonan Anda yang lain pada tanggal yang sama.');
            }

            $pejabat = self::pejabat($db);
            $rute = Routing::tentukan((string) $pem['kelompok'], $pejabat, self::berhalangan($db, $sekarang), $pem['pegawai_id']);
            if (isset($rute['error'])) {
                throw new IzinException($rute['error']);
            }
            $ketahui = $rute['mengetahui'] !== null ? $pejabat[$rute['mengetahui']] : null;
            $kePta = $rute['pemberi'] === 'pta';
            $kini = self::utc($sekarang);

            $db->prepare(
                'INSERT INTO izin_permohonan
                 (pemohon_user_id, pemohon_pegawai_id, pemohon_nama, pemohon_nip, pemohon_jabatan, pemohon_unit_kerja,
                  kelompok, zona_waktu, jenis, tanggal, jam_mulai, jam_kembali, jam_akhir, alasan, status,
                  asal_permohonan_id, mengetahui_user_id, mengetahui_nama, mengetahui_jabatan, pemberi_eksternal,
                  dibuat_at, diajukan_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute(array(
                $pem['user_id'], $pem['pegawai_id'], $pem['nama'], $pem['nip'], $pem['jabatan'], $pem['unit_kerja'],
                $pem['kelompok'], $peng['zona_waktu'], $in['jenis'], $w['tanggal'], $w['jam_mulai'], $w['jam_kembali'], $w['jam_akhir'],
                $alasan, $kePta ? 'diajukan_ke_pta' : 'menunggu_persetujuan',
                self::asalMilikSendiri($db, isset($in['asal_permohonan_id']) ? $in['asal_permohonan_id'] : null, $pem['user_id']),
                $ketahui ? $ketahui['user_id'] : null, $ketahui ? $ketahui['nama'] : null, $ketahui ? $ketahui['jabatan'] : null,
                $kePta ? self::PEMBERI_PTA : null,
                $kini, $kini,
            ));
            $pid = (int) $db->lastInsertId();
            $p = self::muat($db, $pid);

            if ($kePta) {
                if ($ketahui) {
                    self::beritahu($db, $peng, $ketahui['user_id'], $p, 'diajukan_pta', null, $sekarang);
                }
            } else {
                $pb = $pejabat[$rute['pemberi']];
                $tahapId = self::buatTahap($db, $pid, $pb, null, $kini);
                self::beritahu($db, $peng, $pb['user_id'], $p, 'tugas_' . $tahapId, $tahapId, $sekarang);
            }
            self::audit($db, $pid, $userId, 'ajukan', null, $p['status'], null, array('rute' => $rute), $sekarang);
            return $pid;
        });
    }

    public static function setujui(PDO $db, $userId, $id, $versi, $catatan, DateTimeImmutable $sekarang)
    {
        return self::putuskan($db, $userId, $id, $versi, $catatan, $sekarang, true);
    }

    public static function tolak(PDO $db, $userId, $id, $versi, $catatan, DateTimeImmutable $sekarang)
    {
        return self::putuskan($db, $userId, $id, $versi, self::wajibTeks($catatan, 'Alasan penolakan', 3), $sekarang, false);
    }

    private static function putuskan(PDO $db, $userId, $id, $versi, $catatan, DateTimeImmutable $sekarang, $setuju)
    {
        $peng = self::pengaturan($db);
        self::wajibAktif($peng);

        self::transaksi($db, function () use ($db, $peng, $userId, $id, $versi, $catatan, $sekarang, $setuju) {
            $p = self::kunci($db, $id, $versi, array('menunggu_persetujuan'));
            $t = self::tahapAktif($db, $id);
            if (!$t || (int) $t['pemberi_user_id'] !== (int) $userId) {
                throw new IzinException('Anda bukan pemberi izin untuk permohonan ini.');
            }
            self::cekMasihBerwenang($db, $t, $sekarang);
            if ($setuju && !Waktu::masihBolehDiputus($p['tanggal'], $p['zona_waktu'], $sekarang)) {
                throw new IzinException('Tanggal izin sudah lewat; persetujuan tidak dapat dicatat. Permohonan masih bisa ditolak.');
            }
            $kini = self::utc($sekarang);
            $status = $setuju ? 'disetujui' : 'ditolak';

            $db->prepare('UPDATE izin_tahap SET status = ?, catatan = ?, diputus_at = ? WHERE id = ?')
                ->execute(array($setuju ? 'selesai' : 'ditolak', self::kosongJadiNull($catatan), $kini, $t['id']));
            self::ubahStatus($db, $p, $status, $userId, $catatan, $kini);
            self::batalkanWaTahap($db, $t['id']);

            $p = self::muat($db, $id);
            self::beritahu($db, $peng, $p['pemohon_user_id'], $p, $status, null, $sekarang);
            if ($setuju && $p['mengetahui_user_id']) {
                self::beritahu($db, $peng, $p['mengetahui_user_id'], $p, 'mengetahui', null, $sekarang);
            }
            self::audit($db, $id, $userId, $setuju ? 'setujui' : 'tolak', 'menunggu_persetujuan', $status, $catatan, null, $sekarang);
        });
    }

    /** Pemohon membatalkan sebelum diputus. Tetap boleh saat mode pemeliharaan. */
    public static function batalkan(PDO $db, $userId, $id, $versi, $alasan, DateTimeImmutable $sekarang)
    {
        $peng = self::pengaturan($db);
        $alasan = self::wajibTeks($alasan, 'Alasan pembatalan', 3);

        self::transaksi($db, function () use ($db, $peng, $userId, $id, $versi, $alasan, $sekarang) {
            $p = self::kunci($db, $id, $versi, array('menunggu_persetujuan', 'diajukan_ke_pta'));
            if ((int) $p['pemohon_user_id'] !== (int) $userId) {
                throw new IzinException('Hanya pemohon yang dapat membatalkan permohonan ini.');
            }
            $kini = self::utc($sekarang);
            $sebelum = $p['status'];
            $t = self::tahapAktif($db, $id);
            if ($t) {
                $db->prepare('UPDATE izin_tahap SET status = \'dibatalkan\', diputus_at = ? WHERE id = ?')->execute(array($kini, $t['id']));
                self::batalkanWaTahap($db, $t['id']);
            }
            self::ubahStatus($db, $p, 'dibatalkan', $userId, $alasan, $kini);
            $p = self::muat($db, $id);
            if ($t) {
                self::beritahu($db, $peng, $t['pemberi_user_id'], $p, 'dibatalkan', null, $sekarang);
            }
            self::audit($db, $id, $userId, 'batalkan', $sebelum, 'dibatalkan', $alasan, null, $sekarang);
        });
    }

    /** Pemberi izin (atau Ketua untuk izin via PTA) mencabut izin yang sudah disetujui. */
    public static function cabut(PDO $db, $userId, $id, $versi, $alasan, DateTimeImmutable $sekarang)
    {
        $peng = self::pengaturan($db);
        self::wajibAktif($peng);
        $alasan = self::wajibTeks($alasan, 'Alasan pencabutan', 3);

        self::transaksi($db, function () use ($db, $peng, $userId, $id, $versi, $alasan, $sekarang) {
            $p = self::kunci($db, $id, $versi, array('disetujui'));
            if ($p['pemberi_eksternal'] !== null) {
                $boleh = (int) $p['pemohon_user_id'] === (int) $userId;
            } else {
                $s = $db->prepare('SELECT pemberi_user_id FROM izin_tahap WHERE permohonan_id = ? AND status = \'selesai\' ORDER BY id DESC LIMIT 1');
                $s->execute(array($id));
                $boleh = (int) $s->fetchColumn() === (int) $userId;
            }
            if (!$boleh) {
                throw new IzinException('Hanya pemberi izin yang dapat mencabut izin ini.');
            }
            self::ubahStatus($db, $p, 'dicabut', $userId, $alasan, self::utc($sekarang));
            $p = self::muat($db, $id);
            if ((int) $p['pemohon_user_id'] !== (int) $userId) {
                self::beritahu($db, $peng, $p['pemohon_user_id'], $p, 'dicabut', null, $sekarang);
            }
            if ($p['mengetahui_user_id']) {
                self::beritahu($db, $peng, $p['mengetahui_user_id'], $p, 'dicabut', null, $sekarang);
            }
            self::audit($db, $id, $userId, 'cabut', 'disetujui', 'dicabut', $alasan, null, $sekarang);
        });
    }

    /** Izin Ketua: Ketua mencatat jawaban KPTA. */
    public static function catatHasilPta(PDO $db, $userId, $id, $versi, $disetujui, $catatan, DateTimeImmutable $sekarang)
    {
        $peng = self::pengaturan($db);
        self::wajibAktif($peng);
        $catatan = self::wajibTeks($catatan, 'Catatan hasil dari PTA (mis. nomor/tanggal jawaban)', 3);

        self::transaksi($db, function () use ($db, $peng, $userId, $id, $versi, $disetujui, $catatan, $sekarang) {
            $p = self::kunci($db, $id, $versi, array('diajukan_ke_pta'));
            if ((int) $p['pemohon_user_id'] !== (int) $userId) {
                throw new IzinException('Hanya pemohon (Ketua) yang dapat mencatat hasil dari PTA.');
            }
            $status = $disetujui ? 'disetujui' : 'ditolak';
            self::ubahStatus($db, $p, $status, $userId, $catatan, self::utc($sekarang));
            $p = self::muat($db, $id);
            if ($p['mengetahui_user_id']) {
                self::beritahu($db, $peng, $p['mengetahui_user_id'], $p, 'pta_' . $status, null, $sekarang);
            }
            self::audit($db, $id, $userId, 'catat_hasil_pta', 'diajukan_ke_pta', $status, $catatan, null, $sekarang);
        });
    }

    /**
     * Admin: hitung ulang rute dengan ketersediaan saat ini; bila pemberi
     * berubah, tugas lama diganti. Tidak bisa memilih orang bebas.
     */
    public static function alihkan(PDO $db, $adminUserId, $id, $versi, $alasan, DateTimeImmutable $sekarang)
    {
        $peng = self::pengaturan($db);
        self::wajibAktif($peng);
        $alasan = self::wajibTeks($alasan, 'Alasan pengalihan', 3);

        self::transaksi($db, function () use ($db, $peng, $adminUserId, $id, $versi, $alasan, $sekarang) {
            $p = self::kunci($db, $id, $versi, array('menunggu_persetujuan'));
            $lama = self::tahapAktif($db, $id);
            $pejabat = self::pejabat($db);
            $rute = Routing::tentukan($p['kelompok'], $pejabat, self::berhalangan($db, $sekarang), $p['pemohon_pegawai_id']);
            if (isset($rute['error'])) {
                throw new IzinException($rute['error']);
            }
            $baru = $pejabat[$rute['pemberi']];
            if ($lama && (int) $baru['pegawai_id'] === (int) $lama['pemberi_pegawai_id']) {
                throw new IzinException('Rute saat ini tetap ke ' . $baru['nama'] . '. Catat dulu pejabat yang berhalangan bila perlu dialihkan.');
            }
            $kini = self::utc($sekarang);
            if ($lama) {
                $db->prepare('UPDATE izin_tahap SET status = \'diganti\', diputus_at = ?, catatan = ? WHERE id = ?')
                    ->execute(array($kini, $alasan, $lama['id']));
                self::batalkanWaTahap($db, $lama['id']);
            }
            $tahapId = self::buatTahap($db, $id, $baru, $lama ? $lama['id'] : null, $kini);
            $db->prepare('UPDATE izin_permohonan SET versi = versi + 1 WHERE id = ?')->execute(array($id));
            self::beritahu($db, $peng, $baru['user_id'], self::muat($db, $id), 'tugas_' . $tahapId, $tahapId, $sekarang);
            self::audit($db, $id, $adminUserId, 'alihkan', $p['status'], $p['status'], $alasan,
                array('dari' => $lama ? $lama['pemberi_nama'] : null, 'ke' => $baru['nama']), $sekarang);
        });
    }

    // ------------------------------------------------------------------
    // Pesan
    // ------------------------------------------------------------------

    public static function pesan($peristiwa, array $p)
    {
        $jenis = self::LABEL_JENIS[$p['jenis']];
        $jam = function ($t) {
            return str_replace(':', '.', substr($t, 0, 5));
        };
        $kapan = Formatter::tanggalIndonesia($p['tanggal']) . ' pukul ' . $jam($p['jam_mulai'])
            . ($p['jam_kembali'] ? '–' . $jam($p['jam_kembali']) : '');
        $nama = $p['pemohon_nama'];
        if (strpos($peristiwa, 'tugas_') === 0) {
            return "$nama mengajukan izin $jenis $kapan. Mohon tinjau.";
        }
        $daftar = array(
            'diajukan_pta' => "$nama mengajukan izin $jenis $kapan kepada KPTA.",
            'disetujui' => "Izin $jenis Anda $kapan disetujui.",
            'ditolak' => "Izin $jenis Anda $kapan ditolak.",
            'mengetahui' => "$nama mendapat izin $jenis $kapan.",
            'dibatalkan' => "$nama membatalkan permohonan izin $jenis $kapan.",
            'dicabut' => "Izin $jenis $nama $kapan dicabut.",
            'pta_disetujui' => "Izin $jenis $nama $kapan disetujui KPTA.",
            'pta_ditolak' => "Izin $jenis $nama $kapan ditolak KPTA.",
        );
        return $daftar[$peristiwa];
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private static function transaksi(PDO $db, callable $kerja)
    {
        $db->beginTransaction();
        try {
            $hasil = $kerja();
            $db->commit();
            return $hasil;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function wajibAktif(array $peng)
    {
        if ($peng['mode'] !== 'aktif') {
            throw new IzinException($peng['mode'] === 'pemeliharaan'
                ? 'Modul izin sedang dalam pemeliharaan. Hubungi pejabat secara langsung.'
                : 'Modul izin belum diaktifkan.');
        }
    }

    private static function kunci(PDO $db, $id, $versi, array $statusBoleh)
    {
        $s = $db->prepare('SELECT * FROM izin_permohonan WHERE id = ? FOR UPDATE');
        $s->execute(array((int) $id));
        $p = $s->fetch();
        if (!$p) {
            throw new IzinException('Permohonan tidak ditemukan.');
        }
        if ((int) $p['versi'] !== (int) $versi) {
            throw new IzinException('Permohonan sudah berubah sejak halaman dibuka. Muat ulang untuk melihat status terbaru.');
        }
        if (!in_array($p['status'], $statusBoleh, true)) {
            throw new IzinException('Aksi tidak bisa dilakukan pada status "' . self::LABEL_STATUS[$p['status']] . '".');
        }
        return $p;
    }

    private static function tahapAktif(PDO $db, $permohonanId)
    {
        $s = $db->prepare('SELECT * FROM izin_tahap WHERE permohonan_id = ? AND status = \'menunggu\' FOR UPDATE');
        $s->execute(array((int) $permohonanId));
        return $s->fetch() ?: null;
    }

    private static function cekMasihBerwenang(PDO $db, array $t, DateTimeImmutable $sekarang)
    {
        $kini = self::utc($sekarang);
        $s = $db->prepare(
            'SELECT u.status_aktif = 1 AS aktif,
                    EXISTS (SELECT 1 FROM izin_pejabat WHERE pegawai_id = ?) AS pejabat,
                    EXISTS (SELECT 1 FROM izin_berhalangan WHERE pegawai_id = ? AND dibatalkan_at IS NULL AND mulai <= ? AND selesai > ?) AS halangan
             FROM user_login u WHERE u.id = ?'
        );
        $s->execute(array($t['pemberi_pegawai_id'], $t['pemberi_pegawai_id'], $kini, $kini, $t['pemberi_user_id']));
        $r = $s->fetch();
        if (!$r || !$r['aktif'] || !$r['pejabat'] || $r['halangan']) {
            throw new IzinException('Kewenangan Anda untuk permohonan ini berubah (nonaktif, bukan pejabat lagi, atau tercatat berhalangan). Admin perlu mengalihkan permohonan.');
        }
    }

    private static function buatTahap(PDO $db, $permohonanId, array $pejabat, $digantiDari, $kini)
    {
        $db->prepare(
            'INSERT INTO izin_tahap (permohonan_id, pemberi_user_id, pemberi_pegawai_id, pemberi_nama, pemberi_jabatan, diganti_dari, dibuat_at)
             VALUES (?,?,?,?,?,?,?)'
        )->execute(array($permohonanId, $pejabat['user_id'], $pejabat['pegawai_id'], $pejabat['nama'], $pejabat['jabatan'], $digantiDari, $kini));
        return (int) $db->lastInsertId();
    }

    private static function ubahStatus(PDO $db, array $p, $status, $userId, $catatan, $kini)
    {
        $db->prepare(
            'UPDATE izin_permohonan SET status = ?, versi = versi + 1, diputus_at = ?, diputus_oleh = ?, catatan_keputusan = ? WHERE id = ?'
        )->execute(array($status, $kini, $userId, self::kosongJadiNull($catatan), $p['id']));
    }

    /** Pesan tugas yang belum terkirim jadi usang begitu tahapnya diputus/diganti. */
    private static function batalkanWaTahap(PDO $db, $tahapId)
    {
        $db->prepare('UPDATE izin_wa_outbox SET status = \'dibatalkan\' WHERE tahap_id = ? AND status = \'antre\'')->execute(array($tahapId));
    }

    private static function beritahu(PDO $db, array $peng, $penerimaUserId, array $p, $peristiwa, $tahapId, DateTimeImmutable $sekarang)
    {
        $kini = self::utc($sekarang);
        $pesan = self::pesan($peristiwa, $p);
        $db->prepare(
            'INSERT IGNORE INTO izin_notifikasi (penerima_user_id, permohonan_id, peristiwa, pesan, dibuat_at) VALUES (?,?,?,?,?)'
        )->execute(array($penerimaUserId, $p['id'], $peristiwa, $pesan, $kini));

        if (!(int) $peng['wa_aktif']) {
            return;
        }
        $s = $db->prepare('SELECT pg.no_telp FROM user_login u JOIN pegawai pg ON pg.nip = u.nip WHERE u.id = ?');
        $s->execute(array($penerimaUserId));
        $nomor = Wa::formatNomor($s->fetchColumn());
        $db->prepare(
            'INSERT IGNORE INTO izin_wa_outbox (penerima_user_id, permohonan_id, peristiwa, tahap_id, nomor, pesan, status, coba_lagi_at, error, dibuat_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute(array(
            $penerimaUserId, $p['id'], $peristiwa, $tahapId, $nomor,
            'AURA: ' . $pesan . ' ' . self::urlDetail($p['id']),
            $nomor === null ? 'gagal' : 'antre', $kini, $nomor === null ? 'Nomor WA tidak tersedia' : null, $kini,
        ));
    }

    private static function audit(PDO $db, $permohonanId, $aktorUserId, $aksi, $sebelum, $sesudah, $alasan, $data, DateTimeImmutable $sekarang)
    {
        $db->prepare(
            'INSERT INTO izin_audit (permohonan_id, aktor_user_id, aktor_nama, aksi, status_sebelum, status_sesudah, data, alasan, dibuat_at)
             VALUES (?, ?, (SELECT nama_tampilan FROM user_login WHERE id = ?), ?, ?, ?, ?, ?, ?)'
        )->execute(array(
            $permohonanId, $aktorUserId, $aktorUserId, $aksi, $sebelum, $sesudah,
            $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE), self::kosongJadiNull($alasan), self::utc($sekarang),
        ));
    }

    /** Perubahan pengaturan/pemetaan/pejabat/berhalangan oleh admin (permohonan_id NULL). */
    public static function auditKonfigurasi(PDO $db, $adminUserId, $aksi, array $data, DateTimeImmutable $sekarang)
    {
        self::audit($db, null, $adminUserId, $aksi, null, null, null, $data, $sekarang);
    }

    /** Hanya permohonan milik sendiri yang boleh jadi asal salinan. */
    private static function asalMilikSendiri(PDO $db, $asalId, $userId)
    {
        if (!$asalId) {
            return null;
        }
        $s = $db->prepare('SELECT id FROM izin_permohonan WHERE id = ? AND pemohon_user_id = ?');
        $s->execute(array((int) $asalId, (int) $userId));
        return $s->fetchColumn() ?: null;
    }

    public static function urlDetail($id)
    {
        static $dasar = null;
        if ($dasar === null) {
            $config = require __DIR__ . '/../../config/config.php';
            $dasar = isset($config['url_aplikasi']) ? rtrim($config['url_aplikasi'], '/') . '/' : '';
        }
        return $dasar . 'izin/detail.php?id=' . (int) $id;
    }

    private static function wajibTeks($teks, $label, $min)
    {
        $teks = trim((string) $teks);
        $panjang = mb_strlen($teks);
        if ($panjang < $min || $panjang > 2000) {
            throw new IzinException($label . ' wajib diisi (' . $min . '–2.000 karakter).');
        }
        return $teks;
    }

    private static function kosongJadiNull($teks)
    {
        $teks = trim((string) $teks);
        return $teks === '' ? null : $teks;
    }

    private static function daftarIn(array $nilai)
    {
        return "'" . implode("','", $nilai) . "'"; // konstanta internal, bukan input pengguna
    }

    public static function utc(DateTimeImmutable $t)
    {
        return $t->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
