#!/bin/bash
# Lance toute la batterie de tests sur une autre base que celle par défaut (ex. MySQL 8.4 sur le port 3307)
# Usage : tests/sur-mysql.sh <port> <base> <utilisateur> <mot de passe> <dossier de travail>
# Le fichier app/config/config.php est sauvegardé puis remis en place.
set -u
export MYSQL_HOST=127.0.0.1 MYSQL_TCP_PORT=$1
D="$(cd "$(dirname "$0")" && pwd)"
CONF="$D/../app/config/config.php"
[ -f "$CONF" ] && cp "$CONF" "$5.config-sauvegarde.php"
statut=0
php "$D/unit-blocs.php" | tail -1 || statut=1
php "$D/unit-webauthn.php" | tail -1 || statut=1
php "$D/unit-migrations.php" | tail -1 || statut=1
for t in 2 3 5 6 7 8 9 11 12 13; do
    printf 'étape %s : ' "$t"; bash "$D/e2e-etape$t.sh" "$2" "$3" "$4" "$5/e2e$t" | grep -E '^KO|Bilan' || statut=1
done
printf 'installateur : '; bash "$D/e2e-installateur.sh" "$2" "$3" "$4" "$5/installateur" | grep -E '^KO|Bilan' || statut=1
[ -f "$5.config-sauvegarde.php" ] && cp "$5.config-sauvegarde.php" "$CONF"
exit $statut
