# Installer Boussole chez un hébergeur

## Ce qu'il faut

| Élément | Minimum |
|---|---|
| PHP | 8.1 ou plus récent (8.3 ou plus conseillé : 8.1 ne reçoit plus de correctifs de sécurité, 8.2 jusqu'à fin 2026), avec les extensions `pdo_mysql`, `openssl`, `mbstring`, `gd` (images), `dom` (mise en forme des pages, facultative) |
| Base de données | MySQL 5.7+ ou MariaDB 10.3+ |
| Serveur web | Apache (fichiers `.htaccess` fournis) ou nginx (voir plus bas) |
| Tâches planifiées | un cron toutes les 10 minutes si l'hébergeur le permet ; sinon les visites du site s'en chargent |

La plupart des offres mutualisées conviennent (OVH, o2switch, Infomaniak, IONOS, Gandi…).

## Trois façons d'installer

| Façon | Pour qui | Comment |
|---|---|---|
| Installateur en un fichier | une association sans technicien | Télécharger `installer-boussole.php` depuis la dernière version publiée sur GitHub, le déposer à la racine du site (gestionnaire de fichiers de l'hébergeur ou FTP), l'ouvrir dans le navigateur. Il vérifie l'hébergement, télécharge Boussole, contrôle son empreinte, l'installe, affiche un **code d'installation** à noter, puis se supprime. |
| Script FTPS | quelqu'un à l'aise avec un terminal | `./deploiement-ftp.sh` (voir ci-dessous) |
| Déploiement automatique par GitHub | une association qui suit les nouvelles versions | Copier le dépôt (fork), puis dans la copie : *Settings › Secrets and variables › Actions*, créer `FTP_HOTE`, `FTP_UTILISATEUR`, `FTP_MOT_DE_PASSE`, `FTP_DOSSIER` (et `SITE_ADRESSE`). À chaque mise à jour de la copie, GitHub envoie le site par FTPS. Les secrets restent dans la copie de l'association. |

Le code d'installation empêche une autre personne de terminer l'installation à votre place entre le dépôt du fichier et votre première visite.

## Sur un serveur simple-web-server

Le serveur clone lui-même le dépôt dans `/var/www/<domaine>/git` (hors du site), puis lance `install.sh` en tant qu'utilisateur du site. `install.sh` copie `app/` dans `../html`, lit la base dans `../sql` (db, login, pwd), applique les migrations et installe la tâche planifiée de l'utilisateur. Le serveur vérifie ensuite régulièrement la branche et relance `install.sh` à chaque changement.

1. Création du site, depuis le dépôt public (sans clé) :
   ```bash
   <prefixe>-app-internal-simple-web-server-new -d www.mon-asso.org \
       --git-repo-url https://github.com/tuxo83/boussole-asso.git --git-branch main
   ```
2. Pour un dépôt privé (une copie de l'association par exemple) : même commande avec l'adresse `git@…` et `--git-priv-key <clé de déploiement en lecture seule>`.
3. Le journal affiche un **code d'installation** : ouvrir le site, l'assistant le demande avec le nom de l'association et le premier administrateur.

Seule la branche `main` est déployée : ce qui y arrive a été validé.

## Première installation (hébergeur ordinaire)

1. Créer une base de données dans l'espace client de l'hébergeur et noter : serveur, nom de la base, utilisateur, mot de passe.
2. Envoyer le contenu du dossier `app/` à la racine du site (souvent `/www` ou `/public_html`) :
   - avec le script : copier `deploiement-ftp.conf.exemple` en `deploiement-ftp.conf`, le compléter, puis lancer `./deploiement-ftp.sh` ;
   - ou avec un logiciel FTP (FileZilla…) : envoyer le contenu de `app/`, fichiers cachés `.htaccess` compris.
3. Ouvrir l'adresse du site : l'assistant demande la base de données, le nom de l'association et le premier administrateur (qui choisit son mot de passe).

## Mettre à jour

1. Récupérer la nouvelle version, puis `./deploiement-ftp.sh --essai` pour voir ce qui changera, et `./deploiement-ftp.sh` pour l'envoyer.
2. C'est tout : la base de données se met à jour à la première visite. La configuration (`config/config.php`), les images et les fichiers déposés ne sont jamais touchés.

## Tâches planifiées (rappels, relances, clôtures, HelloAsso)

- **Avec un cron** (recommandé), dans l'espace client de l'hébergeur : toutes les 10 minutes, `php /chemin/du/site/bin/cli.php cron`.
- **Sans cron** : rien à faire, les visites du site lancent les tâches au plus toutes les 10 minutes, après l'envoi de la page. Sur un site très peu visité, un rappel peut alors partir avec un peu de retard.

## nginx

nginx ne lit pas les fichiers `.htaccess` : ajouter ces règles au bloc `server` du site.

```nginx
location ~ ^/(config|core|views|modules|stockage|migrations|bin)/ { deny all; }
location ~ ^/uploads/.*\.(php|phtml|phar|pl|py|cgi|sh)$ { deny all; }
location / { try_files $uri /index.php$is_args$args; }
```

## Sécurité

- Préférer le FTPS (`FTP_SECURITE="ftps"`, par défaut) : en FTP simple, le mot de passe circule en clair.
- Laisser `FTP_MOT_DE_PASSE` vide : il est demandé à chaque envoi et n'est écrit nulle part.
- `deploiement-ftp.conf` n'est jamais commité (`.gitignore`).
