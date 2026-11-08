-- Version des sessions d'un membre : l'augmenter déconnecte toutes ses sessions ouvertes (mot de passe changé, désactivé…)
ALTER TABLE membre ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0;
-- Lien et code d'un même e-mail utilisables chacun une fois : lien ouvert dans le navigateur, code saisi dans l'application (iPhone)
ALTER TABLE connexion_jeton ADD COLUMN lien_utilise_le DATETIME NULL;
