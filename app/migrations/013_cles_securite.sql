-- Clés de sécurité FIDO2 / passkeys (WebAuthn) : second facteur sans application sur le téléphone
CREATE TABLE IF NOT EXISTS cle_securite (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membre_id INT UNSIGNED NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    identifiant VARCHAR(255) NOT NULL UNIQUE,
    cle_publique TEXT NOT NULL,
    compteur INT UNSIGNED NOT NULL DEFAULT 0,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_utilisation DATETIME NULL,
    FOREIGN KEY (membre_id) REFERENCES membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
