#!/bin/bash
# Test de bout en bout de l'étape 2 avec le serveur intégré de PHP et une base MariaDB locale
# Usage : tests/e2e-etape2.sh <base> <utilisateur> <mot de passe> <dossier de travail>
set -u
DB=$1 DBU=$2 DBP=$3 W=$4
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT=8099 U="http://127.0.0.1:8099"
mkdir -p "$W"; J="$W/cookies"; B="$W/body.html"; MAIL="$W/mails.txt"; rm -f "$J" "$MAIL"
mysql -u "$DBU" -p"$DBP" -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
cat > "$ROOT/app/config/config.php" <<PHP
<?php
return ['db' => ['host' => '${MYSQL_HOST:-127.0.0.1}', 'port' => ${MYSQL_TCP_PORT:-3306}, 'name' => '$DB', 'user' => '$DBU', 'password' => '$DBP'], 'secret' => 'test-secret', 'mail_fichier' => '$MAIL'];
PHP
php -S 127.0.0.1:$PORT -t "$ROOT/app" "$ROOT/tests/router-serveur-php.php" > "$W/serveur.log" 2>&1 &
SERVER=$!; trap 'kill $SERVER; rm -f "$ROOT/app/config/config.php"' EXIT; sleep 1
c() { curl -s -b "$J" -c "$J" -o "$B" -w '%{http_code} %{redirect_url}' "$@"; }
tok() { grep -o 'name="_csrf" value="[^"]*"' "$B" | head -1 | sed 's/.*value="//;s/"//'; }
# Formulaire « humain » : relit la page du formulaire, attend le délai minimal, calcule la preuve de travail
# shellcheck disable=SC2016
proof() { php -r '[$s,$d]=explode(".",$argv[1]); for($n=0;;$n++){$h=hash("sha256",$s.$n,true);$b=0;foreach(str_split($h) as $c){$o=ord($c);if($o===0){$b+=8;continue;}$b+=7-(int)floor(log($o,2));break;} if($b>=$d){echo $n;break;}}' "$1"; }
hpost() { # hpost <page du formulaire> <action> <données…>
    local page=$1 action=$2; shift 2
    c "$page" >/dev/null; local t d; t=$(tok); d=$(grep -o 'name="_h_defi" value="[^"]*"' "$B" | sed 's/.*value="//;s/"//')
    sleep 2
    c --data-urlencode "_csrf=$t" --data-urlencode "_h_defi=$d" -d "_h_preuve=$(proof "$d")" "$@" "$action"
}
ok=0 ko=0
# Le 3e argument est un motif (glob) volontairement
# shellcheck disable=SC2053
check() { if [[ "$2" == $3 ]]; then echo "OK  $1 ($2)"; ok=$((ok+1)); else echo "KO  $1 : obtenu « $2 », attendu « $3 »"; ko=$((ko+1)); fi; }
# Code PHP passé tel quel à php -r
# shellcheck disable=SC2016
totp() { php -r 'require $argv[1]."/app/core/totp.php"; echo totp_code($argv[2], intdiv(time(),30));' "$ROOT" "$1"; }

