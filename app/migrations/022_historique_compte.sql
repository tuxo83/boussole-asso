-- Historique d'un compte : les lignes du journal qui concernent un membre (connexions, mot de passe, clés…)
ALTER TABLE journal
    ADD COLUMN cible_id INT UNSIGNED NULL,
    ADD INDEX journal_cible (cible_id, le),
    ADD CONSTRAINT journal_cible_membre FOREIGN KEY (cible_id) REFERENCES membre(id) ON DELETE CASCADE;
