-- Comptes : mot de passe facultatif, double authentification, liens et codes de connexion, tentatives

ALTER TABLE membre
    ADD COLUMN mot_de_passe VARCHAR(255) NULL,
    ADD COLUMN totp_secret VARCHAR(255) NULL,
    ADD COLUMN totp_actif TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN derniere_connexion DATETIME NULL;

CREATE TABLE IF NOT EXISTS connexion_jeton (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membre_id INT UNSIGNED NOT NULL,
    lien_hash CHAR(64) NOT NULL UNIQUE,
    code_hash CHAR(64) NOT NULL,
    essais TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expire_le DATETIME NOT NULL,
    utilise_le DATETIME NULL,
    FOREIGN KEY (membre_id) REFERENCES membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tentative_connexion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cle CHAR(64) NOT NULL,
    le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (cle, le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