c "$U/installation" >/dev/null; T=$(tok)
check "installation : mot de passe trop court refusé" "$(c --data-urlencode "_csrf=$T" -d 'nom_association=Asso&email_admin=admin@example.org&mot_de_passe=court&confirmation=court' "$U/installation")" "422 "
check "installation complète" "$(c --data-urlencode "_csrf=$T" -d 'nom_association=Asso test&email_admin=Admin@example.org&mot_de_passe=motdepasse-tres-long&confirmation=motdepasse-tres-long' "$U/installation")" "303 $U/"
check "connexion : mauvais mot de passe" "$(hpost "$U/connexion" "$U/connexion" -d 'email=admin@example.org&mot_de_passe=faux')" "422 "
check "connexion : bon mot de passe" "$(hpost "$U/connexion" "$U/connexion" -d 'email=ADMIN@example.org&mot_de_passe=motdepasse-tres-long')" "303 $U/compte"
check "admin avec une seule preuve → seconde vérification demandée" "$(c "$U/admin/parametres")" "303 $U/connexion/double-authentification"
c "$U/connexion/double-authentification" >/dev/null; T=$(tok)
check "second facteur : code par e-mail proposé, pas d'application exigée" "$(grep -c 'M&#039;envoyer un code\|M.envoyer un code' "$B")" "1"
c --data-urlencode "_csrf=$T" "$U/connexion/double-authentification/envoyer-code" >/dev/null
c "$U/connexion/double-authentification" >/dev/null; T=$(tok)
check "code e-mail : mauvais code refusé" "$(c --data-urlencode "_csrf=$T" -d 'code=000000' "$U/connexion/double-authentification/code")" "422 "
c "$U/connexion/double-authentification" >/dev/null; T=$(tok)
check "code e-mail : bon code, retour à la page demandée" "$(c --data-urlencode "_csrf=$T" -d "code=$(grep -o 'Votre code de confirmation : [0-9]*' "$MAIL" | tail -1 | grep -o '[0-9]*$')" "$U/connexion/double-authentification/code")" "303 $U/admin/parametres"
c "$U/compte/double-authentification" >/dev/null; T=$(tok); S=$(grep -o '<code class="cle">[^<]*' "$B" | sed 's/.*>//; s/ //g')
check "activation 2FA : mauvais code" "$(c --data-urlencode "_csrf=$T" -d 'code=000000' "$U/compte/double-authentification")" "422 "
check "activation 2FA : bon code" "$(c --data-urlencode "_csrf=$T" -d "code=$(totp "$S")" "$U/compte/double-authentification")" "303 $U/compte/parametres"
check "admin accessible" "$(c "$U/admin/parametres")" "200 "
T=$(tok)
check "réglages : CSS avec </style refusé" "$(c --data-urlencode "_csrf=$T" -d 'nom_association=Asso test&couleur_principale=#123456&couleur_accent=#654321&smtp_securite=starttls&css_personnalise=body{}</style><script>' "$U/admin/parametres")" "422 "
check "réglages : couleur invalide refusée" "$(c --data-urlencode "_csrf=$T" -d 'nom_association=Asso test&couleur_principale=red;}&couleur_accent=#654321&smtp_securite=starttls' "$U/admin/parametres")" "422 "
# shellcheck disable=SC2016
php -r '$i=imagecreatetruecolor(4,4); imagepng($i,$argv[1]);' "$W/logo.png" 2>/dev/null || printf '\x89PNG\r\n\x1a\n' > "$W/logo.png"
printf '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' > "$W/logo.svg"
check "réglages : logo SVG refusé" "$(c -F "_csrf=$T" -F 'nom_association=Asso test' -F 'couleur_principale=#123456' -F 'couleur_accent=#654321' -F 'smtp_securite=starttls' -F "logo=@$W/logo.svg" "$U/admin/parametres")" "422 "
check "réglages valides + CSS + expéditeur" "$(c -F "_csrf=$T" -F 'nom_association=Asso test' -F 'couleur_principale=#123456' -F 'couleur_accent=#654321' -F 'smtp_securite=starttls' -F 'css_personnalise=body{color:#111}' -F 'mail_expediteur=noreply@example.org' "$U/admin/parametres")" "303 $U/admin/parametres"
# Tester l'envoi des e-mails avec les réglages saisis, sans les enregistrer (faux serveur SMTP local)
PSMTP=$((20000 + RANDOM % 20000))
python3 "$(dirname "$0")/smtp_simule.py" "$PSMTP" & SMTP_PID=$!; sleep 0.5
mailtest() { c "$U/admin/parametres" >/dev/null; local t; t=$(tok); c --data-urlencode "_csrf=$t" -d "$SMTPD&smtp_mot_de_passe=$1" "$U/admin/mail-test"; }
SMTPD="nom_association=Asso test&couleur_principale=%23123456&couleur_accent=%23654321&mail_expediteur=noreply@example.org&smtp_hote=127.0.0.1&smtp_port=$PSMTP&smtp_securite=aucune&smtp_utilisateur=u&test_destinataire=moi@example.org"
check "test d'envoi : accepté par le serveur saisi" "$(mailtest bon >/dev/null; grep -c "Accepté par le serveur 127.0.0.1:$PSMTP pour moi@example.org" "$B")" "1"
check "test d'envoi : réglages non enregistrés, formulaire resté rempli" "$(mysql -N -u "$DBU" -p"$DBP" "$DB" -e "SELECT COUNT(*) FROM parametre WHERE cle = 'smtp_hote' AND valeur = '127.0.0.1'" 2>/dev/null) $(grep -c "value=\"$PSMTP\"" "$B")" "0 1"
check "test d'envoi : mot de passe refusé, raison affichée" "$(mailtest mauvais >/dev/null; grep -c 'identifiant ou mot de passe refusé' "$B")" "1"
kill "$SMTP_PID"
check "test d'envoi : serveur injoignable, raison affichée" "$(mailtest bon >/dev/null; grep -c "connexion impossible à 127.0.0.1:$PSMTP" "$B")" "1"
check "feuille CSS personnalisée servie" "$(c "$U/theme.css")$(cat "$B")" "200 body{color:#111}"
c "$U/" >/dev/null; check "couleur appliquée dans la page" "$(grep -c -- '--couleur-principale:#123456' "$B")" "1"
c "$U/compte" >/dev/null; T=$(tok); c --data-urlencode "_csrf=$T" "$U/deconnexion" >/dev/null
check "après déconnexion, compte inaccessible" "$(c "$U/compte")" "303 $U/connexion"
hpost "$U/connexion" "$U/connexion" -d 'email=admin@example.org&mot_de_passe=motdepasse-tres-long' >/dev/null
check "avec 2FA active : 2e étape demandée" "$(c "$U/compte")" "303 $U/connexion"
c "$U/connexion/double-authentification" >/dev/null; T=$(tok)
check "2e étape : mauvais code" "$(c --data-urlencode "_csrf=$T" -d 'code=111111' "$U/connexion/double-authentification")" "422 "
check "2e étape : bon code" "$(c --data-urlencode "_csrf=$T" -d "code=$(totp "$S")" "$U/connexion/double-authentification")" "303 $U/compte"
c "$U/compte" >/dev/null; T=$(tok); c --data-urlencode "_csrf=$T" "$U/deconnexion" >/dev/null

