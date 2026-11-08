-- Météo automatique d'un espace (case à cocher) et sa raison affichée (« En retard : 1 date clé dépassée »)
ALTER TABLE espace ADD COLUMN meteo_auto TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE espace ADD COLUMN meteo_raison VARCHAR(200) NOT NULL DEFAULT '';
