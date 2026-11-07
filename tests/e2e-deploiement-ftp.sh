#!/bin/bash
# Test du déploiement FTP : serveur FTPS local, essai à blanc, envoi, fichiers du site préservés, site qui répond,
# tâches planifiées lancées par les visites (hébergeur sans cron)
# Usage : tests/e2e-deploiement-ftp.sh <python avec pyftpdlib> <base> <utilisateur sql> <mot de passe sql> <dossier de travail>
set -u
PY=$1 DB=$2 DBU=$3 DBP=$4 W=$5
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
rm -rf "$W"; mkdir -p "$W/www/config" "$W/www/uploads" "$W/www/stockage"
ok=0 ko=0
# shellcheck disable=SC2053
check() { if [[ "$2" == $3 ]]; then echo "OK  $1 ($2)"; ok=$((ok+1)); else echo "KO  $1 : obtenu « $2 », attendu « $3 »"; ko=$((ko+1)); fi; }

# Un site déjà en ligne : sa configuration, ses images, ses fichiers, et un vieux fichier à supprimer
mysql -u "$DBU" -p"$DBP" -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
cat > "$W/www/config/config.php" <<PHP
<?php
return ['db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => '$DB', 'user' => '$DBU', 'password' => '$DBP'], 'secret' => 'secret-du-site', 'mail_fichier' => '$W/mails.txt'];
PHP
echo image > "$W/www/uploads/img-0123456789abcdef01234567.webp"
echo document > "$W/www/stockage/0123456789abcdef"
echo ancien > "$W/www/ancien-fichier.php"

openssl req -x509 -newkey rsa:2048 -nodes -keyout "$W/cle.pem" -out "$W/cert.pem" -days 1 -subj '/CN=127.0.0.1' 2>/dev/null
cat "$W/cle.pem" "$W/cert.pem" > "$W/ftp.pem"
"$PY" "$ROOT/tests/serveur_ftp.py" 2121 "$W/www" deploy 'mot de passe FTP' "$W/ftp.pem" > "$W/ftp.log" 2>&1 &
FTP=$!
php -S 127.0.0.1:8095 -t "$W/www" "$ROOT/tests/router-serveur-php.php" > "$W/site.log" 2>&1 &
SITE=$!
trap 'kill $FTP $SITE' EXIT; sleep 1.5
cat > "$W/deploiement-ftp.conf" <<CONF
FTP_HOTE="127.0.0.1"
FTP_PORT="2121"
FTP_UTILISATEUR="deploy"
FTP_MOT_DE_PASSE="mot de passe FTP"
FTP_DOSSIER="/"
FTP_SECURITE="ftps"
FTP_VERIFIER_CERTIFICAT="no"
SITE_ADRESSE="http://127.0.0.1:8095"
CONF

"$ROOT/deploiement-ftp.sh" --essai "$W/deploiement-ftp.conf" > "$W/essai.log" 2>&1
check "essai à blanc : rien n'est envoyé ni supprimé" "$([ -f "$W/www/index.php" ] && echo envoyé || echo rien) $([ -f "$W/www/ancien-fichier.php" ] && echo gardé)" "rien gardé"
check "essai à blanc : la liste des changements s'affiche" "$(grep -c 'index.php' "$W/essai.log")" "[1-9]*"

"$ROOT/deploiement-ftp.sh" "$W/deploiement-ftp.conf" > "$W/envoi.log" 2>&1
check "envoi terminé : site neuf, renvoi vers l'installation" "$(tail -2 "$W/envoi.log" | tr '\n' ' ')" "Site en ligne : terminer l'installation*Déploiement terminé "
check "code envoyé" "$(cmp -s "$ROOT/app/core/app.php" "$W/www/core/app.php" && echo identique)" "identique"
check "configuration du site intacte" "$(grep -c 'secret-du-site' "$W/www/config/config.php")" "1"
check "images et fichiers déposés intacts" "$(cat "$W/www/uploads/img-0123456789abcdef01234567.webp" "$W/www/stockage/0123456789abcdef" | tr '\n' ' ')" "image document "
check "protections .htaccess envoyées" "$(for f in uploads stockage bin; do [ -f "$W/www/$f/.htaccess" ] && echo "$f"; done | wc -l)" "3"
check "fichier obsolète supprimé" "$([ -f "$W/www/ancien-fichier.php" ] && echo présent || echo supprimé)" "supprimé"
check "le mot de passe FTP n'apparaît pas dans le journal" "$(grep -c 'mot de passe FTP' "$W/envoi.log")" "0"

"$ROOT/deploiement-ftp.sh" "$W/deploiement-ftp.conf" > "$W/envoi2.log" 2>&1
check "second envoi : seulement ce qui a changé (au plus un fichier dont l'heure diffère)" "$(grep -c "Transferring file" "$W/envoi2.log")" "[01]"

# Mise à jour d'un site déjà installé : la base est mise à jour par la première visite après l'envoi
q() { mysql -u "$DBU" -p"$DBP" "$DB" -N -e "$1" 2>/dev/null; }
php "$W/www/bin/cli.php" migrate > /dev/null
q "INSERT INTO parametre (cle, valeur) VALUES ('installation_terminee', '1'), ('schema_version', '010_rappels.sql') ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)"
q "DELETE FROM schema_migration WHERE nom = '011_helloasso.sql'; ALTER TABLE evenement DROP COLUMN helloasso_formulaire; ALTER TABLE inscription DROP COLUMN paye_le"
touch "$W/www/core/app.php"
"$ROOT/deploiement-ftp.sh" "$W/deploiement-ftp.conf" > "$W/envoi3.log" 2>&1
check "mise à jour : site vérifié" "$(tail -2 "$W/envoi3.log" | head -1)" "Site en ligne (http://127.0.0.1:8095), base de données à jour"
check "mise à jour de la base appliquée par la visite" "$(q "SELECT COUNT(*) FROM schema_migration WHERE nom = '011_helloasso.sql'") $(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'inscription' AND column_name = 'paye_le'")" "1 1"

# Hébergeur sans cron : les visites lancent les tâches (au plus toutes les 10 minutes)
sleep 1
T1=$(mysql -u "$DBU" -p"$DBP" "$DB" -N -e "SELECT valeur FROM parametre WHERE cle = 'taches_derniere'" 2>/dev/null)
check "une visite lance les tâches planifiées" "${T1:-aucune}" "1[0-9]*"
curl -s -o /dev/null http://127.0.0.1:8095/; sleep 1
check "pas plus d'une fois toutes les 10 minutes" "$(mysql -u "$DBU" -p"$DBP" "$DB" -N -e "SELECT valeur FROM parametre WHERE cle = 'taches_derniere'" 2>/dev/null)" "$T1"

echo "Bilan : $ok OK, $ko KO"
[ "$ko" -eq 0 ]
