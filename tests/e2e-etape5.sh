#!/bin/bash
# Test de bout en bout de l'étape 5 : clés d'API, API JSON, serveur MCP, droits, révocation, journal « via IA »
# Usage : tests/e2e-etape5.sh <base> <utilisateur> <mot de passe> <dossier de travail>
set -u
# shellcheck disable=SC1091
source "$(dirname "$0")/commun.sh" "$@"
api() { local key=$1; shift; curl -s -o "$B" -w '%{http_code}' -H "Authorization: Bearer $key" "$@"; }
mcp() { curl -s -o "$B" -w '%{http_code}' -H "Authorization: Bearer $1" -H 'Content-Type: application/json' -d "$2" "$U/mcp"; }
newkey() { post "$U/compte/cles" "$U/compte/cles" -d "libelle=$1&portee=$2&duree=30" >/dev/null; c "$U/compte/cles" >/dev/null; grep -o 'bsl_[a-f0-9]\{8\}_[a-f0-9]\{40\}' "$B" | head -1; }

install_admin
post "$U/admin/membres" "$U/admin/membres" -d 'email=bob@example.org&prenom=Bob' >/dev/null
post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=projet&titre=Projet Bob&visibilite=equipe' >/dev/null
post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=comite&titre=Comité secret&visibilite=equipe' >/dev/null
post "$U/espace/projet-bob" "$U/espace/projet-bob/equipe" -d 'email=bob@example.org&niveau=n1' >/dev/null
ADMIN_KEY=$(newkey 'IA admin' ecriture)
check "clé affichée une fois, au bon format" "${ADMIN_KEY:0:4}" "bsl_"
check "la clé n'est plus affichée au rechargement" "$(c "$U/compte/cles" >/dev/null; grep -c 'bsl_[a-f0-9]\{8\}_[a-f0-9]\{40\}' "$B")" "0"
check "seule l'empreinte est en base" "$(sql "SELECT COUNT(*) FROM cle_api WHERE empreinte = SHA2('$ADMIN_KEY', 256)")" "1"

