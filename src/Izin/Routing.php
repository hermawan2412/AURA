<?php

namespace Aurat\Izin;

/**
 * Matriks rute izin (rancangan bagian 8). Fungsi murni - tanpa DB - supaya
 * bisa dites langsung (tests/izin-routing.php).
 *
 * Mengetahui = pemberitahuan saja (keputusan pengguna 2026-11-16), jadi
 * tidak pernah memblokir: kalau Wakil kosong/berhalangan, pemberitahuan
 * tetap ke Wakil yang tercatat atau dilewati.
 */
class Routing
{
    const KELOMPOK = array('ketua', 'wakil', 'hakim', 'panitera', 'sekretaris', 'kepaniteraan', 'kesekretariatan');

    /**
     * @param string $kelompok kelompok pemohon (izin_pemetaan_pegawai)
     * @param array  $pejabat  kode => array('pegawai_id'=>int, 'user_aktif'=>bool); kode yang belum diisi tidak ada
     * @param int[]  $berhalangan pegawai_id yang sedang berhalangan saat submit
     * @param int    $pemohonPegawaiId
     * @return array array('pemberi'=>kode, 'mengetahui'=>kode|null) atau array('error'=>pesan)
     */
    public static function tentukan($kelompok, array $pejabat, array $berhalangan, $pemohonPegawaiId)
    {
        $pemohonPegawaiId = (int) $pemohonPegawaiId;
        $berhalangan = array_map('intval', $berhalangan);

        // Pejabat yang sama dengan pemohon = konfigurasi salah, jangan dilewati diam-diam.
        foreach ($pejabat as $kode => $p) {
            if ((int) $p['pegawai_id'] === $pemohonPegawaiId && $kode !== $kelompok) {
                return array('error' => 'Pemohon tercatat sebagai pejabat ' . $kode . ' tetapi dipetakan ke kelompok ' . $kelompok . '. Hubungi admin.');
            }
        }

        $tersedia = function ($kode) use ($pejabat, $berhalangan, $pemohonPegawaiId) {
            return isset($pejabat[$kode])
                && $pejabat[$kode]['user_aktif']
                && (int) $pejabat[$kode]['pegawai_id'] !== $pemohonPegawaiId
                && !in_array((int) $pejabat[$kode]['pegawai_id'], $berhalangan, true);
        };
        // Penerima pemberitahuan: cukup tercatat dan punya akun aktif; berhalangan tidak masalah.
        $mengetahui = function ($kode) use ($pejabat, $pemohonPegawaiId) {
            return isset($pejabat[$kode]) && $pejabat[$kode]['user_aktif']
                && (int) $pejabat[$kode]['pegawai_id'] !== $pemohonPegawaiId ? $kode : null;
        };
        $pertamaTersedia = function (array $urutan) use ($tersedia) {
            foreach ($urutan as $kode) {
                if ($tersedia($kode)) {
                    return $kode;
                }
            }
            return null;
        };

        switch ($kelompok) {
            case 'ketua':
                // Ketua menandatangani izinnya sendiri, tidak dialihkan ke Wakil (keputusan pengguna 2026-11-17).
                if (isset($pejabat['ketua']) && $pejabat['ketua']['user_aktif'] && (int) $pejabat['ketua']['pegawai_id'] === $pemohonPegawaiId) {
                    return array('pemberi' => 'ketua', 'mengetahui' => null, 'mandiri' => true);
                }
                return array('error' => 'Akun Anda dipetakan sebagai Ketua tetapi bukan Ketua yang tercatat. Hubungi admin.');

            case 'hakim':
                // Ketua berhalangan -> Wakil (keputusan pengguna 2026-11-16).
                $pemberi = $pertamaTersedia(array('ketua', 'wakil'));
                if ($pemberi !== null) {
                    return array('pemberi' => $pemberi, 'mengetahui' => null);
                }
                return array('error' => 'Ketua dan Wakil tidak tersedia (kosong, nonaktif, atau berhalangan). Hubungi admin.');

            case 'wakil':
                if ($tersedia('ketua')) {
                    return array('pemberi' => 'ketua', 'mengetahui' => null);
                }
                // Pengganti Ketua adalah Wakil sendiri - tidak boleh menyetujui permohonannya sendiri.
                return array('error' => 'Ketua tidak tersedia, dan Wakil tidak dapat menyetujui izinnya sendiri. Hubungi Ketua langsung.');

            case 'kepaniteraan':
            case 'kesekretariatan':
                $normal = $kelompok === 'kepaniteraan' ? 'panitera' : 'sekretaris';
                if ($tersedia($normal)) {
                    return array('pemberi' => $normal, 'mengetahui' => $mengetahui('wakil'));
                }
                $pemberi = $pertamaTersedia(array('wakil', 'ketua'));
                if ($pemberi !== null) {
                    return array('pemberi' => $pemberi, 'mengetahui' => null);
                }
                return array('error' => 'Tidak ada pejabat pemberi izin yang tersedia. Hubungi admin.');

            case 'panitera':
            case 'sekretaris':
                $pemberi = $pertamaTersedia(array('wakil', 'ketua'));
                if ($pemberi !== null) {
                    return array('pemberi' => $pemberi, 'mengetahui' => null);
                }
                return array('error' => 'Wakil dan Ketua tidak tersedia. Hubungi admin.');
        }

        return array('error' => 'Akun Anda belum dipetakan ke kelompok izin. Hubungi admin.');
    }
}
