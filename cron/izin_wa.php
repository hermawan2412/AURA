<?php
// Kirim antrean WhatsApp modul izin. Dipasang tiap menit:
//   * * * * * <user-app> php /var/www/aura/cron/izin_wa.php >> /var/log/aura/izin_wa.log 2>&1
// flock: run berikutnya tidak menumpuk bila Fonnte lambat.
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Aurat\Database;
use Aurat\Izin\Dokumen;
use Aurat\Izin\Wa;

$kunci = fopen(sys_get_temp_dir() . '/aura-izin-wa.lock', 'c');
if (!flock($kunci, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$hasil = Wa::prosesAntrean(Database::pdo(), new DateTimeImmutable('now', new DateTimeZone('UTC')));
// Detak untuk Dasbor Izin (kapan cron terakhir jalan).
@touch(Dokumen::direktori() . '/.cron-wa');
if (array_sum($hasil) > 0) {
    echo '[' . date('c') . '] ' . json_encode($hasil) . "\n";
}
