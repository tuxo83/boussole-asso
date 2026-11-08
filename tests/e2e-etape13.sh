#!/bin/bash
# Test de bout en bout : application installable (manifeste, icônes, service, hors ligne) et « Rester connecté sur cet appareil »
# Usage : tests/e2e-etape13.sh <base> <utilisateur> <mot de passe> <dossier de travail>
set -u
# shellcheck disable=SC1091
source "$(dirname "$0")/commun.sh" "$@"
sans_session() { sed -i '/PHPSESSID/d' "$J"; } # fermer l'application : le cookie de session part, celui de l'appareil reste
appareil() { grep -c 'boussole_appareil' "$J"; }
nb() { sql "SELECT COUNT(*) FROM connexion_appareil a JOIN membre m ON m.id = a.membre_id WHERE m.email = '$1@example.org'"; }
login_rester() { # login_rester <nom> <e-mail> <valeur de la case> : lien magique, case « Rester connecté »
    as "$1"; hpost "$U/connexion" "$U/connexion" -d "email=$2" >/dev/null
    local lien; lien=$(grep -o 'http://[^ ]*/connexion/lien/[a-f0-9]*' "$MAIL" | tail -1)
    c "$lien" >/dev/null; local t; t=$(tok); c --data-urlencode "_csrf=$t" -d "rester=$3" "$lien" >/dev/null
}

install_admin
for p in bob carole; do post "$U/admin/membres" "$U/admin/membres" -d "email=$p@example.org&prenom=${p^}" >/dev/null; done

# Installation : manifeste, icônes, service, page hors ligne
as visiteur; c "$U/" >/dev/null
check "accueil : manifeste, couleur, icône iPhone, service annoncés" "$(grep -c 'rel="manifest"\|name="theme-color"\|rel="apple-touch-icon"\|name="boussole-sw"' "$B")" "4"
c "$U/app/manifest.webmanifest" >/dev/null
# shellcheck disable=SC2016
check "manifeste : nom, nom court, départ, plein écran" "$(php -r '$m=json_decode(file_get_contents($argv[1]),true); echo $m["name"],"|",$m["short_name"],"|",$m["start_url"],"|",$m["display"],"|",count($m["icons"]);' "$B")" "Asso test|Asso test|/app/demarrer|standalone|3"
# shellcheck disable=SC2016
check "icône 512 : une vraie image PNG carrée" "$(c "$U/app/icone-512.png" >/dev/null; php -r '$i=getimagesize($argv[1]); echo $i[0],"x",$i[1]," ",$i["mime"];' "$B")" "512x512 image/png"
check "icône masquable et icône iPhone" "$(c "$U/app/icone-masque-512.png" | cut -c1-3) $(c "$U/app/icone-180.png" | cut -c1-3)" "200 200"
check "taille d'icône inconnue : introuvable" "$(c "$U/app/icone-64.png" | cut -c1-3)" "404"
check "service : JavaScript, portée tout le site" "$(curl -s -D - -o "$B" "$U/app/sw.js" | grep -ci 'service-worker-allowed: /\|content-type: text/javascript') $(grep -c 'HORS_LIGNE' "$B")" "2 [1-9]"
check "page hors ligne : autonome, sans données" "$(c "$U/app/hors-ligne" | cut -c1-3) $(grep -c 'Pas de connexion' "$B") $(grep -c 'boussole.css' "$B")" "200 1 0"
check "ouverture de l'application, non connecté : connexion" "$(c "$U/app/demarrer")" "303 $U/connexion"
sql "REPLACE INTO parametre (cle, valeur) VALUES ('construction_active', '1'), ('construction_code', '123456')"
as visiteur2; check "en construction : le manifeste reste lisible sans code" "$(c "$U/app/manifest.webmanifest" | cut -c1-3) $(c "$U/" | cut -c1-3)" "200 303"
sql "REPLACE INTO parametre (cle, valeur) VALUES ('construction_active', '')"

