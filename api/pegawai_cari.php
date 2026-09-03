<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Database;

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(array('error' => 'Belum masuk.'));
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(array());
    exit;
}

$pdo = Database::pdo();
$stmt = $pdo->prepare(
    'SELECT id, nip, nama_lengkap, gelar_depan, gelar_belakang, pangkat, golongan_ruang, jabatan, unit_kerja
     FROM pegawai
     WHERE status_aktif = 1 AND (nama_lengkap LIKE ? OR nip LIKE ?)
     ORDER BY nama_lengkap ASC
     LIMIT 8'
);
$like = '%' . $q . '%';
$stmt->execute(array($like, $like));

echo json_encode($stmt->fetchAll());
