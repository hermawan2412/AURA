<?php
// Daftar izin DISETUJUI hari ini (tanggal kantor) untuk kotak hijau portal lokal.
// Hanya untuk perantara server portal (port/izin.php) dengan header X-Api-Token =
// izin_pengaturan.token_portal. Field minimum: tanpa alasan, NIP, nomor telepon.
// Gagal = HTTP non-200; daftar kosong dengan ok=true berarti memang tidak ada izin.

require __DIR__ . '/../vendor/autoload.php';

use Aurat\Database;
use Aurat\Izin\Permohonan;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $db = Database::pdo();
    $peng = Permohonan::pengaturan($db);
} catch (\Throwable $e) {
    http_response_code(503);
    exit('{"ok":false}');
}

$token = isset($_SERVER['HTTP_X_API_TOKEN']) ? (string) $_SERVER['HTTP_X_API_TOKEN'] : '';
if ((string) $peng['token_portal'] === '' || !hash_equals((string) $peng['token_portal'], $token)) {
    http_response_code(401);
    exit('{"ok":false}');
}
if ($peng['mode'] === 'nonaktif') {
    http_response_code(503);
    exit('{"ok":false}');
}

$kini = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$tanggal = $kini->setTimezone(new DateTimeZone($peng['zona_waktu']))->format('Y-m-d');
$s = $db->prepare("SELECT id, pemohon_nama, jenis, jam_mulai, jam_kembali FROM izin_permohonan WHERE tanggal = ? AND status = 'disetujui' ORDER BY jam_mulai, pemohon_nama");
$s->execute(array($tanggal));

$izin = array();
foreach ($s->fetchAll() as $r) {
    $izin[] = array(
        'id' => (int) $r['id'],
        'nama' => $r['pemohon_nama'],
        'jenis' => $r['jenis'],
        'jam_mulai' => substr($r['jam_mulai'], 0, 5),
        'jam_kembali' => $r['jam_kembali'] ? substr($r['jam_kembali'], 0, 5) : null,
    );
}
echo json_encode(array(
    'ok' => true,
    'tanggal' => $tanggal,
    'zona_waktu' => $peng['zona_waktu'],
    'generated_at' => $kini->format('Y-m-d\TH:i:s\Z'),
    'izin' => $izin,
));