check "API sans clé : 401" "$(curl -s -o /dev/null -w '%{http_code}' "$U/api/moi")" "401"
check "API, clé inventée : 401" "$(api bsl_00000000_0000000000000000000000000000000000000000 "$U/api/moi")" "401"
check "API /moi" "$(api "$ADMIN_KEY" "$U/api/moi")$(grep -o '"email":"[^"]*"' "$B")" '200"email":"admin@example.org"'
check "API : aucun cookie de session" "$(curl -s -D - -o /dev/null -H "Authorization: Bearer $ADMIN_KEY" "$U/api/moi" | grep -ci 'set-cookie')" "0"
check "API : créer une page (texte, sans jeton CSRF)" "$(api "$ADMIN_KEY" -H 'Content-Type: application/json' -d '{"titre":"Rédigée par IA","texte":"Premier paragraphe <script>alert(1)</script>\n\nDeuxième paragraphe","publiee":true}' "$U/api/pages")" "200"
check "page publiée, script neutralisé" "$(c "$U/page/redigee-par-ia" >/dev/null; grep -c 'Deuxième paragraphe' "$B")$(grep -c '<script>alert' "$B")" "10"
check "journal : action marquée via IA" "$(sql "SELECT via FROM journal WHERE action LIKE 'Page créée : Rédigée%'")" "ia"

as bob; login_link bob bob@example.org
BOB_READ=$(newkey 'Lecture' lecture); BOB_WRITE=$(newkey 'Écriture' ecriture)
check "bob : voit son espace" "$(api "$BOB_READ" "$U/api/espaces/projet-bob")" "200"
check "bob : ne voit pas le comité secret" "$(api "$BOB_READ" "$U/api/espaces/comite-secret")" "404"
check "bob : liste sans le comité secret" "$(api "$BOB_READ" "$U/api/espaces"; grep -c 'comite-secret' "$B")" "2000"
check "bob, clé en lecture : écriture refusée" "$(api "$BOB_READ" -H 'Content-Type: application/json' -d '{"meteo":"soleil"}' "$U/api/espaces/projet-bob")" "403"
check "bob, clé en écriture : météo de son espace" "$(api "$BOB_WRITE" -H 'Content-Type: application/json' -d '{"meteo":"soleil"}' "$U/api/espaces/projet-bob")" "200"
check "bob : pas de droit « publications » → page refusée" "$(api "$BOB_WRITE" -H 'Content-Type: application/json' -d '{"titre":"x","texte":"y"}' "$U/api/pages")" "403"
check "bob : liste des membres refusée" "$(api "$BOB_WRITE" "$U/api/membres")" "403"

check "MCP initialize" "$(mcp "$BOB_WRITE" '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"test","version":"1"}}}')$(grep -o '"protocolVersion":"[^"]*"' "$B")" '200"protocolVersion":"2025-06-18"'
check "MCP notification : 202 sans corps" "$(mcp "$BOB_WRITE" '{"jsonrpc":"2.0","method":"notifications/initialized"}')$(wc -c < "$B")" "2020"
# shellcheck disable=SC2016
check "MCP tools/list : 25 outils" "$(mcp "$BOB_WRITE" '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'; php -r 'echo count(json_decode(file_get_contents($argv[1]),true)["result"]["tools"]);' "$B")" "20025"
check "MCP ajouter_date_cle" "$(mcp "$BOB_WRITE" '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"ajouter_date_cle","arguments":{"slug":"projet-bob","date":"2027-03-01","libelle":"Rendu"}}}'; grep -o '"isError":[a-z]*' "$B")" '200"isError":false'
check "MCP modifier_espace : archiver puis désarchiver" "$(mcp "$BOB_WRITE" '{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"modifier_espace","arguments":{"slug":"projet-bob","archive":true}}}' >/dev/null; sql "SELECT archive FROM espace WHERE slug = 'projet-bob'"; mcp "$BOB_WRITE" '{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{"name":"modifier_espace","arguments":{"slug":"projet-bob","archive":false}}}' >/dev/null; sql "SELECT archive FROM espace WHERE slug = 'projet-bob'")" "1
0"
check "MCP : outil refusé → isError" "$(mcp "$BOB_WRITE" '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"lire_espace","arguments":{"slug":"comite-secret"}}}'; grep -o '"isError":[a-z]*' "$B")" '200"isError":true'
check "MCP : méthode inconnue → -32601" "$(mcp "$BOB_WRITE" '{"jsonrpc":"2.0","id":5,"method":"resources/list"}'; grep -o '"code":-32601' "$B")" '200"code":-32601'
check "MCP sans clé : 401" "$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' "$U/mcp")" "401"

KID=$(sql "SELECT id FROM cle_api WHERE libelle = 'Écriture'")
post "$U/compte/cles" "$U/compte/cles/$KID/revoquer" >/dev/null
check "clé révoquée : 401" "$(api "$BOB_WRITE" "$U/api/moi")" "401"
sql "UPDATE cle_api SET expire_le = CURDATE() - INTERVAL 1 DAY WHERE libelle = 'Lecture'"
check "clé expirée : 401" "$(api "$BOB_READ" "$U/api/moi")" "401"
sql "UPDATE membre SET actif = 0 WHERE email = 'bob@example.org'"; sql "UPDATE cle_api SET expire_le = CURDATE() + INTERVAL 5 DAY WHERE libelle = 'Lecture'"
check "membre désactivé : sa clé ne marche plus" "$(api "$BOB_READ" "$U/api/moi")" "401"
as admin
# Clé d'écriture avec une seule preuve : la seconde vérification est demandée, et expliquée
sql "UPDATE membre SET totp_actif = 0 WHERE email = 'admin@example.org'"
c "$U/compte" >/dev/null; T=$(tok); c --data-urlencode "_csrf=$T" "$U/deconnexion" >/dev/null
hpost "$U/connexion" "$U/connexion" -d 'email=admin@example.org&mot_de_passe=motdepasse-tres-long' >/dev/null
check "clé d'écriture, une seule preuve : seconde vérification demandée" "$(post "$U/compte/cles" "$U/compte/cles" -d 'libelle=IA&portee=ecriture&duree=7')" "303 $U/connexion/double-authentification"
check "la page explique pourquoi" "$(c "$U/connexion/double-authentification" | cut -c1-3) $(grep -c 'Vous êtes bien connecté' "$B")" "200 1"
# Session ouverte avant la mise à jour (sans facteurs notés) : la seconde vérification fonctionne quand même
SID=$(awk '$6 == "BOUSSOLE" || $6 == "PHPSESSID" {print $7}' "$J" | tail -1)
sed -i 's/facteurs|a:[0-9]*:{[^}]*}//' "/var/lib/php/sessions/sess_$SID"
check "ancienne session : la seconde vérification s'affiche (pas de renvoi vers le compte)" "$(c "$U/connexion/double-authentification" | cut -c1-3) $(grep -c 'Vous êtes bien connecté' "$B")" "200 1"
stepup_email >/dev/null
check "après le code e-mail : clé d'écriture possible" "$(post "$U/compte/cles" "$U/compte/cles" -d 'libelle=IA2&portee=ecriture&duree=7')" "303 $U/compte/cles"
# Pages par le MCP : menu, accueil, image envoyée en base64
# shellcheck disable=SC2016
IMG=$(php -r '$i=imagecreatetruecolor(40,30); imagefill($i,0,0,imagecolorallocate($i,30,80,120)); ob_start(); imagejpeg($i); echo base64_encode(ob_get_clean());')
R=$(curl -s -H "Authorization: Bearer $ADMIN_KEY" -H 'Content-Type: application/json' -d "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/call\",\"params\":{\"name\":\"ajouter_image\",\"arguments\":{\"base64\":\"$IMG\"}}}" "$U/mcp")
# shellcheck disable=SC2016
CHEMIN=$(echo "$R" | php -r '$r = json_decode(stream_get_contents(STDIN), true); echo json_decode($r["result"]["content"][0]["text"], true)["chemin"] ?? "";')
check "MCP ajouter_image : image ré-encodée dans uploads/" "$([ -f "$ROOT/app/$CHEMIN" ] && echo présente)" "présente"
check "MCP ajouter_image : faux fichier refusé" "$(curl -s -H "Authorization: Bearer $ADMIN_KEY" -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"ajouter_image","arguments":{"base64":"PD9waHAgZWNobyAxOw=="}}}' "$U/mcp" | grep -o '"isError":[a-z]*')" '"isError":true'
curl -s -o /dev/null -H "Authorization: Bearer $ADMIN_KEY" -H 'Content-Type: application/json' -d "{\"jsonrpc\":\"2.0\",\"id\":3,\"method\":\"tools/call\",\"params\":{\"name\":\"creer_page\",\"arguments\":{\"titre\":\"Bienvenue\",\"publiee\":true,\"est_accueil\":true,\"blocs\":{\"blocks\":[{\"type\":\"image\",\"data\":{\"chemin\":\"$CHEMIN\",\"alt\":\"Rade\"}}]}}}}" "$U/mcp"
curl -s -o /dev/null -H "Authorization: Bearer $ADMIN_KEY" -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"creer_page","arguments":{"titre":"Agenda","publiee":true,"dans_menu":true,"ordre":20}}}' "$U/mcp"
check "MCP : page d'accueil et menu réglés" "$(sql "SELECT CONCAT(est_accueil, dans_menu) FROM page WHERE titre = 'Bienvenue'") $(sql "SELECT CONCAT(est_accueil, dans_menu, ' ', ordre) FROM page WHERE titre = 'Agenda'")" "10 01 20"
check "accueil : l'image envoyée par le MCP s'affiche" "$(curl -s "$U/" | grep -c "$CHEMIN")" "1"
IID=$(sql "SELECT id FROM image WHERE chemin = '$CHEMIN'")
check "médiathèque : on voit où l'image sert" "$(as admin; c "$U/admin/images" >/dev/null; grep -c 'Sert dans : page « Bienvenue »' "$B")" "1"
check "médiathèque : image utilisée, suppression refusée" "$(as admin; post "$U/admin/images" "$U/admin/images/$IID/supprimer" >/dev/null; [ -f "$ROOT/app/$CHEMIN" ] && sql "SELECT COUNT(*) FROM image WHERE id = $IID")" "1"
R2=$(curl -s -H "Authorization: Bearer $ADMIN_KEY" -H 'Content-Type: application/json' -d "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/call\",\"params\":{\"name\":\"ajouter_image\",\"arguments\":{\"base64\":\"$IMG\"}}}" "$U/mcp")
# shellcheck disable=SC2016
CHEMIN2=$(echo "$R2" | php -r '$r = json_decode(stream_get_contents(STDIN), true); echo json_decode($r["result"]["content"][0]["text"], true)["chemin"] ?? "";')
IID2=$(sql "SELECT id FROM image WHERE chemin = '$CHEMIN2'")
check "médiathèque : image inutilisée supprimée (base et disque)" "$(as admin; post "$U/admin/images" "$U/admin/images/$IID2/supprimer" >/dev/null; sql "SELECT COUNT(*) FROM image WHERE id = $IID2"; [ -f "$ROOT/app/$CHEMIN2" ] && echo encore)" "0"

