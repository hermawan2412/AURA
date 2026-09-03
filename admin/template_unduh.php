<?php

require __DIR__ . '/../src/bootstrap.php';

use Aurat\Auth;
use Aurat\Surat\TemplateSuratRepository;
use Aurat\Surat\TemplateUpload;

Auth::requireLogin();

$templateSuratId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$template = $templateSuratId > 0 ? TemplateSuratRepository::muatById($templateSuratId) : null;

if (!$template) {
    http_response_code(404);
    exit('Template tidak ditemukan.');
}

$path = TemplateUpload::direktoriUpload() . '/' . $template['nama_berkas'];
if (!is_file($path)) {
    http_response_code(404);
    exit('Berkas template tidak ditemukan di server.');
}

// nama_asli berasal dari nama berkas yang diunggah admin dulu — bersihkan kendali/kutip
// sebelum dipakai di header supaya tidak bisa dipakai utk header injection.
$namaBersih   = preg_replace('/[\r\n"]+/', '_', $template['nama_asli']);
$namaUnduhan  = 'v' . (int) $template['versi'] . '_' . $namaBersih;

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $namaUnduhan . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, no-store, no-cache');
header('Pragma: public');
header('Content-Length: ' . filesize($path));

readfile($path);
exit;
