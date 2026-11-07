-- Connexion HelloAsso (facultative) : formulaire de billetterie d'un événement, et paiement constaté d'un inscrit
ALTER TABLE evenement ADD COLUMN helloasso_formulaire VARCHAR(150) NULL;
ALTER TABLE inscription ADD COLUMN paye_le DATE NULL;