# Lien magique pour un membre sans mot de passe
mysql -u "$DBU" -p"$DBP" "$DB" -e "INSERT INTO membre (email) VALUES ('membre@example.org')" 2>/dev/null
check "inconnu : même réponse (pas de révélation)" "$(hpost "$U/connexion" "$U/connexion" -d 'email=personne@example.org')" "303 $U/connexion/code"
check "aucun e-mail pour une adresse inconnue" "$(grep -o 'personne@example.org' "$MAIL" 2>/dev/null | wc -l)" "0"
check "demande de lien" "$(hpost "$U/connexion" "$U/connexion" -d 'email=membre@example.org')" "303 $U/connexion/code"
LIEN=$(grep -o 'http://[^ ]*/connexion/lien/[a-f0-9]*' "$MAIL" | tail -1); CODE=$(grep -o 'code sur la page de connexion : [0-9]*' "$MAIL" | tail -1 | grep -o '[0-9]*$')
check "le lien dans l'e-mail vient de l'adresse figée" "$LIEN" "http://127.0.0.1:8099/connexion/lien/*"
check "ouvrir le lien ne connecte pas encore (aperçu)" "$(c "$LIEN")" "200 "
T=$(tok)
check "confirmer le lien connecte" "$(c --data-urlencode "_csrf=$T" "$LIEN")" "303 $U/compte"
c "$U/compte" >/dev/null; T=$(tok); c --data-urlencode "_csrf=$T" "$U/deconnexion" >/dev/null
c "$LIEN" >/dev/null; T=$(tok)
check "lien déjà utilisé refusé" "$(c --data-urlencode "_csrf=$T" "$LIEN")" "303 $U/connexion"
hpost "$U/connexion" "$U/connexion" -d 'email=membre@example.org' >/dev/null
CODE=$(grep -o 'code sur la page de connexion : [0-9]*' "$MAIL" | tail -1 | grep -o '[0-9]*$')
for _ in 1 2 3 4 5; do hpost "$U/connexion/code" "$U/connexion/code" -d 'code=999999' >/dev/null; done
check "code bloqué après 5 erreurs, même le bon" "$(hpost "$U/connexion/code" "$U/connexion/code" -d "code=$CODE")" "422 "
# Captcha invisible
c "$U/connexion" >/dev/null; T=$(tok); D=$(grep -o 'name="_h_defi" value="[^"]*"' "$B" | sed 's/.*value="//;s/"//')
check "captcha : champ piège rempli → refus" "$(c --data-urlencode "_csrf=$T" --data-urlencode "_h_defi=$D" -d "_h_preuve=$(proof "$D")&site_web=spam&email=membre@example.org" "$U/connexion")" "400 "
check "captcha : envoi instantané → refus" "$(c --data-urlencode "_csrf=$T" --data-urlencode "_h_defi=$D" -d '_h_preuve=1&email=membre@example.org' "$U/connexion")" "400 "
check "captcha : défi falsifié → refus" "$(c --data-urlencode "_csrf=$T" -d '_h_defi=aaaa.16.1.bbbb&_h_preuve=1&email=membre@example.org' "$U/connexion")" "400 "
check "captcha : mauvaise preuve → refus" "$(sleep 2; c --data-urlencode "_csrf=$T" --data-urlencode "_h_defi=$D" -d '_h_preuve=1&email=membre@example.org' "$U/connexion")" "400 "
c "$U/connexion" >/dev/null; T=$(tok); D=$(grep -o 'name="_h_defi" value="[^"]*"' "$B" | sed 's/.*value="//;s/"//'); sleep 2; P=$(proof "$D")
c --data-urlencode "_csrf=$T" --data-urlencode "_h_defi=$D" -d "_h_preuve=$P&email=membre@example.org" "$U/connexion" >/dev/null
check "captcha : même défi rejoué → refus" "$(c --data-urlencode "_csrf=$T" --data-urlencode "_h_defi=$D" -d "_h_preuve=$P&email=membre@example.org" "$U/connexion")" "400 "
for _ in $(seq 1 7); do hpost "$U/connexion" "$U/connexion" -d 'email=membre@example.org' >/dev/null; done
check "trop de demandes pour un e-mail : 429" "$(hpost "$U/connexion" "$U/connexion" -d 'email=membre@example.org')" "429 "
check "formulaire sans jeton CSRF refusé" "$(c -d 'email=membre@example.org' "$U/connexion")" "400 "
grep -i -E 'fatal|warning|notice|deprecated' "$W/serveur.log" | head -5
echo "Bilan : $ok OK, $ko KO"
[ "$ko" -eq 0 ]
