-- Pointage facultatif des présences : pour information seulement, sans effet sur les priorités
ALTER TABLE inscription ADD COLUMN present TINYINT(1) NULL;
