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

## Droits de plateforme
- Trois axes indépendants : **type de membre** (statut dans l'association), **rôle dans un espace** (gérer cet espace), **droits de plateforme** (gérer une partie du site).
- Droits de plateforme : **administrateur** (tout), et trois délégations : **cotisations**, **membres**, **publications** (accueil, pages, actualités).
- Aucun droit ne découle d'une fonction (président, secrétaire…) : on attribue explicitement. Tout droit de plateforme impose la double authentification.

## Pages du site : éditeur à blocs
- Éditeur à blocs open source (Editor.js, licence Apache 2.0), embarqué dans le dépôt : aucun CDN, aucun service externe.
- Le contenu est enregistré en blocs (JSON) et affiché avec nos propres composants : jamais de HTML libre.
- Inscriptions : vœux sans course au clic ; groupes 0 (invités) à 3 calculés sur la moyenne des participations sur 12 mois ; tirage reproductible ; liste d'attente avec promotion seulement si une place est réellement libre. Inscrit = participation (pas de pointage des présences pour l'instant).
- Blocs dynamiques en nombre limité (liste d'événements, actualités, adhésion, contact, espaces publics), sans plugins.
- Images : ré-encodées à l'envoi (suppression des métadonnées EXIF, GPS…), formats image uniquement. Une image d'une page publique est publique ; une image ou un fichier d'un espace ou d'un événement n'est servi qu'aux personnes qui ont le droit de voir cet espace.

## Authentification externe (plus tard)
- Par défaut, la connexion intégrée (fonctionne chez tout hébergeur).
- À garder possible : un mode facultatif où le serveur (ex. Apache OIDC devant le site) a déjà authentifié la personne ; Boussole lit alors l'e-mail transmis par le serveur (variable du serveur, jamais un en-tête du navigateur) et retrouve le membre. Les droits restent gérés dans Boussole.
- La connexion est concentrée dans `core/auth.php` (première étape réussie → `login_first_step`) : c'est le seul endroit à brancher.
