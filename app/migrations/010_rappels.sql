-- Rappels : destinataires d'une date clé, et rappels déjà envoyés (jamais deux fois le même)
ALTER TABLE jalon ADD COLUMN destinataires ENUM('binome', 'equipe') NOT NULL DEFAULT 'binome';

CREATE TABLE IF NOT EXISTS rappel_envoye (
    genre ENUM('jalon', 'evenement') NOT NULL,
    objet_id INT UNSIGNED NOT NULL,
    delai SMALLINT UNSIGNED NOT NULL,
    envoye_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (genre, objet_id, delai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
