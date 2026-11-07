-- Clé d'API « administration » : réglages du site et membres, toujours dans la limite des droits du propriétaire
ALTER TABLE cle_api MODIFY portee ENUM('lecture', 'ecriture', 'admin') NOT NULL DEFAULT 'lecture';