# Espaces et événements par le MCP
mcpcall() { curl -s -H "Authorization: Bearer $1" -H 'Content-Type: application/json' -d "{\"jsonrpc\":\"2.0\",\"id\":9,\"method\":\"tools/call\",\"params\":{\"name\":\"$2\",\"arguments\":$3}}" "$U/mcp"; }
check "MCP creer_espace : commission créée" "$(mcpcall "$ADMIN_KEY" creer_espace '{"type":"commission","titre":"Trinôme académique","visibilite":"adherents","meteo":"soleil","description":"Armées, Éducation nationale, association"}' | grep -o '"isError":[a-z]*') $(sql "SELECT CONCAT(type, ' ', visibilite, ' ', meteo) FROM espace WHERE slug = 'trinome-academique'")" '"isError":false commission adherents soleil'
check "MCP creer_espace : sous-espace événement" "$(mcpcall "$ADMIN_KEY" creer_espace '{"type":"evenement","titre":"Concert du trinôme","visibilite":"public","parent":"trinome-academique"}' | grep -o '"isError":[a-z]*') $(sql "SELECT p.slug FROM espace e JOIN espace p ON p.id = e.parent_id WHERE e.slug = 'concert-du-trinome'")" '"isError":false trinome-academique'
check "MCP regler_evenement" "$(mcpcall "$ADMIN_KEY" regler_evenement '{"slug":"concert-du-trinome","debut":"2027-03-12 18:30","lieu":"Palais Neptune, Toulon","mode":"ouvert"}' | grep -o '"isError":[a-z]*') $(sql "SELECT CONCAT(debut, ' ', lieu) FROM evenement e JOIN espace s ON s.id = e.espace_id WHERE s.slug = 'concert-du-trinome'")" '"isError":false 2027-03-12 18:30:00 Palais Neptune, Toulon'
check "MCP regler_evenement : erreur claire" "$(mcpcall "$ADMIN_KEY" regler_evenement '{"slug":"concert-du-trinome","mode":"auto"}' | grep -c 'nombre de places')" "1"
# Événement sur plusieurs jours (exposition) : commencé hier, il reste à venir jusqu'à sa fin
hier=$(date -d yesterday '+%F'); dans3=$(date -d '+3 days' '+%F')
check "MCP regler_evenement : sur plusieurs jours" "$(mcpcall "$ADMIN_KEY" regler_evenement "{\"slug\":\"concert-du-trinome\",\"debut\":\"$hier 09:00\",\"fin\":\"$dans3 18:00\"}" | grep -o '"isError":[a-z]*')" '"isError":false'
check "événement en cours : encore dans les événements à venir" "$(mcpcall "$ADMIN_KEY" lister_evenements '{}' | grep -c 'concert-du-trinome')" "1"
mcpcall "$ADMIN_KEY" creer_page '{"titre":"Rendez-vous","publiee":true,"blocs":{"blocks":[{"type":"boussole","data":{"bloc":"agenda"}}]}}' >/dev/null
check "agenda : l'exposition en cours, du premier au dernier jour" "$(as visiteur; c "$U/page/rendez-vous" >/dev/null; grep -c 'href=\"[^\"]*/espace/concert-du-trinome\"' "$B") $(grep -c "· 9 h – [a-z]* $(date -d '+3 days' '+%-d') " "$B")" "1 1"
# Fichier joint par le MCP (flyer) : droits de contribution, taille, base64
check "MCP ajouter_fichier : flyer joint" "$(mcpcall "$ADMIN_KEY" ajouter_fichier "{\"slug\":\"concert-du-trinome\",\"nom\":\"flyer.pdf\",\"base64\":\"$(printf '%%PDF-1.4 essai' | base64 -w0)\"}" | grep -o '"isError":[a-z]*') $(sql "SELECT CONCAT(f.nom, ' ', f.type_mime, ' ', f.taille) FROM fichier f JOIN espace s ON s.id = f.espace_id WHERE s.slug = 'concert-du-trinome'") $(sql "SELECT COUNT(*) FROM journal j JOIN espace s ON s.id = j.espace_id WHERE s.slug = 'concert-du-trinome' AND j.action LIKE 'Fichier déposé : flyer.pdf'")" '"isError":false flyer.pdf application/pdf 14 1'
check "MCP ajouter_fichier : base64 invalide refusé" "$(mcpcall "$ADMIN_KEY" ajouter_fichier '{"slug":"concert-du-trinome","nom":"x.pdf","base64":"@@@"}' | grep -c 'mal encodé')" "1"
mcpcall "$ADMIN_KEY" regler_evenement '{"slug":"concert-du-trinome","debut":"2027-03-12 18:30","fin":""}' >/dev/null
sql "UPDATE membre SET actif = 1 WHERE email = 'bob@example.org'"
check "MCP ajouter_fichier : clé en lecture refusée" "$(mcpcall "$BOB_READ" ajouter_fichier '{"slug":"projet-bob","nom":"x.txt","base64":"eA=="}' | grep -o '"isError":[a-z]*')" '"isError":true'
BOB2="bsl_0000aaaa_$(printf 'b%.0s' {1..40})"
sql "INSERT INTO cle_api (membre_id, libelle, prefixe, empreinte, portee, expire_le) SELECT id, 'Bob 2', '0000aaaa', SHA2('$BOB2', 256), 'ecriture', NULL FROM membre WHERE email = 'bob@example.org'"
check "MCP ajouter_fichier : espace d'un autre refusé" "$(mcpcall "$BOB2" ajouter_fichier '{"slug":"comite-secret","nom":"x.txt","base64":"eA=="}' | grep -o '"isError":[a-z]*') $(sql "SELECT COUNT(*) FROM fichier WHERE nom = 'x.txt'")" '"isError":true 0'
check "MCP ajouter_equipe" "$(mcpcall "$ADMIN_KEY" ajouter_equipe '{"slug":"trinome-academique","email":"bob@example.org","niveau":"n1","libelle":"Responsable"}' | grep -o '"isError":[a-z]*')" '"isError":false'
check "MCP creer_espace : un simple membre ne crée pas d'espace au premier niveau" "$(mcpcall "$BOB2" creer_espace '{"type":"projet","titre":"X"}' | grep -o '"isError":[a-z]*')" '"isError":true'