# Rester connecté : case à la connexion, cookie d'appareil, session rouverte après fermeture
as visiteur; c "$U/connexion" >/dev/null
check "connexion : case « Rester connecté »" "$(grep -c 'name="rester"' "$B")" "1"
login_rester bob bob@example.org 1
check "bob : appareil retenu (cookie et ligne)" "$(appareil) $(nb bob)" "1 1"
check "seule l'empreinte du secret est gardée" "$(sql "SELECT COUNT(*) FROM connexion_appareil WHERE LOCATE(empreinte, '$(grep boussole_appareil "$J" | awk '{print $7}')') > 0")" "0"
sans_session
check "bob rouvre l'application : toujours connecté" "$(c "$U/compte" | cut -c1-3)" "200"
check "membre connecté : bandeau « Installez l'application » prêt (affiché par le script, sur téléphone)" "$(c "$U/compte" >/dev/null; grep -c 'data-proposer-appli hidden' "$B") $(as visiteur; c "$U/" >/dev/null; grep -c 'data-proposer-appli' "$B")" "1 0"
as bob; check "ouverture de l'application, connecté : page d'arrivée" "$(sans_session; c "$U/app/demarrer")" "303 $U/compte"
c "$U/compte/parametres" >/dev/null
check "Paramètres : appareils connectés, « cet appareil », application" "$(grep -c '<strong>Appareils connectés</strong>' "$B") $(grep -c '>cet appareil<' "$B") $(grep -c 'id="application" data-application' "$B")" "1 1 1"
login_link carole carole@example.org
check "carole sans la case : rien de retenu" "$(appareil) $(nb carole)" "0 0"
sans_session; check "carole après fermeture : déconnectée" "$(c "$U/compte")" "303 $U/connexion"

# Cookie trafiqué : refusé, la ligne est effacée
as bob; SEL=$(grep boussole_appareil "$J" | awk '{print $7}' | cut -d. -f1)
as pirate; printf '# Netscape HTTP Cookie File\n127.0.0.1\tFALSE\t/\tFALSE\t0\tboussole_appareil\t%s.%064d\n' "$SEL" 0 > "$J"
check "secret faux : pas connecté, appareil effacé" "$(c "$U/compte") $(nb bob)" "303 $U/connexion 0"
check "format invalide : ignoré" "$(printf '# Netscape HTTP Cookie File\n127.0.0.1\tFALSE\t/\tFALSE\t0\tboussole_appareil\tabc\n' > "$J"; c "$U/compte")" "303 $U/connexion"

