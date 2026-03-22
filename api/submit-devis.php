<?php


declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2 Mo
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

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


function jsonResponse(bool $success, string $message, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
function sanitize(string $value): string
{
    return trim($value);
}

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
 * @throws RuntimeException en cas d'erreur de validation
 */
function handlePhotoUpload(): ?string
{
    if (
        !isset($_FILES['photo']) ||
        $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $file = $_FILES['photo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Erreur lors de l'envoi du fichier (code: {$file['error']}).");
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        throw new RuntimeException('Le fichier dépasse la taille maximale autorisée (2 Mo).');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
        throw new RuntimeException('Type de fichier non autorisé. Seuls JPG et PNG sont acceptés.');
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
        throw new RuntimeException('Extension de fichier non autorisée.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new RuntimeException('Le fichier ne semble pas être une image valide.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $uniqueName = uniqid('devis_', true) . '.' . $extension;
    $destination = UPLOAD_DIR . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException("Impossible de sauvegarder le fichier.");
    }

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

function generateSecurePhotoLink(string $photoPath): string
{
    // Extraire le nom de fichier seul (ex: devis_6789abc.png)
    $filename = basename($photoPath);

    $token = hash_hmac('sha256', $filename, PHOTO_ACCESS_SECRET);

    return BASE_URL . '/api/view-photo.php?file=' . urlencode($filename) . '&token=' . $token;
}

function sendNotificationEmail(array $data, int $devisId): void
{
    $to = ADMIN_EMAIL;
    $subject = "Nouvelle demande de devis #$devisId — JOËL ELEC";

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

    if (!empty($data['photo'])) {
        $photoLink = generateSecurePhotoLink($data['photo']);
        $body .= "PHOTO       : Oui\n";
        $body .= "LIEN PHOTO  : $photoLink\n";
    } else {
        $body .= "PHOTO       : Non\n";
    }

    $body .= "\n" . str_repeat('─', 50) . "\n";
    $body .= "IP client : {$data['ip_client']}\n";

    $headers = implode("\r\n", [
        'From: noreply@joelelec.com',
        'Reply-To: ' . ($data['email'] ?? 'noreply@joelelec.com'),
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: JOEL-ELEC-Website',
    ]);

    $sent = @mail($to, $subject, $body, $headers);

    if (!$sent) {
        error_log("Échec d'envoi email pour devis #$devisId vers $to");
    }
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée. Utilisez POST.', 405);
}

try {
    $nom          = getPostField('nom');
    $telephone    = getPostField('telephone');
    $email        = getPostField('email');
    $service      = getPostField('service');
    $typeBatiment = getPostField('type_batiment');
    $localisation = getPostField('localisation');
    $message      = getPostField('message');

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

    $photoPath = handlePhotoUpload();

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

    $pdo = getDbConnection();
    $devisId = insertDevis($pdo, $data);

    sendNotificationEmail($data, $devisId);

    jsonResponse(true, "Votre demande de devis a été envoyée avec succès ! Référence : #$devisId. Nous vous répondrons sous 24h.");

} catch (RuntimeException $e) {
    jsonResponse(false, $e->getMessage(), 422);

} catch (PDOException $e) {
    error_log('Erreur BDD devis: ' . $e->getMessage());
    jsonResponse(false, 'Une erreur interne est survenue. Veuillez réessayer plus tard.', 500);

} catch (Throwable $e) {
    error_log('Erreur inattendue devis: ' . $e->getMessage());
    jsonResponse(false, 'Une erreur inattendue est survenue. Veuillez réessayer.', 500);
}
