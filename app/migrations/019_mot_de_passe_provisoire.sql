-- Mot de passe provisoire donné par l'administrateur : le membre doit en choisir un autre à sa prochaine connexion
ALTER TABLE membre ADD COLUMN IF NOT EXISTS mot_de_passe_provisoire TINYINT(1) NOT NULL DEFAULT 0;