# Clé « administration » : réglages du site et membres, dans la limite des droits
post "$U/compte/cles" "$U/compte/cles" -d 'libelle=Admin&portee=admin&duree=7' >/dev/null; c "$U/compte/cles" >/dev/null
ADM=$(grep -o 'bsl_[a-f0-9]\{8\}_[a-f0-9]\{40\}' "$B" | head -1)
check "clé administration créée" "$(sql "SELECT portee FROM cle_api WHERE libelle = 'Admin'")" "admin"
check "regler_site refusé avec une clé d'écriture" "$(mcpcall "$ADMIN_KEY" regler_site '{"slogan":"x"}' | grep -c 'administration')" "1"
check "regler_site : accroche et lien d'adhésion" "$(mcpcall "$ADM" regler_site '{"slogan":"Esprit de défense","lien_adhesion":"https://www.helloasso.com/x"}' | grep -o '"isError":[a-z]*') $(sql "SELECT valeur FROM parametre WHERE cle = 'slogan'")" '"isError":false Esprit de défense'
check "regler_site : lien non https refusé" "$(mcpcall "$ADM" regler_site '{"lien_adhesion":"javascript:alert(1)"}' | grep -o '"isError":[a-z]*')" '"isError":true'
check "MCP regler_site : logo (image envoyée)" "$(curl -s -H "Authorization: Bearer $ADM" -H 'Content-Type: application/json' -d "{\"jsonrpc\":\"2.0\",\"id\":7,\"method\":\"tools/call\",\"params\":{\"name\":\"regler_site\",\"arguments\":{\"logo\":\"$CHEMIN\"}}}" "$U/mcp" | grep -o '"isError":[a-z]*') $(sql "SELECT valeur FROM parametre WHERE cle = 'logo'")" "\"isError\":false $CHEMIN"
check "MCP regler_site : logo hors uploads refusé" "$(curl -s -H "Authorization: Bearer $ADM" -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","id":8,"method":"tools/call","params":{"name":"regler_site","arguments":{"logo":"https://exemple.org/x.png"}}}' "$U/mcp" | grep -o '"isError":[a-z]*')" '"isError":true'
check "regler_site : mode construction activé, code fourni" "$(mcpcall "$ADM" regler_site '{"site_en_construction":true}' | grep -c 'code_acces[^,]*[0-9]\{6\}') $(sql "SELECT valeur FROM parametre WHERE cle = 'construction_active'")" "1 1"
mcpcall "$ADM" regler_site '{"site_en_construction":false}' >/dev/null
check "inviter_membres : jamais connectés invités, e-mail avec la connexion préremplie" "$(mcpcall "$ADM" inviter_membres '{"email":"bob@example.org"}' | grep -o 'invitations[^0-9]*[0-9]*' | grep -o '[0-9]*$') $(grep -c 'connexion?email=bob%40example.org' "$MAIL")" "1 1"
check "ajouter_membre" "$(mcpcall "$ADM" ajouter_membre '{"email":"carla@example.org","prenom":"Carla"}' | grep -o '"isError":[a-z]*') $(sql "SELECT COUNT(*) FROM membre WHERE email = 'carla@example.org'")" '"isError":false 1'
check "modifier_membre : compte de démonstration marqué" "$(mcpcall "$ADM" modifier_membre '{"email":"carla@example.org","compte_demo":true}' | grep -o '"isError":[a-z]*') $(sql "SELECT compte_demo FROM membre WHERE email = 'carla@example.org'")" '"isError":false 1'
check "modifier_membre : aucun champ de mot de passe accepté" "$(mcpcall "$ADM" modifier_membre '{"email":"carla@example.org","mot_de_passe":"pirate-pirate-123"}' >/dev/null; sql "SELECT COALESCE(mot_de_passe, 'aucun') FROM membre WHERE email = 'carla@example.org'")" "aucun"
check "effacer_membre : refusé sans confirmation exacte" "$(mcpcall "$ADM" effacer_membre '{"email":"carla@example.org","confirmation":"autre@example.org"}' | grep -o '"isError":[a-z]*') $(sql "SELECT COUNT(*) FROM membre WHERE email = 'carla@example.org'")" '"isError":true 1'
check "effacer_membre : effacé avec confirmation" "$(mcpcall "$ADM" effacer_membre '{"email":"carla@example.org","confirmation":"carla@example.org"}' | grep -o '"isError":[a-z]*') $(sql "SELECT COUNT(*) FROM membre WHERE email = 'carla@example.org'")" '"isError":false 0'
sql "INSERT INTO cle_api (membre_id, libelle, prefixe, empreinte, portee, expire_le) SELECT id, 'Bob admin', '0000bbbb', SHA2('bsl_0000bbbb_$(printf 'c%.0s' {1..40})', 256), 'admin', NULL FROM membre WHERE email = 'bob@example.org'"
check "clé administration d'un non-administrateur : refusée" "$(mcpcall "bsl_0000bbbb_$(printf 'c%.0s' {1..40})" regler_site '{"slogan":"pirate"}' | grep -o '"isError":[a-z]*') $(sql "SELECT valeur FROM parametre WHERE cle = 'slogan'")" '"isError":true Esprit de défense'

