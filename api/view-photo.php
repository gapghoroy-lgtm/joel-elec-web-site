<?php


declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

define('UPLOADS_DIR', __DIR__ . '/../uploads/');
define('ALLOWED_SERVE_TYPES', [
    'image/jpeg' => 'image/jpeg',
    'image/png'  => 'image/png',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

$file  = $_GET['file']  ?? '';
$token = $_GET['token'] ?? '';

if ($file === '' || $token === '') {
    http_response_code(400);
    exit('Paramètres manquants.');
}

$safeName = basename($file);

if ($safeName !== $file || $safeName === '' || $safeName === '.' || $safeName === '..') {
    http_response_code(403);
    exit('Accès refusé.');
}

$expectedToken = hash_hmac('sha256', $safeName, PHOTO_ACCESS_SECRET);

if (!hash_equals($expectedToken, $token)) {
    http_response_code(403);
    exit('Token invalide — accès refusé.');
}

$filePath = UPLOADS_DIR . $safeName;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath);

if (!isset(ALLOWED_SERVE_TYPES[$mimeType])) {
    http_response_code(403);
    exit('Type de fichier non autorisé.');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');

readfile($filePath);
exit;
