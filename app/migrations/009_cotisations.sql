-- Cotisations : seulement « payé, pour quelle période, quand, comment ». Aucun montant ni donnée bancaire.

CREATE TABLE IF NOT EXISTS cotisation (
    membre_id INT UNSIGNED NOT NULL,
    periode SMALLINT UNSIGNED NOT NULL,
    paye_le DATE NOT NULL,
    moyen ENUM('helloasso', 'cheque', 'especes', 'virement', 'autre') NOT NULL,
    saisi_par INT UNSIGNED NULL,
    PRIMARY KEY (membre_id, periode),
    FOREIGN KEY (membre_id) REFERENCES membre(id) ON DELETE CASCADE,
    FOREIGN KEY (saisi_par) REFERENCES membre(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relances envoyées (une relance automatique par période au plus)
CREATE TABLE IF NOT EXISTS cotisation_relance (
    membre_id INT UNSIGNED NOT NULL,
    periode SMALLINT UNSIGNED NOT NULL,
    envoyee_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (membre_id, periode),
    FOREIGN KEY (membre_id) REFERENCES membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