# Clé sans limite de durée et configuration MCP prête à copier
post "$U/compte/cles" "$U/compte/cles" -d 'libelle=Sans limite&portee=lecture&duree=0' >/dev/null; c "$U/compte/cles" >/dev/null
SL=$(grep -o 'bsl_[a-f0-9]\{8\}_[a-f0-9]\{40\}' "$B" | head -1)
check "configuration MCP : adresse et clé dans le JSON" "$(tr -d '\n' < "$B" | grep -o '<pre class="config"[^<]*' | grep -c "/mcp.*Bearer $SL")" "1"
check "clé sans limite : pas de date d'expiration, et elle fonctionne" "$(sql "SELECT expire_le IS NULL FROM cle_api WHERE libelle = 'Sans limite'") $(api "$SL" "$U/api/moi")" "1 200"
check "liste : « sans limite de durée »" "$(c "$U/compte/cles" >/dev/null; grep -c 'sans limite de durée' "$B")" "1"
check "admin : voit toutes les clés" "$(c "$U/admin/cles" >/dev/null; grep -c 'Bob' "$B")" "[1-9]*"
# Référencement (site ouvert) : robots, plan du site, description, aperçu, données structurées
mcpcall "$ADM" creer_page '{"titre":"Note interne","texte":"Réservé","publiee":true,"reservee_membres":true}' >/dev/null
mcpcall "$ADM" modifier_page "{\"id\":$(sql "SELECT id FROM page WHERE titre = 'Agenda'"),\"description\":\"Les rendez-vous de l'association <b>2027</b>\"}" >/dev/null
check "robots.txt : interne exclu, plan du site annoncé" "$(c "$U/robots.txt" >/dev/null; grep -c '^Disallow: /admin$\|^Disallow: /compte$\|^Sitemap: .*/sitemap.xml$' "$B")" "3"
check "sitemap : pages publiques, rien de réservé" "$(c "$U/sitemap.xml" >/dev/null; grep -c '/page/agenda<' "$B") $(grep -c 'note-interne' "$B")" "1 0"
check "page : description choisie (texte seul), canonique, aperçu" "$(c "$U/page/agenda" >/dev/null; grep -c '<meta name=\"description\" content=\"Les rendez-vous de l&#039;association 2027\">' "$B") $(grep -c 'rel=\"canonical\" href=\"[^\"]*/page/agenda\"' "$B") $(grep -c 'property=\"og:title\"' "$B")" "1 1 1"
check "accueil : association en données structurées, image de l'aperçu" "$(c "$U/" >/dev/null; grep -c '\"@type\":\"NGO\"' "$B") $(grep -c 'property=\"og:image\"' "$B")" "1 1"
check "page réservée et pages internes : noindex" "$(as admin; c "$U/page/note-interne" >/dev/null; grep -c 'noindex' "$B") $(c "$U/compte" >/dev/null; grep -c 'noindex' "$B")" "1 1"
# Formulaire de contact : message gardé dans le site, transmis par e-mail avec « répondre à » l'expéditeur
mcpcall "$ADM" creer_page '{"titre":"Nous écrire","publiee":true,"blocs":{"blocks":[{"type":"boussole","data":{"bloc":"contact"}}]}}' >/dev/null
as visiteur
check "contact : formulaire affiché, aucune adresse en clair" "$(c "$U/page/nous-ecrire" >/dev/null; grep -c 'action=\"[^\"]*/contact\"' "$B") $(grep -c 'mailto:' "$B")" "1 0"
check "contact : message envoyé, retour à la page" "$(hpost "$U/page/nous-ecrire" "$U/contact" -d 'retour=/page/nous-ecrire&nom=Jean Dupont&email=jean@example.org&objet=Conférence&message=Bonjour, une question.')" "303 $U/page/nous-ecrire"
check "contact : message gardé, transmis par e-mail avec répondre-à" "$(sql "SELECT CONCAT(nom, ' ', transmis) FROM message_contact WHERE email = 'jean@example.org'") $(grep -c 'De : Jean Dupont <jean@example.org>' "$MAIL")" "Jean Dupont 1 1"
check "contact : sans le défi anti-robot, refusé" "$(post "$U/page/nous-ecrire" "$U/contact" -d 'retour=/&nom=Robot&email=r@example.org&message=spam' | cut -c1-3) $(sql "SELECT COUNT(*) FROM message_contact WHERE email = 'r@example.org'")" "400 0"
check "contact : e-mail invalide refusé" "$(hpost "$U/page/nous-ecrire" "$U/contact" -d 'retour=/page/nous-ecrire&nom=X&email=pas-une-adresse&message=x' >/dev/null; sql "SELECT COUNT(*) FROM message_contact WHERE nom = 'X'")" "0"
as admin
check "admin : messages reçus lisibles, puis marqués lus" "$(c "$U/admin/messages" >/dev/null; grep -c 'Bonjour, une question.' "$B") $(sql "SELECT lu FROM message_contact WHERE email = 'jean@example.org'")" "1 1"
MID=$(sql "SELECT id FROM message_contact WHERE email = 'jean@example.org'")
check "admin : suppression d'un message" "$(post "$U/admin/messages" "$U/admin/messages/$MID/supprimer" >/dev/null; sql "SELECT COUNT(*) FROM message_contact")" "0"
check "contact : messages plus vieux que la durée réglée effacés par les tâches" "$(sql "INSERT INTO message_contact (nom, email, objet, message, recu_le) VALUES ('Ancien', 'a@example.org', 'o', 'm', NOW() - INTERVAL 13 MONTH), ('Récent', 'b@example.org', 'o', 'm', NOW() - INTERVAL 2 MONTH)"; php "$ROOT/app/bin/cli.php" cron >/dev/null; sql "SELECT GROUP_CONCAT(nom) FROM message_contact")" "Récent"
as visiteur
check "contact : messages réservés à l'administrateur" "$(c "$U/admin/messages" | cut -c1-3)" "303"
bilan
