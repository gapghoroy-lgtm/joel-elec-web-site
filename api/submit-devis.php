<?php
/**
 * JOËL ELEC — API de soumission de demande de devis
 *
 * Point d'entrée : POST /api/submit-devis.php
 * Content-Type  : multipart/form-data
 *
 * Champs attendus :
 *   - nom          (string, obligatoire)
 *   - telephone    (string, obligatoire)
 *   - email        (string, optionnel)
 *   - service      (string, obligatoire)
 *   - type_batiment(string, obligatoire)
 *   - localisation (string, obligatoire)
 *   - message      (string, optionnel)
 *   - photo        (file,   optionnel, jpg/png, max 2 Mo)
 *
 * Retourne du JSON : { "success": true/false, "message": "..." }
 */

declare(strict_types=1);

// ─── Headers ────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ─── CORS (ajuster l'origine en production) ─────────────────
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Charger la connexion BDD et la configuration applicative ─
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

// ─── Constantes de configuration ────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2 Mo
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Services et types de bâtiment autorisés (whitelist)
define('VALID_SERVICES', [
    'Installation électrique',
    'Dépannage',
    'Rénovation',
    'Maintenance',
]);
define('VALID_BATIMENTS', [
    'Maison',
    'Appartement',
    'Bureau',
    'Commerce',
    'Autres',
]);


// =====================================================================
// FONCTIONS UTILITAIRES
// =====================================================================

/**
 * Envoie une réponse JSON et termine le script.
 */
function jsonResponse(bool $success, string $message, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Nettoie une chaîne : trim + suppression des balises HTML.
 */
function sanitize(string $value): string
{
    return trim($value);
}

/**
 * Récupère et nettoie un champ POST. Retourne null si vide.
 */
function getPostField(string $key): ?string
{
    if (!isset($_POST[$key]) || trim($_POST[$key]) === '') {
        return null;
    }
    return sanitize($_POST[$key]);
}

/**
 * Valide et uploade le fichier photo de manière sécurisée.
 * Retourne le chemin relatif ou null si aucun fichier.
 *
 * @throws RuntimeException en cas d'erreur de validation
 */
function handlePhotoUpload(): ?string
{
    // Aucun fichier envoyé
    if (
        !isset($_FILES['photo']) ||
        $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $file = $_FILES['photo'];

    // Vérifier les erreurs d'upload PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Erreur lors de l'envoi du fichier (code: {$file['error']}).");
    }

    // Vérifier la taille
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new RuntimeException('Le fichier dépasse la taille maximale autorisée (2 Mo).');
    }

    // Vérifier le type MIME réel (pas celui déclaré par le client)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
        throw new RuntimeException('Type de fichier non autorisé. Seuls JPG et PNG sont acceptés.');
    }

    // Vérifier l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
        throw new RuntimeException('Extension de fichier non autorisée.');
    }

    // Vérifier que c'est bien une image valide (protection contre les fichiers déguisés)
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new RuntimeException('Le fichier ne semble pas être une image valide.');
    }

    // Créer le dossier uploads s'il n'existe pas
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Générer un nom unique pour éviter les collisions et les noms malveillants
    $uniqueName = uniqid('devis_', true) . '.' . $extension;
    $destination = UPLOAD_DIR . $uniqueName;

    // Déplacer le fichier temporaire
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException("Impossible de sauvegarder le fichier.");
    }

    // Retourner le chemin relatif (pour stockage en BDD)
    return 'uploads/' . $uniqueName;
}

/**
 * Insère la demande de devis en base de données.
 *
 * @return int L'ID du devis inséré
 */
