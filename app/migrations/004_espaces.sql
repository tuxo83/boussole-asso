-- Types de membres, droits de plateforme, espaces (comités, commissions, projets, événements)

CREATE TABLE IF NOT EXISTS type_membre (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(100) NOT NULL UNIQUE,
    ordre INT NOT NULL DEFAULT 50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO type_membre (libelle, ordre) VALUES ('Adhérent', 10), ('Membre associé', 20);

ALTER TABLE membre
    ADD COLUMN type_membre_id INT UNSIGNED NULL,
    ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1,
    ADD FOREIGN KEY (type_membre_id) REFERENCES type_membre(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS droit_plateforme (
    membre_id INT UNSIGNED NOT NULL,
    droit ENUM('admin', 'cotisations', 'membres', 'publications') NOT NULL,
    PRIMARY KEY (membre_id, droit),
    FOREIGN KEY (membre_id) REFERENCES membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO droit_plateforme (membre_id, droit) SELECT id, 'admin' FROM membre WHERE est_admin = 1;

ALTER TABLE membre DROP COLUMN est_admin;

CREATE TABLE IF NOT EXISTS espace (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('comite', 'commission', 'projet', 'evenement') NOT NULL,
    titre VARCHAR(200) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    visibilite ENUM('equipe', 'adherents', 'public') NOT NULL DEFAULT 'equipe',
    meteo ENUM('soleil', 'eclaircie', 'pluie', 'orage') NOT NULL DEFAULT 'eclaircie',
    parent_id INT UNSIGNED NULL,
    archive TINYINT(1) NOT NULL DEFAULT 0,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES espace(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS espace_membre (
    espace_id INT UNSIGNED NOT NULL,
    membre_id INT UNSIGNED NOT NULL,
    niveau ENUM('n1', 'n2', 'membre', 'associe') NOT NULL,
    libelle VARCHAR(100) NOT NULL DEFAULT '',
    PRIMARY KEY (espace_id, membre_id),
    FOREIGN KEY (espace_id) REFERENCES espace(id) ON DELETE CASCADE,
    FOREIGN KEY (membre_id) REFERENCES membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jalon (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    espace_id INT UNSIGNED NOT NULL,
    date_jalon DATE NOT NULL,
    libelle VARCHAR(200) NOT NULL,
    fait TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (espace_id) REFERENCES espace(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fichier (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    espace_id INT UNSIGNED NOT NULL,
    nom VARCHAR(255) NOT NULL,
    stockage VARCHAR(100) NOT NULL UNIQUE,
    taille INT UNSIGNED NOT NULL,
    type_mime VARCHAR(100) NOT NULL,
    depose_par INT UNSIGNED NULL,
    depose_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (espace_id) REFERENCES espace(id) ON DELETE CASCADE,
    FOREIGN KEY (depose_par) REFERENCES membre(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    espace_id INT UNSIGNED NULL,
    membre_id INT UNSIGNED NULL,
    action VARCHAR(255) NOT NULL,
    via ENUM('web', 'ia') NOT NULL DEFAULT 'web',
    le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (espace_id, le),
    FOREIGN KEY (espace_id) REFERENCES espace(id) ON DELETE CASCADE,
    FOREIGN KEY (membre_id) REFERENCES membre(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