# Deux appareils ; changer de mot de passe déconnecte l'autre, pas celui-ci
login_rester bob3 bob@example.org 1; login_rester bob2 bob@example.org appli
check "bob : deux appareils, dont l'application" "$(nb bob) $(sql "SELECT COUNT(*) FROM connexion_appareil WHERE libelle LIKE 'Application%'")" "2 1"
as bob3; check "bob change son mot de passe" "$(post "$U/compte/parametres" "$U/compte/mot-de-passe" -d 'mot_de_passe=un-nouveau-mot-de-passe&confirmation=un-nouveau-mot-de-passe')" "303 $U/compte/parametres"
check "l'autre appareil est oublié, celui-ci reste" "$(nb bob)" "1"
as bob2; sans_session; check "l'autre appareil doit se reconnecter" "$(c "$U/compte")" "303 $U/connexion"
sql "UPDATE connexion_appareil SET derniere_utilisation = NOW() - INTERVAL 2 DAY" # dernière réouverture avant-hier
as bob3; sans_session; check "celui-ci reste connecté" "$(c "$U/compte" | cut -c1-3)" "200"
check "avis par e-mail du changement de mot de passe" "$(grep -c 'Mot de passe choisi' "$MAIL")" "1"
check "session rouverte : changer le mot de passe exige d'abord une preuve fraîche" "$(post "$U/compte/parametres" "$U/compte/mot-de-passe" -d 'mot_de_passe=pirate-pirate-pirate&confirmation=pirate-pirate-pirate') $(sql "SELECT COUNT(*) FROM journal WHERE action = 'Mot de passe changé'")" "303 $U/connexion/double-authentification 0"
check "session rouverte : ajouter sa propre application exige d'abord une preuve fraîche" "$(c "$U/compte/double-authentification")" "303 $U/connexion/double-authentification"
check "session rouverte : créer une clé d'API exige d'abord une preuve fraîche" "$(post "$U/compte/cles" "$U/compte/cles" -d 'libelle=pirate&duree=30&portee=lecture') $(sql "SELECT COUNT(*) FROM cle_api")" "303 $U/connexion/double-authentification 0"
c "$U/connexion/double-authentification" >/dev/null
check "session rouverte : toutes les preuves sont proposées (code e-mail, mot de passe)" "$(grep -c 'envoyer-code\|double-authentification/mot-de-passe' "$B")" "2"
c "$U/connexion/double-authentification" >/dev/null; T=$(tok)
c --data-urlencode "_csrf=$T" -d 'mot_de_passe=un-nouveau-mot-de-passe' "$U/connexion/double-authentification/mot-de-passe" >/dev/null
check "après le mot de passe redonné : changé" "$(post "$U/compte/parametres" "$U/compte/mot-de-passe" -d 'mot_de_passe=encore-un-autre-mot&confirmation=encore-un-autre-mot')" "303 $U/compte/parametres"
check "session rouverte : la réouverture apparaît dans l'activité du compte" "$(sql "SELECT COUNT(*) > 0 FROM journal j JOIN membre m ON m.id = j.cible_id WHERE m.email = 'bob@example.org' AND j.action LIKE 'Connexion rouverte%'")" "1"
login_rester bob5 bob@example.org 1
ID5=$(sql "SELECT MAX(a.id) FROM connexion_appareil a JOIN membre m ON m.id = a.membre_id WHERE m.email = 'bob@example.org'")
as bob3; post "$U/compte/parametres" "$U/compte/appareils/$ID5/oublier" >/dev/null
as bob5; check "appareil déconnecté ailleurs : sa session ouverte cesse aussitôt" "$(c "$U/compte")" "303 $U/connexion"
ID=$(sql "SELECT a.id FROM connexion_appareil a JOIN membre m ON m.id = a.membre_id WHERE m.email = 'bob@example.org'")
login_link carole carole@example.org; check "carole ne peut pas déconnecter l'appareil de bob" "$(post "$U/compte/parametres" "$U/compte/appareils/$ID/oublier" >/dev/null; nb bob)" "1"
as bob3; check "bob déconnecte son appareil depuis Paramètres" "$(post "$U/compte/parametres" "$U/compte/appareils/$ID/oublier" >/dev/null; nb bob)" "0"
login_rester bob4 bob@example.org 1
check "déconnexion : appareil oublié, cookie effacé" "$(post "$U/compte" "$U/deconnexion" >/dev/null; nb bob) $(appareil)" "0 0"

# Gestion : la session rouverte n'a qu'un facteur, la double authentification est redemandée
as admin2; hpost "$U/connexion" "$U/connexion" -d 'email=admin@example.org&mot_de_passe=motdepasse-tres-long&rester=1' >/dev/null
check "administrateur : appareil retenu (double authentification pour la gestion seulement)" "$(nb admin)" "1"
sans_session
check "session rouverte : son compte, oui" "$(c "$U/compte" | cut -c1-3)" "200"
check "session rouverte : la gestion redemande la double authentification" "$(c "$U/admin/membres")" "303 $U/connexion/double-authentification"
check "session rouverte : un code e-mail seul ne suffit pas pour la gestion" "$(stepup_email >/dev/null; c "$U/admin/membres")" "303 $U/connexion/double-authentification"
c "$U/connexion/double-authentification" >/dev/null; T=$(tok)
c --data-urlencode "_csrf=$T" -d 'mot_de_passe=motdepasse-tres-long' "$U/connexion/double-authentification/mot-de-passe" >/dev/null
check "code e-mail + mot de passe : la gestion s'ouvre" "$(c "$U/admin/membres" | cut -c1-3)" "200"
# Qui a choisi la double authentification à chaque connexion : jamais retenu
sql "UPDATE membre SET mfa_connexion = 1 WHERE email = 'admin@example.org'"
sans_session; check "double authentification à chaque connexion : l'appareil ne rouvre plus rien" "$(c "$U/compte") $(nb admin)" "303 $U/connexion 0"

