-- Événements (un espace de type « evenement ») et inscriptions équitables : des vœux, pas de course au clic

CREATE TABLE IF NOT EXISTS evenement (
    espace_id INT UNSIGNED PRIMARY KEY,
    debut DATETIME NULL,
    lieu VARCHAR(200) NOT NULL DEFAULT '',
    mode ENUM('ouvert', 'auto', 'valide') NOT NULL DEFAULT 'ouvert',
    places INT UNSIGNED NULL,
    cloture DATETIME NULL,
    etat ENUM('ouvert', 'proposee', 'attribuee') NOT NULL DEFAULT 'ouvert',
    FOREIGN KEY (espace_id) REFERENCES espace(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscription (
    evenement_id INT UNSIGNED NOT NULL,
    membre_id INT UNSIGNED NOT NULL,
    statut ENUM('invite', 'voeu', 'retenu', 'attente', 'desiste', 'decline') NOT NULL,
    invite TINYINT(1) NOT NULL DEFAULT 0,
    groupe TINYINT UNSIGNED NULL,
    participations SMALLINT UNSIGNED NULL,
    rang INT UNSIGNED NULL,
    propose TINYINT(1) NOT NULL DEFAULT 0,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (evenement_id, membre_id),
    FOREIGN KEY (evenement_id) REFERENCES evenement(espace_id) ON DELETE CASCADE,
    FOREIGN KEY (membre_id) REFERENCES membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
