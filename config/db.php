<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'joel_elec');
define('DB_USER', 'root');          // À modifier 
define('DB_PASS', '');              // À modifier 
define('DB_CHARSET', 'utf8mb4');

/**
 * Crée et retourne une connexion PDO configurée.
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
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}