function insertDevis(PDO $pdo, array $data): int
{
    $sql = "INSERT INTO devis (nom, telephone, email, service, type_batiment, localisation, message, photo, ip_client)
            VALUES (:nom, :telephone, :email, :service, :type_batiment, :localisation, :message, :photo, :ip_client)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':nom'            => $data['nom'],
        ':telephone'      => $data['telephone'],
        ':email'          => $data['email'],
        ':service'        => $data['service'],
        ':type_batiment'  => $data['type_batiment'],
        ':localisation'   => $data['localisation'],
        ':message'        => $data['message'],
        ':photo'          => $data['photo'],
        ':ip_client'      => $data['ip_client'],
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Génère un lien sécurisé vers une photo via view-photo.php.
 *
 * Le token est un HMAC-SHA256 du nom de fichier signé avec la clé
 * secrète serveur (PHOTO_ACCESS_SECRET). Seul le serveur peut le
 * générer, et view-photo.php le recalcule pour valider l'accès.
 */
function generateSecurePhotoLink(string $photoPath): string
{
    // Extraire le nom de fichier seul (ex: devis_6789abc.png)
    $filename = basename($photoPath);

    // Générer le token HMAC
    $token = hash_hmac('sha256', $filename, PHOTO_ACCESS_SECRET);

    // Construire l'URL complète
    return BASE_URL . '/api/view-photo.php?file=' . urlencode($filename) . '&token=' . $token;
}

/**
 * Envoie un email de notification à l'administrateur.
 *
 * Utilise mail() natif. En cas d'échec, l'erreur est loguée
 * mais l'enregistrement en base n'est pas impacté.
 */
function sendNotificationEmail(array $data, int $devisId): void
{
    $to = ADMIN_EMAIL;
    $subject = "Nouvelle demande de devis #$devisId — JOËL ELEC";

    // Construction du corps de l'email en texte brut
    $body = "Nouvelle demande de devis reçue sur le site JOËL ELEC\n";
    $body .= str_repeat('─', 50) . "\n\n";
    $body .= "Devis #$devisId\n";
    $body .= "Date : " . date('d/m/Y à H:i') . "\n\n";
    $body .= "NOM         : {$data['nom']}\n";
    $body .= "TÉLÉPHONE   : {$data['telephone']}\n";
    $body .= "EMAIL       : " . ($data['email'] ?? 'Non renseigné') . "\n";
    $body .= "SERVICE     : {$data['service']}\n";
    $body .= "BÂTIMENT    : {$data['type_batiment']}\n";
    $body .= "LOCALISATION: {$data['localisation']}\n";
    $body .= "MESSAGE     : " . ($data['message'] ?? 'Aucun message') . "\n";

    // Lien sécurisé vers la photo (si présente)
    if (!empty($data['photo'])) {
        $photoLink = generateSecurePhotoLink($data['photo']);
        $body .= "PHOTO       : Oui\n";
        $body .= "LIEN PHOTO  : $photoLink\n";
    } else {
        $body .= "PHOTO       : Non\n";
    }

    $body .= "\n" . str_repeat('─', 50) . "\n";
    $body .= "IP client : {$data['ip_client']}\n";

    // Headers de l'email
    $headers = implode("\r\n", [
        'From: noreply@joelelec.com',
        'Reply-To: ' . ($data['email'] ?? 'noreply@joelelec.com'),
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: JOEL-ELEC-Website',
    ]);

    // Envoi — en cas d'échec, on logue l'erreur sans bloquer
    $sent = @mail($to, $subject, $body, $headers);

    if (!$sent) {
        error_log("Échec d'envoi email pour devis #$devisId vers $to");
    }
}


// =====================================================================
// TRAITEMENT PRINCIPAL
// =====================================================================

// 1. Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée. Utilisez POST.', 405);
}

try {
    // 2. Récupérer et valider les champs obligatoires
    $nom          = getPostField('nom');
    $telephone    = getPostField('telephone');
    $email        = getPostField('email');
    $service      = getPostField('service');
    $typeBatiment = getPostField('type_batiment');
    $localisation = getPostField('localisation');
    $message      = getPostField('message');

    // Validation des champs obligatoires
    $errors = [];

    if ($nom === null || mb_strlen($nom) < 2) {
        $errors[] = 'Le nom est obligatoire (minimum 2 caractères).';
    }
    if ($nom !== null && mb_strlen($nom) > 100) {
        $errors[] = 'Le nom ne doit pas dépasser 100 caractères.';
    }

    if ($telephone === null) {
        $errors[] = 'Le numéro de téléphone est obligatoire.';
    } elseif (!preg_match('/^[\d\s\+\-\(\)]{6,20}$/', $telephone)) {
        $errors[] = 'Le numéro de téléphone n\'est pas valide.';
    }

    // Email optionnel, mais s'il est fourni il doit être valide
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse email n\'est pas valide.';
    }

    if ($service === null) {
        $errors[] = 'Le type de service est obligatoire.';
    } elseif (!in_array($service, VALID_SERVICES, true)) {
        $errors[] = 'Le type de service sélectionné n\'est pas valide.';
    }

    if ($typeBatiment === null) {
        $errors[] = 'Le type de bâtiment est obligatoire.';
    } elseif (!in_array($typeBatiment, VALID_BATIMENTS, true)) {
        $errors[] = 'Le type de bâtiment sélectionné n\'est pas valide.';
    }

    if ($localisation === null || mb_strlen($localisation) < 2) {
        $errors[] = 'La localisation est obligatoire.';
    }

    if (!empty($errors)) {
        jsonResponse(false, implode(' ', $errors), 422);
    }

    // 3. Upload sécurisé de la photo (optionnel)
    $photoPath = handlePhotoUpload();

    // 4. Préparer les données pour l'insertion
    $data = [
        'nom'           => $nom,
        'telephone'     => $telephone,
        'email'         => $email,
        'service'       => $service,
        'type_batiment' => $typeBatiment,
        'localisation'  => $localisation,
        'message'       => $message,
        'photo'         => $photoPath,
        'ip_client'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ];

    // 5. Connexion à la base et insertion
    $pdo = getDbConnection();
    $devisId = insertDevis($pdo, $data);

    // 6. Envoyer la notification email
    sendNotificationEmail($data, $devisId);

    // 7. Réponse succès
    jsonResponse(true, "Votre demande de devis a été envoyée avec succès ! Référence : #$devisId. Nous vous répondrons sous 24h.");

} catch (RuntimeException $e) {
    // Erreurs de validation fichier
    jsonResponse(false, $e->getMessage(), 422);

} catch (PDOException $e) {
    // Erreurs base de données — ne pas exposer les détails en production
    error_log('Erreur BDD devis: ' . $e->getMessage());
    jsonResponse(false, 'Une erreur interne est survenue. Veuillez réessayer plus tard.', 500);

} catch (Throwable $e) {
    // Toute autre erreur inattendue
    error_log('Erreur inattendue devis: ' . $e->getMessage());
    jsonResponse(false, 'Une erreur inattendue est survenue. Veuillez réessayer.', 500);
}
