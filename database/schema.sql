-- ============================================================
-- JOËL ELEC — Table des demandes de devis
-- À exécuter dans phpMyAdmin ou via CLI MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS joel_elec
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE joel_elec;

CREATE TABLE IF NOT EXISTS devis (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100)    NOT NULL,
    telephone       VARCHAR(20)     NOT NULL,
    email           VARCHAR(150)    DEFAULT NULL,
    service         VARCHAR(80)     NOT NULL,
    type_batiment   VARCHAR(80)     NOT NULL,
    localisation    VARCHAR(150)    NOT NULL,
    message         TEXT            DEFAULT NULL,
    photo           VARCHAR(255)    DEFAULT NULL,       -- chemin relatif vers le fichier uploadé
    ip_client       VARCHAR(45)     DEFAULT NULL,       -- IPv4 ou IPv6, utile anti-spam
    date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_date (date_creation),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
