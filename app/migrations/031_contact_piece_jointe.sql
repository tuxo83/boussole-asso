-- Formulaire de contact : un PDF joint (CV d'un candidat…), gardé avec le message et effacé avec lui
ALTER TABLE message_contact ADD COLUMN pj_nom VARCHAR(255) NULL, ADD COLUMN pj_stockage VARCHAR(64) NULL, ADD COLUMN pj_taille INT UNSIGNED NULL;
