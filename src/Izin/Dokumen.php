<?php

namespace Aurat\Izin;

use Aurat\DocxGenerator;
use Aurat\Formatter;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * Surat izin final (rancangan bagian 11): nomor register + snapshot nilai
 * dibekukan dulu (siapkan), baru dirender ke PDF (render). Gagal render
 * tidak menyentuh keputusan; render ulang memakai nomor & snapshot yang sama.
 *
 * Arsip disimpan di config['direktori_arsip_izin'] - di produksi WAJIB di
 * luar web root (web root AURA = root repo).
 */
class Dokumen
{
    const TEMPLATE = array(
        'pegawai' => 'templates/izin_modul_pegawai.docx',
        'hakim' => 'templates/izin_modul_hakim.docx',
    );
    const ZONA_SINGKAT = array('Asia/Jakarta' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT');
    const HARI = array(1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu');

    /** @var string|null untuk tes */
    public static $direktoriOverride = null;

    public static function direktori()
    {
        if (self::$direktoriOverride !== null) {
            $dir = self::$direktoriOverride;
        } else {
            $config = require __DIR__ . '/../../config/config.php';
            $dir = isset($config['direktori_arsip_izin']) ? $config['direktori_arsip_izin'] : __DIR__ . '/../../storage/izin';
        }
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
            throw new RuntimeException('Direktori arsip izin tidak dapat dibuat.');
        }
        return rtrim($dir, '/');
    }

    public static function muat(PDO $db, $permohonanId)
    {
        $s = $db->prepare('SELECT * FROM izin_dokumen WHERE permohonan_id = ?');
        $s->execute(array((int) $permohonanId));
        return $s->fetch() ?: null;
    }

    /** Siapkan (bila belum) lalu render. Dipanggil setelah keputusan di-commit; tidak pernah throw. */
    public static function terbitkan(PDO $db, $permohonanId, DateTimeImmutable $sekarang)
    {
        try {
            self::siapkan($db, $permohonanId, $sekarang);
            return self::render($db, $permohonanId);
        } catch (\Throwable $e) {
            error_log('[AURA izin] terbitkan #' . (int) $permohonanId . ': ' . $e->getMessage());
            return self::muat($db, $permohonanId);
        }
    }

    /** Reservasi nomor + snapshot. Idempoten: dokumen yang sudah ada dikembalikan apa adanya. */
    public static function siapkan(PDO $db, $permohonanId, DateTimeImmutable $sekarang)
    {
        $db->beginTransaction();
        try {
            $s = $db->prepare('SELECT * FROM izin_permohonan WHERE id = ? FOR UPDATE');
            $s->execute(array((int) $permohonanId));
            $p = $s->fetch();
            $bolehTerbit = $p && $p['status'] === 'disetujui';
            if (!$bolehTerbit) {
                throw new IzinException('Surat hanya dapat diterbitkan untuk izin yang disetujui.');
            }
            $ada = self::muat($db, $permohonanId);
            if ($ada) {
                $db->commit();
                return $ada;
            }

            $tahun = (int) substr($p['tanggal'], 0, 4);
            $s = $db->prepare('SELECT COALESCE(MAX(urutan), 0) + 1 FROM izin_dokumen WHERE tahun = ? FOR UPDATE');
            $s->execute(array($tahun));
            $urutan = (int) $s->fetchColumn();
            $nomor = 'IZN/' . $tahun . '/' . $urutan;

            $varian = in_array($p['kelompok'], array('hakim', 'wakil', 'ketua'), true) ? 'hakim' : 'pegawai';
            $sumber = __DIR__ . '/../../' . self::TEMPLATE[$varian];
            $sha = hash_file('sha256', $sumber);
            $beku = self::direktori() . '/template/' . $sha . '.docx';
            if (!is_file($beku)) {
                if (!is_dir(dirname($beku))) {
                    mkdir(dirname($beku), 0700, true);
                }
                if (!copy($sumber, $beku . '.tmp') || !rename($beku . '.tmp', $beku)) {
                    throw new RuntimeException('Gagal menyalin template ke arsip.');
                }
            }

            $db->prepare(
                'INSERT INTO izin_dokumen (permohonan_id, tahun, urutan, nomor, status, template_berkas, template_sha256, snapshot_nilai, dibuat_at)
                 VALUES (?,?,?,?,\'diproses\',?,?,?,?)'
            )->execute(array(
                $p['id'], $tahun, $urutan, $nomor, self::TEMPLATE[$varian], $sha,
                json_encode(self::nilai($db, $p, $varian, $nomor, $sekarang), JSON_UNESCAPED_UNICODE),
                Permohonan::utc($sekarang),
            ));
            $db->commit();
            return self::muat($db, $permohonanId);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Render snapshot ke PDF. Satu proses render sekaligus (flock) - VPS kecil. */
    public static function render(PDO $db, $permohonanId)
    {
        $d = self::muat($db, $permohonanId);
        if (!$d) {
            throw new IzinException('Dokumen belum disiapkan.');
        }
        $dir = self::direktori();
        if ($d['status'] === 'siap' && is_file($dir . '/' . $d['path_pdf'])) {
            return $d;
        }

        $kunci = fopen($dir . '/.render.lock', 'c');
        flock($kunci, LOCK_EX);
        $kerja = null;
        try {
            $d = self::muat($db, $permohonanId); // proses lain mungkin sudah selesai selama menunggu kunci
            if ($d['status'] === 'siap' && is_file($dir . '/' . $d['path_pdf'])) {
                return $d;
            }
            $relatif = $d['tahun'] . '/' . (int) $d['permohonan_id'] . '.pdf';
            $final = $dir . '/' . $relatif;

            // Crash setelah berkas dipindah tapi sebelum DB diperbarui: rekonsiliasi, jangan render ulang.
            if (!is_file($final)) {
                $kerja = $dir . '/tmp/' . bin2hex(random_bytes(8));
                mkdir($kerja, 0700, true);
                $nilai = json_decode($d['snapshot_nilai'], true);
                $gambar = array();
                foreach (isset($nilai['_ttd']) ? $nilai['_ttd'] : array() as $ph => $berkas) {
                    if ($berkas !== null && is_file($dir . '/ttd/' . basename($berkas))) {
                        $gambar[$ph] = self::ukuranTtd($dir . '/ttd/' . basename($berkas));
                    }
                }
                unset($nilai['_ttd']);
                $docx = DocxGenerator::generate($dir . '/template/' . $d['template_sha256'] . '.docx', $nilai, array(), $gambar);
                rename($docx, $kerja . '/surat.docx');
                self::kePdf($kerja);
                if (!is_dir(dirname($final))) {
                    mkdir(dirname($final), 0700, true);
                }
                if (!rename($kerja . '/surat.pdf', $final)) {
                    throw new RuntimeException('Gagal memindahkan PDF ke arsip.');
                }
            }
            $db->prepare('UPDATE izin_dokumen SET status = \'siap\', path_pdf = ?, sha256 = ?, error = NULL, siap_at = ? WHERE id = ?')
                ->execute(array($relatif, hash_file('sha256', $final), gmdate('Y-m-d H:i:s'), $d['id']));
        } catch (\Throwable $e) {
            $db->prepare('UPDATE izin_dokumen SET status = \'gagal\', error = ? WHERE id = ?')
                ->execute(array(substr($e->getMessage(), 0, 250), $d['id']));
        } finally {
            if ($kerja !== null) {
                self::hapusFolder($kerja);
            }
            flock($kunci, LOCK_UN);
            fclose($kunci);
        }
        return self::muat($db, $permohonanId);
    }

    /** Muat TTD di kotak maks 6 x 3 cm tanpa mengubah perbandingan sisi gambar. */
    public static function ukuranTtd($path)
    {
        // PhpWord hanya memahami piksel (96 dpi): 6 cm = 227 px, 3 cm = 113 px.
        $info = getimagesize($path);
        $w = 227; $h = 113;
        if ($info && $info[0] > 0 && $info[1] > 0) {
            $skala = min($w / $info[0], $h / $info[1]);
            $w = $info[0] * $skala; $h = $info[1] * $skala;
        }
        return array('path' => $path, 'width' => (int) round($w), 'height' => (int) round($h), 'ratio' => false);
    }

    public static function pathPdf(array $d)
    {
        return self::direktori() . '/' . $d['path_pdf'];
    }

    // ------------------------------------------------------------------

    private static function nilai(PDO $db, array $p, $varian, $nomor, DateTimeImmutable $sekarang)
    {
        $tz = new DateTimeZone($p['zona_waktu']);
        $zona = isset(self::ZONA_SINGKAT[$p['zona_waktu']]) ? self::ZONA_SINGKAT[$p['zona_waktu']] : $p['zona_waktu'];
        $kini = $sekarang->setTimezone($tz);
        $tgl = DateTimeImmutable::createFromFormat('!Y-m-d', $p['tanggal'], $tz);
        $hari = self::HARI[(int) $tgl->format('N')];
        $mulai = str_replace(':', '.', substr($p['jam_mulai'], 0, 5));
        $selesai = $p['jam_kembali'] ? str_replace(':', '.', substr($p['jam_kembali'], 0, 5)) : 'selesai jam kerja';

        $s = $db->prepare(
            'SELECT t.pemberi_nama, t.pemberi_jabatan, t.diputus_at, pg.nip, pg.unit_kerja, pg.ttd_berkas
             FROM izin_tahap t JOIN pegawai pg ON pg.id = t.pemberi_pegawai_id
             WHERE t.permohonan_id = ? AND t.status = \'selesai\' ORDER BY t.id DESC LIMIT 1'
        );
        $s->execute(array($p['id']));
        $t = $s->fetch();
        if (!$t) {
            throw new RuntimeException('Tahap persetujuan tidak ditemukan.');
        }
        $pemberi = array('nama' => $t['pemberi_nama'], 'jabatan' => $t['pemberi_jabatan'], 'nip' => $t['nip'], 'unit_kerja' => $t['unit_kerja']);
        $keterangan = 'Disetujui melalui AURA oleh ' . $t['pemberi_nama'] . ' pada ' . self::waktuLokal($t['diputus_at'], $tz) . ' ' . $zona . '. Nomor register ' . $nomor . '.';

        // TTD dibekukan lewat nama berkas per isi (ttd/<sha256>.<ext>, tidak pernah ditimpa).
        $ttdMengetahui = null;
        if ($p['mengetahui_user_id']) {
            $s = $db->prepare('SELECT pg.ttd_berkas FROM user_login u JOIN pegawai pg ON pg.nip = u.nip WHERE u.id = ?');
            $s->execute(array($p['mengetahui_user_id']));
            $ttdMengetahui = $s->fetchColumn() ?: null;
        }
        $umum = array(
            '_ttd' => array('ttd_pemberi' => $t['ttd_berkas'] ?: null, 'ttd_mengetahui' => $ttdMengetahui),
            'ttd_pemberi' => '',
            'ttd_mengetahui' => '',
            'pemberi_izin_nama_lengkap' => $pemberi['nama'],
            'pemberi_izin_jabatan' => (string) $pemberi['jabatan'],
            'penerima_izin_nama_lengkap' => $p['pemohon_nama'],
            'penerima_izin_nip' => $p['pemohon_nip'],
            'keperluan_izin' => $p['alasan'],
            'tanggal_surat' => Formatter::tanggalIndonesia($kini->format('Y-m-d')),
            'keterangan_aura' => $keterangan,
        );
        if ($varian === 'hakim') {
            return $umum + array(
                'hari' => $hari,
                'tanggal_izin_keluar' => Formatter::tanggalIndonesia($p['tanggal']),
                'jam_mulai_izin' => $mulai,
                'jam_selesai_izin' => $p['jam_kembali'] ? $selesai . ' ' . $zona : $selesai,
            );
        }
        return $umum + array(
            'pemberi_izin_nip' => (string) $pemberi['nip'],
            'pemberi_izin_unit_kerja' => (string) $pemberi['unit_kerja'],
            'penerima_izin_jabatan' => (string) $p['pemohon_jabatan'],
            'penerima_izin_unit_kerja' => (string) $p['pemohon_unit_kerja'],
            'waktu_izin' => $hari . ', ' . Formatter::tanggalIndonesia($p['tanggal']) . ', pukul ' . $mulai . ' ' . $zona . ' s.d. '
                . ($p['jam_kembali'] ? $selesai . ' ' . $zona : $selesai . ' (pulang awal)'),
            // Mengetahui selalu Wakil (Routing hanya memberi 'wakil'); kosong = blok tidak tampil.
            'mengetahui_judul' => $p['mengetahui_nama'] !== null ? 'Mengetahui' : '',
            'mengetahui_jabatan' => $p['mengetahui_nama'] !== null ? 'Wakil Ketua Pengadilan Agama Rantau' : '',
            'mengetahui_nama_lengkap' => $p['mengetahui_nama'] !== null ? $p['mengetahui_nama'] : '',
        );
    }

    private static function waktuLokal($utc, DateTimeZone $tz)
    {
        $t = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return Formatter::tanggalIndonesia($t->setTimezone($tz)->format('Y-m-d')) . ' pukul ' . $t->setTimezone($tz)->format('H.i');
    }

    /** soffice headless dengan profil sementara + batas waktu 60 detik. */
    private static function kePdf($kerja)
    {
        $soffice = getenv('AURA_SOFFICE') ?: 'soffice';
        $cmd = array($soffice, '-env:UserInstallation=file://' . $kerja . '/profil', '--headless', '--convert-to', 'pdf', '--outdir', $kerja, $kerja . '/surat.docx');
        // cwd = folder kerja: skrip soffice melakukan cd ke folder aktif; folder lain bisa tak terbaca user aplikasi.
        $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipa, $kerja, array('PATH' => '/usr/local/bin:/usr/bin:/bin:/opt/homebrew/bin', 'HOME' => $kerja));
        if (!is_resource($proc)) {
            throw new RuntimeException('soffice tidak dapat dijalankan.');
        }
        $batas = time() + 60;
        do {
            $st = proc_get_status($proc);
            if (!$st['running']) {
                break;
            }
            usleep(200000);
        } while (time() < $batas);
        if ($st['running']) {
            proc_terminate($proc, 9);
            proc_close($proc);
            throw new RuntimeException('Konversi PDF melebihi batas waktu.');
        }
        $err = stream_get_contents($pipa[2]);
        fclose($pipa[1]);
        fclose($pipa[2]);
        proc_close($proc);
        if (!is_file($kerja . '/surat.pdf') || filesize($kerja . '/surat.pdf') < 1000) {
            throw new RuntimeException('Konversi PDF gagal: ' . substr(trim($err), 0, 150));
        }
    }

    private static function hapusFolder($dir)
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
