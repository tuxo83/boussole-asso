-- « Rester connecté sur cet appareil » : un jeton par appareil (seule son empreinte est gardée).
-- Il ne rouvre que la première étape (mot de passe ou e-mail) : la gestion redemande le second facteur.
CREATE TABLE IF NOT EXISTS connexion_appareil (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membre_id INT UNSIGNED NOT NULL,
    selecteur CHAR(24) NOT NULL UNIQUE,
    empreinte CHAR(64) NOT NULL,
    facteur VARCHAR(10) NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_utilisation DATETIME NULL,
    expire_le DATETIME NOT NULL,
    FOREIGN KEY (membre_id) REFERENCES membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
