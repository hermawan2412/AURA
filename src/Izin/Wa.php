<?php

namespace Aurat\Izin;

/**
 * Kirim WhatsApp via Fonnte - porting kecil dari restu/includes/whatsapp.php
 * (provider & format nomor sama). Token milik AURA sendiri
 * (izin_pengaturan.wa_token), bukan dibaca dari DB RESTU.
 */
class Wa
{
    const ENDPOINT = 'https://api.fonnte.com/send';

    /** @var callable|null pengganti pengiriman untuk tes: function($nomor, $pesan) => array(bool ok, string|null error) */
    public static $pengirimPalsu = null;

    /** "0812..", "+62 812-..", "812.." -> "62812..". null bila kosong/tidak wajar. */
    public static function formatNomor($no)
    {
        $digit = preg_replace('/\D+/', '', (string) $no);
        if ($digit === '') {
            return null;
        }
        if ($digit[0] === '0') {
            $digit = '62' . substr($digit, 1);
        } elseif (strpos($digit, '62') !== 0) {
            $digit = '62' . $digit;
        }
        return strlen($digit) >= 10 && strlen($digit) <= 15 ? $digit : null;
    }

    /**
     * @return array array(bool terkirim, string|null error ringkas tanpa rahasia, bool boleh diulang)
     * Timeout dianggap TIDAK PASTI (pesan mungkin sudah diterima Fonnte) -> tidak diulang buta.
     */
    public static function kirim($token, $nomor, $pesan)
    {
        if (self::$pengirimPalsu !== null) {
            return call_user_func(self::$pengirimPalsu, $nomor, $pesan);
        }

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => array('Authorization: ' . $token),
            CURLOPT_POSTFIELDS => array('target' => $nomor, 'message' => $pesan),
        ));
        $respons = curl_exec($ch);
        $kodeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errNo = curl_errno($ch);
        curl_close($ch);

        if ($respons === false) {
            if ($errNo === CURLE_OPERATION_TIMEDOUT) {
                return array(false, 'tidak pasti: batas waktu habis', false);
            }
            return array(false, 'koneksi gagal (curl ' . $errNo . ')', true);
        }
        $data = json_decode($respons, true);
        if ($kodeHttp >= 500) {
            return array(false, 'Fonnte HTTP ' . $kodeHttp, true);
        }
        if ($kodeHttp >= 400 || !is_array($data) || (isset($data['status']) ? $data['status'] : false) !== true) {
            $alasan = is_array($data) && isset($data['reason']) ? (string) $data['reason'] : 'HTTP ' . $kodeHttp;
            return array(false, substr($alasan, 0, 150), false); // token/nomor salah dsb: perlu perbaikan, bukan diulang
        }
        return array(true, null, false);
    }

    /**
     * Proses antrean (dipanggil cron/izin_wa.php tiap menit). Maks 3 percobaan,
     * jeda 1 lalu 5 menit. Pesan tugas yang tahapnya sudah diputus, atau yang
     * tanggal izinnya sudah lewat, dibatalkan - jangan kirim pesan basi.
     * @return array hitungan per hasil
     */
    public static function prosesAntrean(\PDO $db, \DateTimeImmutable $sekarang, $batas = 20)
    {
        $hasil = array('terkirim' => 0, 'gagal' => 0, 'diulang' => 0, 'dibatalkan' => 0, 'ditahan' => 0);
        $peng = Permohonan::pengaturan($db);
        if (!(int) $peng['wa_aktif'] || trim((string) $peng['wa_token']) === '') {
            return $hasil;
        }
        // Batas harian (zona kantor): lewat batas, antrean ditahan sampai besok; pesan yang
        // keburu basi dibatalkan oleh pemeriksaan "usang" di bawah pada putaran berikutnya.
        $sisa = max(1, (int) $peng['wa_batas_harian']) - self::hitungTerkirim($db, $peng, $sekarang, 'hari');
        if ($sisa <= 0) {
            $hasil['ditahan'] = (int) $db->query("SELECT COUNT(*) FROM izin_wa_outbox WHERE status = 'antre'")->fetchColumn();
            return $hasil;
        }
        $batas = min((int) $batas, $sisa);
        $kini = Permohonan::utc($sekarang);
        $hariIni = $sekarang->setTimezone(new \DateTimeZone($peng['zona_waktu']))->format('Y-m-d');
        $s = $db->prepare(
            'SELECT o.*, t.status AS status_tahap, p.tanggal
             FROM izin_wa_outbox o
             JOIN izin_permohonan p ON p.id = o.permohonan_id
             LEFT JOIN izin_tahap t ON t.id = o.tahap_id
             WHERE o.status = \'antre\' AND o.coba_lagi_at <= ? ORDER BY o.id LIMIT ' . (int) $batas
        );
        $s->execute(array($kini));
        $ubah = $db->prepare('UPDATE izin_wa_outbox SET status = ?, percobaan = ?, coba_lagi_at = ?, error = ?, terkirim_at = ? WHERE id = ? AND status = \'antre\'');

        foreach ($s->fetchAll() as $o) {
            $usang = ($o['tahap_id'] !== null && $o['status_tahap'] !== 'menunggu')
                || ($o['tahap_id'] !== null && $o['tanggal'] < $hariIni);
            if ($usang) {
                $ubah->execute(array('dibatalkan', $o['percobaan'], $o['coba_lagi_at'], 'tugas sudah tidak aktif', null, $o['id']));
                $hasil['dibatalkan']++;
                continue;
            }
            $ke = (int) $o['percobaan'] + 1;
            list($ok, $err, $ulang) = self::kirim($peng['wa_token'], $o['nomor'], $o['pesan']);
            if ($ok) {
                $ubah->execute(array('terkirim', $ke, $o['coba_lagi_at'], null, $kini, $o['id']));
                $hasil['terkirim']++;
            } elseif ($ulang && $ke < 3) {
                $jeda = $ke === 1 ? 'PT1M' : 'PT5M';
                $ubah->execute(array('antre', $ke, Permohonan::utc($sekarang->add(new \DateInterval($jeda))), $err, null, $o['id']));
                $hasil['diulang']++;
            } else {
                $ubah->execute(array('gagal', $ke, $o['coba_lagi_at'], $err, null, $o['id']));
                $hasil['gagal']++;
            }
        }
        return $hasil;
    }

    /** Jumlah pesan terkirim sejak awal hari ('hari') atau awal bulan ('bulan') menurut zona kantor. */
    public static function hitungTerkirim(\PDO $db, array $peng, \DateTimeImmutable $sekarang, $rentang)
    {
        $lokal = $sekarang->setTimezone(new \DateTimeZone($peng['zona_waktu']));
        $mulai = $rentang === 'bulan' ? $lokal->modify('first day of this month')->setTime(0, 0) : $lokal->setTime(0, 0);
        $s = $db->prepare("SELECT COUNT(*) FROM izin_wa_outbox WHERE status = 'terkirim' AND terkirim_at >= ?");
        $s->execute(array(Permohonan::utc($mulai)));
        return (int) $s->fetchColumn();
    }
}
