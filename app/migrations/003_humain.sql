-- Captcha invisible : défis déjà utilisés (pas de rejeu)

CREATE TABLE IF NOT EXISTS defi_utilise (
    empreinte CHAR(64) PRIMARY KEY,
    expire_le DATETIME NOT NULL,
    INDEX (expire_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
