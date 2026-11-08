-- Double authentification : à chaque connexion (1) ou seulement pour entrer dans la gestion (0, par défaut)
ALTER TABLE membre ADD COLUMN mfa_connexion TINYINT(1) NOT NULL DEFAULT 0;
