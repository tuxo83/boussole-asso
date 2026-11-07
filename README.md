# Boussole

Site et outil de gestion pour les associations : une partie publique, un espace membres et des espaces de travail (comités, commissions, projets, événements), avec rappels automatiques et connexion aux IA par MCP.

> Projet en cours de développement.

## Principes

- **Universel** : PHP et MySQL / MariaDB, sans framework ni composer. Fonctionne chez la plupart des hébergeurs.
- **Rien en dur** : nom de l'association, couleurs, logo, textes et structure se règlent dans le site (ou par le MCP), jamais dans le code.
- **Durable** : du code simple, des composants réutilisables, une page = un fichier.

## Installation

1. Envoyer le contenu du dossier `app/` chez l'hébergeur : `./deploiement-ftp.sh` (FTPS) ou un logiciel FTP.
2. Ouvrir le site : l'assistant d'installation demande la base de données, le nom de l'association et le premier administrateur.

Détails, mises à jour, tâches planifiées et nginx : [docs/installation.md](docs/installation.md).
Sur un serveur « simple-web-server », `install.sh` déploie et configure le site automatiquement.

## Contribuer

Les commits passent par les contrôles de `.githooks/` : `git config core.hooksPath .githooks`.
