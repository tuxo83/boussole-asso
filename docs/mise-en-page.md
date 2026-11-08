# Règles de mise en page

Pour que toutes les pages se ressemblent et restent simples. À suivre pour toute nouvelle page ; les personas (Élise, Inès) relisent ce qui se voit.

## Consulter d'un côté, régler de l'autre
- La page d'un espace ou d'un événement montre **le contenu** : en-tête, à faire, agenda, dates, fichiers, équipe, inscrits.
- **Tout ce qui se règle** (réglages, organisation, journal, archiver, supprimer) est dans la page **« Gérer »**, ouverte par le bouton « ⚙ Gérer » en haut à droite, pour qui gère seulement. Ses onglets sont toujours dans le même ordre : Réglages · Organisation · Journal · Archiver ou supprimer.
- Le titre, la description, la météo et la visibilité restent modifiables **sur place** (un clic), sur la page elle-même.

## Sections
- Une section = un titre (`h2`) + son contenu ; un bouton d'ajout « + … » **en haut à droite** de la section (`.titre-avec-lien`).
- Les réglages se rangent dans des **blocs** encadrés (`.bloc-reglage`) avec un titre court (« Quand et où », « Inscriptions »…).
- Une rubrique vide n'est montrée qu'à qui peut la remplir.

## Choix
- **Deux à quatre options** : des **cartes cliquables** avec une ligne d'explication (`.carte-choix`, `.choix-cartes`), pas une liste déroulante.
- Au-delà, ou pour une valeur (membre, date) : un champ.
- Ce qui ne sert que selon un choix (places limitées, paiement) n'apparaît que si ce choix est fait.

## Actions
- Une seule action principale par bloc (`.bouton-principal`) ; les autres en secondaire.
- Modifier un élément d'une liste : un **crayon** sur la ligne, qui ouvre une **petite fenêtre** (`dialog.dialogue`).
- Ce qui ne se défait pas : isolé dans « Archiver ou supprimer », avec confirmation explicite (retaper le nom), bouton rouge.

## Petites fenêtres
- Aide « ? », menus, fenêtres : un clic ailleurs ou Échap les referme ; elles restent dans l'écran.
- L'essentiel se lit **sans ouvrir** de « ? » : le « ? » ne sert qu'au détail.

## Téléphone
- Tout doit marcher à 390 px de large sans défilement horizontal ; cibles tactiles de 44 px au moins.
- Vérifier chaque page sur téléphone et sur ordinateur (captures à noms uniques, jamais réécrites).
