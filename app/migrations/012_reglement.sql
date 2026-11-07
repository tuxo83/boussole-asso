-- Événements payants ou à confirmer : paiement (ou confirmation) dans un délai après l'attribution,
-- puis « dernières places » (premier qui paie ou confirme) à l'approche de l'événement
ALTER TABLE evenement
    ADD COLUMN reglement ENUM('aucun', 'paiement', 'confirmation') NOT NULL DEFAULT 'aucun',
    ADD COLUMN lien_paiement VARCHAR(300) NULL,
    ADD COLUMN delai_jours TINYINT UNSIGNED NOT NULL DEFAULT 5,
    ADD COLUMN dernieres_jours SMALLINT UNSIGNED NOT NULL DEFAULT 7,
    ADD COLUMN dernieres_annoncees TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE inscription
    MODIFY statut ENUM('invite', 'voeu', 'retenu', 'attente', 'desiste', 'decline', 'expire') NOT NULL,
    ADD COLUMN echeance DATETIME NULL,
    ADD COLUMN confirme_le DATETIME NULL,
    ADD COLUMN rappel_echeance TINYINT(1) NOT NULL DEFAULT 0;
