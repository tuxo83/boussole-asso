-- Compte de démonstration : mot de passe fixé par un administrateur ; ses utilisateurs ne changent ni le mot de passe ni la sécurité
ALTER TABLE membre ADD COLUMN compte_demo TINYINT(1) NOT NULL DEFAULT 0;
