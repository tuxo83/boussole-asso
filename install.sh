#!/bin/bash
#
# Déploiement sur simple-web-server (droits utilisateur limités, jamais root) :
# copie app/ dans ../html, configuration depuis ../sql, migrations, tâches planifiées.
# Ailleurs : copier app/ sur l'hébergement et suivre l'assistant d'installation.
#

######################### Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HTML_DIR="$(cd "$SCRIPT_DIR/../html" && pwd)" || { echo "ERREUR : dossier ../html introuvable"; exit 1; }
SQL_DIR="$SCRIPT_DIR/../sql"

######################### Vérifications
if [[ $EUID -eq 0 ]]; then
    echo "ERREUR : ce script ne doit pas être exécuté en root"
    exit 1
fi

######################### Code
# La configuration et les fichiers déposés appartiennent à l'installation : jamais écrasés
rsync -a --delete \
    --exclude '/config/config.php' --exclude '/config/VERSION.txt' \
    --include '/uploads/.htaccess' --exclude '/uploads/*' \
    --include '/stockage/.htaccess' --exclude '/stockage/*' \
    "$SCRIPT_DIR/app/" "$HTML_DIR/" || exit 1

######################### Version (versioning automatique de la documentation d'infra, adapté à simple-web-server)
# MAJOR.MINOR dans VERSION ; PATCH = commits (hors fusions) depuis le dernier changement de VERSION ; suffixe selon la branche.
# Écrite hors de la partie publique (config/, interdit au navigateur) : seuls les administrateurs la voient, dans Paramètres.
GIT_HOME=$(mktemp -d)
HOME="$GIT_HOME" git config --global --add safe.directory "$SCRIPT_DIR" 2>/dev/null || true
BASE=$(cat "$SCRIPT_DIR/VERSION" 2>/dev/null || echo "0.1")
MAJOR=${BASE%%.*}
MINOR=${BASE##*.}
BRANCH=$(HOME="$GIT_HOME" git -C "$SCRIPT_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")
COMMIT=$(HOME="$GIT_HOME" git -C "$SCRIPT_DIR" rev-parse --short HEAD 2>/dev/null || date +%s | md5sum | cut -c1-7)
LAST_V=$(HOME="$GIT_HOME" git -C "$SCRIPT_DIR" log --oneline -- VERSION 2>/dev/null | head -1 | cut -d' ' -f1)
PATCH=$( [ -n "$LAST_V" ] && HOME="$GIT_HOME" git -C "$SCRIPT_DIR" rev-list --count --no-merges "${LAST_V}..HEAD" 2>/dev/null || echo "0")
DATE_COMMIT=$(HOME="$GIT_HOME" git -C "$SCRIPT_DIR" log -1 --format=%cI 2>/dev/null || date -Iseconds)
rm -rf "$GIT_HOME"
BRANCH_CLEAN=$(echo "$BRANCH" | tr '/' '-' | tr -cd 'a-zA-Z0-9-' | cut -c1-20)
case "$BRANCH" in
    dev)            BUILD_VERSION="$MAJOR.$MINOR.$PATCH-dev.$COMMIT" ;;
    preprod)        BUILD_VERSION="$MAJOR.$MINOR.$PATCH-rc.$COMMIT" ;;
    main|master)    BUILD_VERSION="$MAJOR.$MINOR.$PATCH" ;;
    unknown|HEAD)   BUILD_VERSION="$MAJOR.$MINOR.$PATCH-build.$COMMIT" ;;
    *)              BUILD_VERSION="$MAJOR.$MINOR.$PATCH-$BRANCH_CLEAN.$COMMIT" ;;
esac
printf '%s\n%s\n%s\n%s\n' "$BUILD_VERSION" "$COMMIT" "$DATE_COMMIT" "$(date -Iseconds)" > "$HTML_DIR/config/VERSION.txt"
echo "Version : $BUILD_VERSION (branche $BRANCH, commit $COMMIT)"

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
