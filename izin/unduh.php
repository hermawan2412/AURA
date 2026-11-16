<?php
// Baca arsip PDF saja - tidak pernah memicu penerbitan atau perubahan status.

require __DIR__ . '/_umum.php';

use Aurat\Auth;
use Aurat\Izin\Dokumen;
use Aurat\Izin\Permohonan;

$p = Permohonan::muat($db, isset($_GET['id']) ? (int) $_GET['id'] : 0);
if (!$p || !Permohonan::bolehLihat($db, $p, $userId, Auth::isAdmin())) {
    http_response_code(404);
    exit('Surat tidak ditemukan.');
}
$d = Dokumen::muat($db, $p['id']);
$path = $d && $d['status'] === 'siap' ? Dokumen::pathPdf($d) : null;
if ($path === null || !is_file($path)) {
    http_response_code(404);
    exit('Surat belum tersedia.');
}

$nama = 'Izin_' . preg_replace('/[^A-Za-z0-9]+/', '_', $p['pemohon_nama']) . '_' . $p['tanggal'] . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nama . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
