<?php
/**
 * JOËL ELEC — Connexion PDO à la base de données MySQL
 *
 * Ce fichier retourne une instance PDO prête à l'emploi.
 * En production, déplacez les identifiants dans un fichier .env
 * ou dans des variables d'environnement serveur.
 */

// ─── Paramètres de connexion ────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'joel_elec');
define('DB_USER', 'root');          // À modifier en production
define('DB_PASS', '');              // À modifier en production
define('DB_CHARSET', 'utf8mb4');

/**
 * Crée et retourne une connexion PDO configurée.
 *
 * @return PDO
 * @throws PDOException si la connexion échoue
 */
function getDbConnection(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        // Lancer des exceptions en cas d'erreur (pas de warnings silencieux)
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

        // Retourner des tableaux associatifs par défaut
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Utiliser de vraies requêtes préparées (pas d'émulation)
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}
