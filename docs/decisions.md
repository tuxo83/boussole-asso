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
