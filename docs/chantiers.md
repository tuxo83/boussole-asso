# Chantiers à venir

Sujets décidés en principe, à mener plus tard, chacun comme un petit chantier dédié. Les décisions déjà prises sont dans [decisions.md](decisions.md), les règles générales dans [regles.md](regles.md).

## Annuaire des membres
Un chantier de réflexion d'abord, la technique ensuite.
- **Les informations** : téléphone, adresse, parcours… ce que chacun accepte de partager.
- **Les deux cercles de partage** : les membres de l'association, et l'annuaire de l'Union.
- **La mise à jour** : comment chacun relit et met à jour ses informations, et donne ou retire son accord.
- **Le téléphone des participants** à un événement (utile le jour J) attend ce chantier : rien n'est stocké d'ici là.

## Messagerie interne
- Pour commencer, « Écrire à tous » et « Écrire à… » sont de simples liens e-mail, les destinataires en copie cachée.
- **Plus tard** : envoyer un message à une personne ou à un groupe par le site, sans montrer les adresses. À évaluer : c'est vite compliqué, ne pas en faire une messagerie complète.

## Application installable et notifications
- **Fait (8 novembre 2026)** : le site s'installe comme une application (Android, iPhone) ; « Rester connecté sur cet appareil » garde la connexion simple, la gestion redemande la double authentification (validité réglable).
- **Fait aussi** : bandeau « Installez l'application » pour les membres connectés, sur téléphone et tablette, seulement là où l'installation est possible (« Plus tard » : 30 jours) ; désactivé par défaut, à activer dans Administration › Paramètres (8 novembre 2026).
- **Ensuite, une fois la partie application validée par le webmaster** : **envoyer des notifications** en plus des e-mails. Exemple : une place se libère sur un événement où l'on est en liste d'attente.

### Notifications : le concept retenu (à faire plus tard, noté le 9 novembre 2026)
Séparer **ce qui est dit** de **par où ça passe**, et laisser le membre choisir simplement.

| Notion | Ce que c'est |
|---|---|
| **Contenu notifié** | Ce qui mérite d'être dit (place libérée, rappel de la veille…). Catalogue fixé dans l'outil : nom, famille, public, canaux permis et par défaut, texte court pour l'écran verrouillé. |
| **Canal** | Par où ça passe : cloche dans le site (toujours, c'est l'historique), push (par appareil), mail immédiat, mail résumé (jour ou semaine). Plus tard : Signal (pour la veille), sans rien changer au reste. |
| **Autorisation** | Le membre dit « j'autorise tel contenu ». Ses canaux sont choisis une fois (push sur cet appareil, mail immédiat ou résumé). L'admin fixe les défauts et peut interdire un canal pour un contenu. |

Familles, d'après les 21 mails envoyés aujourd'hui :
- **Sécurité du compte** (code de connexion, code de confirmation, mot de passe changé, double authentification ou clé ajoutée) : toujours par mail, jamais coupable.
- **Mes inscriptions** (place libérée, dernières places, délai de paiement, liste d'attente, invitation, inscription annulée, rappel de la veille).
- **Mes espaces** (rappel d'une date clé ; à venir : nouveau fichier, nouvel événement).
- **J'organise** (liste à valider).
- **Je gère le site** (adhésion à valider, message de contact).
- **Adhésion** (bienvenue, cotisation reçue) : jamais coupable.

Mécanique :
- Un fait crée une seule notification, distribuée sur les canaux autorisés : pas de doublon, une trace par envoi.
- Repli par mail si aucun canal n'est actif.
- Heures calmes : pas de push la nuit.
- Les rafales sont regroupées.
- Désinscription en un clic dans chaque mail.
- Texte neutre possible sur l'écran verrouillé.
- Écran : Paramètres › Notifications.

Push : Web Push en PHP pur (VAPID, aes128gcm avec openssl), une table des abonnements par appareil, envoi après la réponse de la page, avec la tâche planifiée en secours.

Ordre de travail :
1. Moteur et cloche (les mails passent dessus sans changement visible).
2. Écran des autorisations.
3. Push.
4. Résumés.
5. Plus tard : la veille et Signal.

Questions encore ouvertes :
- Écran verrouillé : texte neutre ou titre ?
- Rappel de la veille : push au lieu du mail, ou les deux ?
- Notifications des organisateurs : dès la première version ?
- **Plus tard** : « Partager vers Boussole » depuis le téléphone (utile à la veille).

## Veille
Proposition faite le 8 novembre 2026, à reprendre plus tard (rien n'est codé).
- **Saisie** : coller un lien ; titre, résumé, source et date se remplissent seuls ; une catégorie, un commentaire facultatif. Secondaire : par l'IA (MCP).
- **Organisation** : un espace « Veille » dont le n°1 est le responsable ; 3 à 8 catégories ; fiches réservées aux membres par défaut, publiques au cas par cas.
- **Diffusion** : page « Veille » ; lettre groupée par e-mail (abonnement par catégorie, désabonnement en un clic) ; bouton « Copier pour Signal » (pas d'envoi automatique : aucun moyen officiel).
- **Juridique** : lien, titre, court résumé et source (droit de citation) ; ni article ni image recopiés.

## Décisions déjà prises (8 novembre 2026)
- **Heure de fin** des événements : ajoutée, facultative.
- **Le jour J, un absent n'est pas remplacé** par le premier de la liste d'attente : il est seulement noté absent (non pointé sur l'émargement).
