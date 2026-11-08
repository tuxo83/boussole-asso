-- Le bandeau « Installez l'application » n'est plus proposé d'office : l'administrateur l'active s'il le souhaite
REPLACE INTO parametre (cle, valeur) VALUES ('application_proposer', '0');
