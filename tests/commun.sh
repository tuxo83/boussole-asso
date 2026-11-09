#!/bin/bash
# Outils communs aux tests de bout en bout : base neuve, serveur intégré de PHP, requêtes, vérifications
# À sourcer : source tests/commun.sh <base> <utilisateur> <mot de passe> <dossier de travail>
DB=$1 DBU=$2 DBP=$3 W=$4
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT=8097 U="http://127.0.0.1:8097"
mkdir -p "$W"; B="$W/body.html"; MAIL="$W/mails.txt"; rm -f "$W"/cookies-* "$MAIL"
J="$W/cookies-admin"
mysql -u "$DBU" -p"$DBP" -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
cat > "$ROOT/app/config/config.php" <<PHP
<?php
return ['db' => ['host' => '${MYSQL_HOST:-127.0.0.1}', 'port' => ${MYSQL_TCP_PORT:-3306}, 'name' => '$DB', 'user' => '$DBU', 'password' => '$DBP'], 'secret' => 'test-secret', 'mail_fichier' => '$MAIL', 'taches_par_les_visites' => false];
PHP
php -S 127.0.0.1:$PORT -t "$ROOT/app" "$ROOT/tests/router-serveur-php.php" > "$W/serveur.log" 2>&1 &
SERVER=$!; trap 'kill $SERVER; rm -f "$ROOT/app/config/config.php"' EXIT; sleep 1

sql() { mysql -u "$DBU" -p"$DBP" "$DB" -N -e "$1" 2>/dev/null; }
# Les tests voient les pages d'espace en « vue organisateur » (le site, lui, s'ouvre en vue membre) ; un fichier de cookies vide
# est d'abord amorcé pour que curl lise et écrive le même fichier
c() { [ -s "$J" ] || printf '# Netscape HTTP Cookie File\n127.0.0.1\tFALSE\t/\tFALSE\t0\tvue_orga\t1\n' > "$J"; curl -s -b "$J" -c "$J" -o "$B" -w '%{http_code} %{redirect_url}' "$@"; }
tok() { grep -o 'name="_csrf" value="[^"]*"' "$B" | head -1 | sed 's/.*value="//;s/"//'; }
post() { # post <page du formulaire> <action> <données…> : jeton CSRF de la page
    local page=$1 action=$2; shift 2; c "$page" >/dev/null; local t; t=$(tok); c --data-urlencode "_csrf=$t" "$@" "$action"
}
ok=0 ko=0
# Le 3e argument est un motif (glob) volontairement
# shellcheck disable=SC2053
check() { if [[ "$2" == $3 ]]; then echo "OK  $1 ($2)"; ok=$((ok+1)); else echo "KO  $1 : obtenu « $2 », attendu « $3 »"; ko=$((ko+1)); fi; }
# shellcheck disable=SC2016
totp() { php -r 'require $argv[1]."/app/core/totp.php"; echo totp_code($argv[2], intdiv(time(),30));' "$ROOT" "$1"; }
# shellcheck disable=SC2016
proof() { php -r '[$s,$d]=explode(".",$argv[1]); for($n=0;;$n++){$h=hash("sha256",$s.$n,true);$b=0;foreach(str_split($h) as $c){$o=ord($c);if($o===0){$b+=8;continue;}$b+=7-(int)floor(log($o,2));break;} if($b>=$d){echo $n;break;}}' "$1"; }
hpost() { # formulaire protégé par le captcha invisible
    local page=$1 action=$2; shift 2
    c "$page" >/dev/null; local t d; t=$(tok); d=$(grep -o 'name="_h_defi" value="[^"]*"' "$B" | sed 's/.*value="//;s/"//')
    sleep 2
    c --data-urlencode "_csrf=$t" --data-urlencode "_h_defi=$d" -d "_h_preuve=$(proof "$d")" "$@" "$action"
}
hpostf() { # formulaire anti-robot envoyé en multipart (fichier joint)
    local page=$1 action=$2; shift 2
    c "$page" >/dev/null; local t d; t=$(tok); d=$(grep -o 'name="_h_defi" value="[^"]*"' "$B" | sed 's/.*value="//;s/"//')
    sleep 2
    c -F "_csrf=$t" -F "_h_defi=$d" -F "_h_preuve=$(proof "$d")" "$@" "$action"
}
as() { J="$W/cookies-$1"; } # change d'utilisateur (un jeu de cookies par personne)
login_link() { # login_link <nom> <e-mail> : connexion par lien magique
    as "$1"; hpost "$U/connexion" "$U/connexion" -d "email=$2" >/dev/null
    local lien; lien=$(grep -o 'http://[^ ]*/connexion/lien/[a-f0-9]*' "$MAIL" | tail -1)
    c "$lien" >/dev/null; local t; t=$(tok); c --data-urlencode "_csrf=$t" "$lien" >/dev/null
}
stepup_email() { # second facteur par code e-mail (après une connexion par mot de passe)
    c "$U/connexion/double-authentification" >/dev/null; local t; t=$(tok)
    c --data-urlencode "_csrf=$t" "$U/connexion/double-authentification/envoyer-code" >/dev/null
    c "$U/connexion/double-authentification" >/dev/null; t=$(tok)
    c --data-urlencode "_csrf=$t" -d "code=$(grep -o 'Votre code de confirmation : [0-9]*' "$MAIL" | tail -1 | grep -o '[0-9]*$')" "$U/connexion/double-authentification/code"
}
install_admin() { # installation, connexion par mot de passe, double authentification
    as admin; c "$U/installation" >/dev/null; local t; t=$(tok)
    c --data-urlencode "_csrf=$t" -d 'nom_association=Asso test&email_admin=admin@example.org&mot_de_passe=motdepasse-tres-long&confirmation=motdepasse-tres-long' "$U/installation" >/dev/null
    hpost "$U/connexion" "$U/connexion" -d 'email=admin@example.org&mot_de_passe=motdepasse-tres-long' >/dev/null
    stepup_email
    c "$U/compte/double-authentification" >/dev/null; t=$(tok); ADMIN_TOTP=$(grep -o '<code class="cle">[^<]*' "$B" | sed 's/.*>//; s/ //g')
    c --data-urlencode "_csrf=$t" -d "code=$(totp "$ADMIN_TOTP")" "$U/compte/double-authentification" >/dev/null
}
bilan() { grep -i -E 'fatal|warning|notice|deprecated' "$W/serveur.log" | head -5; echo "Bilan : $ok OK, $ko KO"; [ "$ko" -eq 0 ]; }
