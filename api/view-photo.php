<?php
/**
 * JOËL ELEC — Accès sécurisé aux photos de devis
 *
 * Point d'entrée : GET /api/view-photo.php?file=xxx&token=xxx
 *
 * Ce script vérifie un token HMAC avant de servir l'image.
 * Le token est généré dans submit-devis.php lors de l'envoi de l'email.
 * Cela empêche l'accès direct aux fichiers du dossier /uploads/.
 *
 * Sécurité :
 *   - Vérification HMAC avec hash_equals() (timing-safe)
 *   - Protection contre la traversée de répertoires (basename)
 *   - Vérification que le fichier existe dans uploads/
 *   - Vérification du type MIME réel du fichier
 *   - Seuls les types image/jpeg et image/png sont servis
 */

declare(strict_types=1);

// ─── Charger la configuration ────────────────────────────────
require_once __DIR__ . '/../config/app.php';

// ─── Constantes locales ──────────────────────────────────────
define('UPLOADS_DIR', __DIR__ . '/../uploads/');
define('ALLOWED_SERVE_TYPES', [
    'image/jpeg' => 'image/jpeg',
    'image/png'  => 'image/png',
]);

// ─── Vérifier la méthode HTTP ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

// ─── Récupérer et vérifier les paramètres ────────────────────
$file  = $_GET['file']  ?? '';
$token = $_GET['token'] ?? '';

if ($file === '' || $token === '') {
    http_response_code(400);
    exit('Paramètres manquants.');
}

// ─── Protection contre la traversée de répertoires ───────────
// basename() supprime tout chemin relatif (../, ./, /) du nom de fichier.
// On compare ensuite avec la valeur originale pour détecter toute tentative.
$safeName = basename($file);

if ($safeName !== $file || $safeName === '' || $safeName === '.' || $safeName === '..') {
    http_response_code(403);
    exit('Accès refusé.');
}

// ─── Vérifier le token HMAC ──────────────────────────────────
// Le token attendu est calculé avec la même clé secrète que lors de la
// génération du lien dans submit-devis.php.
$expectedToken = hash_hmac('sha256', $safeName, PHOTO_ACCESS_SECRET);

if (!hash_equals($expectedToken, $token)) {
    http_response_code(403);
    exit('Token invalide — accès refusé.');
}

// ─── Vérifier que le fichier existe ──────────────────────────
$filePath = UPLOADS_DIR . $safeName;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// ─── Vérifier le type MIME réel ──────────────────────────────
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath);

if (!isset(ALLOWED_SERVE_TYPES[$mimeType])) {
    http_response_code(403);
    exit('Type de fichier non autorisé.');
}

// ─── Servir le fichier image ─────────────────────────────────
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');

readfile($filePath);
exit;
