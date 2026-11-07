-- Pages réservées aux membres connectés ; adhésions reçues d'un inconnu : compte en attente de validation ;
-- paiements en double signalés au responsable des cotisations
ALTER TABLE page ADD COLUMN reservee_membres TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE membre ADD COLUMN en_attente TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS cotisation_doublon (
    membre_id INT UNSIGNED NOT NULL,
    periode SMALLINT UNSIGNED NOT NULL,
    paye_le DATE NOT NULL,
    moyen ENUM('helloasso', 'cheque', 'especes', 'virement', 'autre') NOT NULL,
    signale_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    traite TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (membre_id, periode, paye_le, moyen),
    FOREIGN KEY (membre_id) REFERENCES membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
