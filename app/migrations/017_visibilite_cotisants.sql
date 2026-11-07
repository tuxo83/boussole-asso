-- Visibilité « membres à jour de cotisation » (événements réservés aux cotisants, par exemple)
ALTER TABLE espace MODIFY visibilite ENUM('equipe', 'cotisants', 'adherents', 'public') NOT NULL DEFAULT 'equipe';
