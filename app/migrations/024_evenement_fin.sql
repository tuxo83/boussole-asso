-- Heure de fin d'un événement (facultative) : « 9 h 30 – 12 h », et fichier d'agenda correct
ALTER TABLE evenement ADD COLUMN fin DATETIME NULL;
