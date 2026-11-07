-- Clés d'API personnelles (IA, MCP) : seule l'empreinte est gardée, la clé n'est montrée qu'une fois

CREATE TABLE IF NOT EXISTS cle_api (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membre_id INT UNSIGNED NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    prefixe CHAR(8) NOT NULL UNIQUE,
    empreinte CHAR(64) NOT NULL,
    portee ENUM('lecture', 'ecriture') NOT NULL DEFAULT 'lecture',
    expire_le DATE NOT NULL,
    revoquee TINYINT(1) NOT NULL DEFAULT 0,
    derniere_utilisation DATETIME NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (membre_id) REFERENCES membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