# Changer de mot de passe coupe aussi les sessions non retenues
login_link carole carole@example.org; login_link carole2 carole@example.org
as carole2; post "$U/compte/parametres" "$U/compte/mot-de-passe" -d 'mot_de_passe=carole-mot-de-passe&confirmation=carole-mot-de-passe' >/dev/null
as carole; check "mot de passe changé ailleurs : l'autre session (non retenue) est coupée" "$(c "$U/compte")" "303 $U/connexion"
as carole2; check "la session qui l'a changé reste ouverte" "$(c "$U/compte" | cut -c1-3)" "200"
# iPhone : lien ouvert dans Safari, code saisi dans l'application (stockage à part) : les deux marchent
as safari; hpost "$U/connexion" "$U/connexion" -d 'email=bob@example.org&rester=appli' >/dev/null
check "page du lien : case cochée d'avance, valeur « application »" "$(c "$U/connexion/lien/x" >/dev/null; grep -c 'name="rester" value="appli" checked' "$B")" "1"
LIEN=$(grep -o 'http://[^ ]*/connexion/lien/[a-f0-9]*' "$MAIL" | tail -1); CODE=$(grep -o 'saisissez ce code sur la page de connexion : [0-9]*' "$MAIL" | tail -1 | grep -o '[0-9]*$')
as safari2; c "$LIEN" >/dev/null; T=$(tok); c --data-urlencode "_csrf=$T" "$LIEN" >/dev/null
check "lien ouvert dans un autre navigateur : connecté" "$(c "$U/compte" | cut -c1-3)" "200"
as safari; check "le code du même e-mail marche encore dans l'application" "$(hpost "$U/connexion/code" "$U/connexion/code" -d "code=$CODE" | cut -c1-3) $(c "$U/compte" | cut -c1-3)" "303 200"
check "application : appareil retenu sous « Application »" "$(appareil) $(sql "SELECT COUNT(*) FROM connexion_appareil WHERE libelle LIKE 'Application%'")" "1 [1-9]"
# Formulaire resté ouvert dans l'application, session PHP expirée entre-temps : il passe quand même
c "$U/compte/parametres" >/dev/null; T=$(tok); sans_session
check "formulaire ouvert, session expirée : accepté (jeton propre à l'appareil)" "$(c --data-urlencode "_csrf=$T" "$U/compte/appareils/oublier-autres" | cut -d' ' -f1)" "303"

# Compte de démonstration (partagé) : jamais retenu
sql "UPDATE membre SET compte_demo = 1 WHERE email = 'carole@example.org'"
login_rester demo carole@example.org 1
check "compte de démonstration : aucun appareil retenu" "$(nb carole) $(appareil)" "0 0"
# Désactivé par l'administrateur : ses appareils sont oubliés (même s'il est réactivé)
login_rester bob6 bob@example.org 1
sql "UPDATE membre SET compte_demo = 0 WHERE email = 'carole@example.org'"
as admin; BOB=$(sql "SELECT id FROM membre WHERE email = 'bob@example.org'")
check "administrateur désactive bob : appareils oubliés" "$(post "$U/admin/membres" "$U/admin/membres/$BOB" -d 'email=bob@example.org&prenom=Bob&nom=' >/dev/null; nb bob)" "0"

# Réglages : durée 0 (case absente), application coupée
sql "REPLACE INTO parametre (cle, valeur) VALUES ('appareil_duree_jours', '0'), ('application_active', '0')"
as visiteur; c "$U/connexion" >/dev/null
check "durée 0 : pas de case « Rester connecté »" "$(grep -c 'name="rester"' "$B")" "0"
check "application coupée : pas de bandeau" "$(as bob3; c "$U/compte" >/dev/null; grep -c 'data-proposer-appli' "$B"; as visiteur)" "0"
check "application coupée : ni manifeste ni lien" "$(c "$U/app/manifest.webmanifest" | cut -c1-3) $(c "$U/" >/dev/null; grep -c 'rel="manifest"' "$B")" "404 0"
check "application coupée : le service installé se retire" "$(c "$U/app/sw.js" | cut -c1-3) $(grep -c 'unregister' "$B")" "200 1"
bilan
