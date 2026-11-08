-- Événement réservé aux membres qui devient public une fois passé (compte rendu, photos visibles de tous)
ALTER TABLE evenement ADD COLUMN public_apres TINYINT(1) NOT NULL DEFAULT 0;
