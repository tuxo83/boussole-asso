-- Pages en arborescence, contenu en blocs (éditeur à blocs), page d'accueil, images publiques

ALTER TABLE page
    ADD COLUMN parent_id INT UNSIGNED NULL,
    ADD COLUMN blocs MEDIUMTEXT NULL,
    ADD COLUMN est_accueil TINYINT(1) NOT NULL DEFAULT 0,
    ADD FOREIGN KEY (parent_id) REFERENCES page(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS image (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chemin VARCHAR(150) NOT NULL UNIQUE,
    largeur INT UNSIGNED NOT NULL,
    hauteur INT UNSIGNED NOT NULL,
    depose_par INT UNSIGNED NULL,
    depose_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (depose_par) REFERENCES membre(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
