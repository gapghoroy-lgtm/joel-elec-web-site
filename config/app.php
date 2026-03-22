<?php
/**
 * JOËL ELEC — Configuration applicative
 *
 * Centralise les constantes de configuration qui ne concernent pas
 * directement la base de données (email, URLs, clés secrètes).
 *
 * En production, ces valeurs peuvent être surchargées par des
 * variables d'environnement serveur.
 */

// ─── Adresse email administrateur ────────────────────────────
// Toutes les notifications de devis seront envoyées à cette adresse.
define('ADMIN_EMAIL', 'joelelec.contact@gmail.com');

// ─── URL de base du site ─────────────────────────────────────
// Utilisée pour construire les liens dans les emails.
// Ne pas mettre de slash final.
define('BASE_URL', 'https://joelelec.com');

// ─── Clé secrète pour les liens photo sécurisés ──────────────
// Utilisée avec hash_hmac() pour signer les URLs d'accès aux photos.
// IMPORTANT : changer cette valeur en production avec une chaîne
// aléatoire longue (32+ caractères). Ne jamais la rendre publique.
define('PHOTO_ACCESS_SECRET', 'CHANGER_CETTE_CLE_EN_PRODUCTION_32chars_min');
