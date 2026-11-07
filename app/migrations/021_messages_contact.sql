-- Messages du formulaire de contact : gardés dans le site (même sans envoi d'e-mail), effacés après une durée réglable
CREATE TABLE IF NOT EXISTS message_contact (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    objet VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    recu_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lu TINYINT(1) NOT NULL DEFAULT 0,
    transmis TINYINT(1) NOT NULL DEFAULT 0,
    INDEX (recu_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
