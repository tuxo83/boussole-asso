#!/bin/bash
#
# Déploiement sur simple-web-server (droits utilisateur limités, jamais root) :
# copie app/ dans ../html, configuration depuis ../sql, migrations, tâches planifiées.
# Ailleurs : copier app/ sur l'hébergement et suivre l'assistant d'installation.
#

######################### Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HTML_DIR="$SCRIPT_DIR/../html"
SQL_DIR="$SCRIPT_DIR/../sql"

######################### Vérifications
if [[ $EUID -eq 0 ]]; then
    echo "ERREUR : ce script ne doit pas être exécuté en root"
    exit 1
fi

######################### Code
# La configuration et les fichiers déposés appartiennent à l'installation : jamais écrasés
rsync -a --delete \
    --exclude '/config/config.php' \
    --include '/uploads/.htaccess' --exclude '/uploads/*' \
    "$SCRIPT_DIR/app/" "$HTML_DIR/" || exit 1

######################### Base de données
php "$HTML_DIR/bin/cli.php" config-sql "$SQL_DIR/db" "$SQL_DIR/login" "$SQL_DIR/pwd" || exit 1
php "$HTML_DIR/bin/cli.php" migrate || exit 1

######################### Tâches planifiées (rappels, relances), remplacées à chaque déploiement
CRON_TAG="# autocrontab-boussole"
{
    crontab -l 2>/dev/null | grep -v "$CRON_TAG"
    echo "*/10 * * * * php $HTML_DIR/bin/cli.php cron >> \"\$HOME/boussole-cron.log\" 2>&1 $CRON_TAG"
} | crontab -

echo "Déploiement terminé"
