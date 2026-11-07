# Décisions de conception

## Cotisations
- On enregistre seulement **qui est à jour, pour quelle année, depuis quand, et par quel moyen** (en ligne, chèque, espèces…). Aucune donnée bancaire, aucun scan, aucun montant détaillé de mouvement.
- Chèque : la personne qui a reçu le chèque coche « a payé par chèque ».
- Import d'un service de paiement en ligne (ex. HelloAsso) : sert uniquement à cocher « a payé » avec la date.
- Relances des retardataires : oui.

## Rappels
- Délais **réglables** : quelques semaines avant pour les tâches internes, jusqu'à plusieurs mois avant pour les événements. Valeurs par défaut à définir.

## Données personnelles
- Supprimer au plus tôt : une personne dont la dernière cotisation date de **plus de 2 ans** est effacée.
- L'effacement n'est pas silencieux : il devient une **tâche récurrente** pour la personne responsable, avec une alerte et une météo, qui valide l'effacement.
- Avant cela, l'export de la liste (ex. qui a cotisé) reste possible.

## Rôles
- **Dans l'association**, des types de membres réglables par chaque installation (ex. adhérent, membre associé, membre d'honneur) : un libellé libre, et des droits qui en découlent (voter, s'inscrire aux événements réservés…).
- **Dans un espace** (comité, commission, projet, événement), des niveaux de droits fixes, avec un libellé libre :
  - n°1 (responsable) et n°2 (suppléant) : mêmes droits, gèrent l'espace ;
  - membre : contribue (fichiers, dates, météo) ;
  - associé / conseiller (« sage ») : rattaché à l'espace sans y être nommé, éventuellement extérieur à l'association ; voit l'espace et peut commenter, sans le gérer.
- Les libellés sont libres (président, secrétaire, sage…), les niveaux de droits ne le sont pas : simple à comprendre, sûr, universel.
