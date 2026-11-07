#!/bin/bash
# Test de l'installateur en un fichier : paquet publié, empreinte vérifiée, code d'installation, assistant
# Usage : tests/e2e-installateur.sh <base> <utilisateur sql> <mot de passe sql> <dossier de travail>
set -u
DB=$1 DBU=$2 DBP=$3 W=$4
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
rm -rf "$W"; mkdir -p "$W/pub" "$W/site"
ok=0 ko=0
# shellcheck disable=SC2053
check() { if [[ "$2" == $3 ]]; then echo "OK  $1 ($2)"; ok=$((ok+1)); else echo "KO  $1 : obtenu « $2 », attendu « $3 »"; ko=$((ko+1)); fi; }
mysql -u "$DBU" -p"$DBP" -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
B="$W/page.html" J="$W/cookies"
c() { curl -s -b "$J" -c "$J" -o "$B" -w '%{http_code}' "$@"; }
tok() { grep -o 'name="_csrf" value="[^"]*"' "$B" | head -1 | sed 's/.*value="//;s/"//'; }

"$ROOT/outils/paquet.sh" v9.9.9 http://127.0.0.1:8094 "$W/pub" > /dev/null
php -S 127.0.0.1:8094 -t "$W/pub" > "$W/pub.log" 2>&1 &
PUB=$!
php -S 127.0.0.1:8093 -t "$W/site" > "$W/site.log" 2>&1 &
SITE=$!
trap 'kill $PUB $SITE 2>/dev/null' EXIT; sleep 1

# Fichier altéré (empreinte différente) : rien n'est installé
sed "s/^const EMPREINTE = '[0-9a-f]*'/const EMPREINTE = '$(printf '0%.0s' {1..64})'/" "$W/pub/installer-boussole.php" > "$W/site/installer-boussole.php"
c -X POST "http://127.0.0.1:8093/installer-boussole.php" >/dev/null
check "fichier altéré : installation refusée" "$(grep -c 'empreinte différente' "$B") $([ -f "$W/site/index.php" ] && echo installé || echo rien)" "1 rien"

cp "$W/pub/installer-boussole.php" "$W/site/"
c "http://127.0.0.1:8093/installer-boussole.php" >/dev/null
check "vérifications affichées, bouton d'installation" "$(grep -c 'class="ko"' "$B") $(grep -c 'Télécharger et installer' "$B")" "0 1"
c -X POST "http://127.0.0.1:8093/installer-boussole.php" >/dev/null
CODE=$(grep -o 'id="code">[A-F0-9-]*' "$B" | sed 's/.*>//')
check "installé : code affiché" "$CODE" "[A-F0-9][A-F0-9][A-F0-9][A-F0-9]-[A-F0-9][A-F0-9][A-F0-9][A-F0-9]"
check "fichiers en place, installateur supprimé" "$([ -f "$W/site/index.php" ] && echo index) $([ -f "$W/site/installer-boussole.php" ] && echo encore || echo supprimé)" "index supprimé"
check "code rangé hors d'atteinte du web (config/)" "$(grep -c "$CODE" "$W/site/config/code-installation.php") $(grep -c 'Require all denied' "$W/site/config/.htaccess")" "1 1"

# L'assistant exige le code
kill $SITE; php -S 127.0.0.1:8093 -t "$W/site" "$ROOT/tests/router-serveur-php.php" > "$W/site.log" 2>&1 &
SITE=$!; sleep 1
c "http://127.0.0.1:8093/installation" >/dev/null
check "l'assistant demande le code" "$(grep -c 'name="code_installation"' "$B")" "1"
form() { c "http://127.0.0.1:8093/installation" >/dev/null; c --data-urlencode "_csrf=$(tok)" -d "db_host=127.0.0.1&db_port=3306&db_name=$DB&db_user=$DBU" --data-urlencode "db_password=$DBP" \
    -d "nom_association=Asso test&email_admin=admin@example.org&mot_de_passe=motdepasse-tres-long&confirmation=motdepasse-tres-long&code_installation=$1" "http://127.0.0.1:8093/installation"; }
check "mauvais code : refusé" "$(form FAUX-CODE) $(grep -c 'installation incorrect' "$B")" "422 1"
check "bon code : installation terminée" "$(form "$CODE")" "303"
check "code supprimé après usage" "$([ -f "$W/site/config/code-installation.php" ] && echo présent || echo supprimé)" "supprimé"
check "site en ligne" "$(c "http://127.0.0.1:8093/")" "200"

cp "$W/pub/installer-boussole.php" "$W/site/"
kill $SITE; php -S 127.0.0.1:8093 -t "$W/site" > "$W/site.log" 2>&1 &
SITE=$!; sleep 1
c -X POST "http://127.0.0.1:8093/installer-boussole.php" >/dev/null
check "installateur sur un site existant : ne touche à rien" "$(grep -c 'déjà installé ici' "$B")" "1"

echo "Bilan : $ok OK, $ko KO"
[ "$ko" -eq 0 ]
