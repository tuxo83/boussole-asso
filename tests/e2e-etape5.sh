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
check "MCP tools/list : 12 outils" "$(mcp "$BOB_WRITE" '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'; php -r 'echo count(json_decode(file_get_contents($argv[1]),true)["result"]["tools"]);' "$B")" "20012"
check "MCP ajouter_date_cle" "$(mcp "$BOB_WRITE" '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"ajouter_date_cle","arguments":{"slug":"projet-bob","date":"2027-03-01","libelle":"Rendu"}}}'; grep -o '"isError":[a-z]*' "$B")" '200"isError":false'
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
check "admin : voit toutes les clés" "$(c "$U/admin/cles" >/dev/null; grep -c 'Bob' "$B")" "[1-9]*"
bilan
