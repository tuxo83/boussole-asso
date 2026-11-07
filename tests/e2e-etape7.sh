#!/bin/bash
# Test de bout en bout de l'étape 7 : cotisations (cocher, import HelloAsso, relances, export, période, effacement, MCP)
# Usage : tests/e2e-etape7.sh <base> <utilisateur> <mot de passe> <dossier de travail>
set -u
# shellcheck disable=SC1091
source "$(dirname "$0")/commun.sh" "$@"
id() { sql "SELECT id FROM membre WHERE email = '$1@example.org'"; }
P=$(date +%Y); AUJ=$(date +%Y-%m-%d)

install_admin
for p in alice bruno chloe dave eve frank; do post "$U/admin/membres" "$U/admin/membres" -d "email=$p@example.org&prenom=${p^}" >/dev/null; done
sql "UPDATE membre SET nom = '=HYPERLINK(\"x\")' WHERE email = 'chloe@example.org'"

check "liste : 7 membres actifs, aucun à jour" "$(c "$U/cotisations" >/dev/null; grep -o '<strong>0</strong> à jour sur 7' "$B")" "<strong>0</strong> à jour sur 7"
post "$U/cotisations" "$U/cotisations/marquer" -d "periode=$P&membre_id=$(id alice)&moyen=cheque&paye_le=$AUJ" >/dev/null
check "chèque coché : payé, moyen, qui l'a noté" "$(sql "SELECT CONCAT(moyen, ' ', paye_le, ' ', saisi_par) FROM cotisation WHERE membre_id = $(id alice)")" "cheque $AUJ $(sql "SELECT id FROM membre WHERE email = 'admin@example.org'")"
check "la liste affiche « noté par »" "$(c "$U/cotisations" >/dev/null; grep -c 'noté par' "$B")" "1"
post "$U/cotisations" "$U/cotisations/marquer" -d "periode=$P&membre_id=$(id bruno)&moyen=cheque&paye_le=$(date -d '+3 days' +%Y-%m-%d)" >/dev/null
check "date future refusée" "$(sql "SELECT COUNT(*) FROM cotisation WHERE membre_id = $(id bruno)")" "0"
check "aucun montant ni donnée bancaire en base" "$(sql "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cotisation' AND column_name REGEXP 'montant|iban|banque|numero'")" "0"

# Relance automatique (cron) : une seule fois par période
check "cron : relance des 6 retardataires" "$(php "$ROOT/app/bin/cli.php" cron | grep cotisation)" "cron : 6 relance(s) de cotisation"
check "cron relancé : personne n'est relancé deux fois" "$(php "$ROOT/app/bin/cli.php" cron | grep cotisation)" "cron : 0 relance(s) de cotisation"
check "alice (à jour) n'a pas reçu de relance" "$(grep -B1 'Subject: Cotisation' "$MAIL" | grep -c 'To: alice@')" "0"
post "$U/cotisations" "$U/cotisations/relancer" -d "periode=$P" >/dev/null
check "relance manuelle : 6 e-mails de plus" "$(grep -c 'Subject: Cotisation' "$MAIL")" "12"

# Import HelloAsso : seuls l'e-mail et la date comptent
printf '\xef\xbb\xbfR\xc3\xa9f\xc3\xa9rence commande;Date;Montant;Email payeur;Nom\n1;05/10/%s 14:32;30,00;bruno@example.org;Bruno\n2;06/10/%s 09:00;30,00;inconnu@example.org;X\n3;07/10/%s 10:00;30,00;alice@example.org;Alice\n' "$P" "$P" "$P" > "$W/helloasso.csv"
c "$U/cotisations" >/dev/null; T=$(tok)
c -F "_csrf=$T" -F "periode=$P" -F "fichier=@$W/helloasso.csv;type=text/csv" "$U/cotisations/import" >/dev/null
check "import : bruno payé par HelloAsso le 05/10" "$(sql "SELECT CONCAT(moyen, ' ', paye_le) FROM cotisation WHERE membre_id = $(id bruno)")" "helloasso $P-10-05"
check "import : alice déjà à jour, chèque conservé" "$(sql "SELECT moyen FROM cotisation WHERE membre_id = $(id alice)")" "cheque"
check "import : bilan et adresse inconnue signalée" "$(c "$U/cotisations" >/dev/null; grep -o '1 cotisation(s) enregistrée(s), 1 déjà à jour' "$B") $(grep -c 'inconnu@example.org' "$B")" "1 cotisation(s) enregistrée(s), 1 déjà à jour 1"
check "import : aucun compte créé" "$(sql "SELECT COUNT(*) FROM membre WHERE email = 'inconnu@example.org'")" "0"

post "$U/cotisations" "$U/cotisations/marquer" -d "periode=$P&membre_id=$(id chloe)&moyen=especes&paye_le=$AUJ" >/dev/null
c "$U/cotisations/export?periode=$P" >/dev/null
check "export CSV : 3 lignes + en-tête" "$(grep -c . "$B")" "4"
check "export CSV : formule neutralisée" "$(grep -c "'=HYPERLINK" "$B") $(grep -c '^"\?=' "$B")" "1 0"
post "$U/cotisations" "$U/cotisations/annuler" -d "periode=$P&membre_id=$(id chloe)" >/dev/null
check "annulation d'une erreur de saisie" "$(sql "SELECT COUNT(*) FROM cotisation WHERE membre_id = $(id chloe)")" "0"

as alice; login_link alice alice@example.org
check "alice : « À jour » sur son compte" "$(c "$U/compte" >/dev/null; grep -c 'badge-ok">À jour' "$B")" "1"
check "alice : pas d'accès à la gestion des cotisations" "$(c "$U/cotisations")" "403 "
as bruno; login_link bruno bruno@example.org
sql "INSERT INTO cotisation (membre_id, periode, paye_le, moyen) VALUES ($(id bruno), $((P-1)), '$((P-1))-03-01', 'cheque'), ($(id bruno), $((P-2)), '$((P-2))-03-01', 'cheque')"
check "bruno : fidèle depuis deux ans" "$(c "$U/compte" >/dev/null; grep -o "fidèle depuis $((P-2))" "$B")" "fidèle depuis $((P-2))"

# Période qui commence en septembre
as admin; post "$U/cotisations" "$U/cotisations/reglages" -d 'cotisation_mois_debut=9&cotisation_relance_jours=30&cotisation_conservation_ans=2' >/dev/null
EXP=$([ "$(date +%-m)" -ge 9 ] && echo "$P-$((P+1))" || echo "$((P-1))-$P")
check "période sur deux années (septembre)" "$(c "$U/cotisations" >/dev/null; grep -o "<h1>Cotisations [0-9-]*" "$B")" "<h1>Cotisations $EXP"
post "$U/cotisations" "$U/cotisations/reglages" -d 'cotisation_mois_debut=1&cotisation_relance_jours=30&cotisation_conservation_ans=2' >/dev/null
check "réglage invalide refusé" "$(post "$U/cotisations" "$U/cotisations/reglages" -d 'cotisation_mois_debut=13&cotisation_relance_jours=30&cotisation_conservation_ans=2' >/dev/null; sql "SELECT valeur FROM parametre WHERE cle = 'cotisation_mois_debut'")" "1"

# Effacement : dave (dernière cotisation il y a 4 ans), eve (jamais cotisé, compte de 3 ans) ; frank (il y a 2 ans) reste
post "$U/cotisations" "$U/cotisations/marquer" -d "periode=$((P-4))&membre_id=$(id dave)&moyen=cheque&paye_le=$((P-4))-02-01" >/dev/null
sql "INSERT INTO cotisation (membre_id, periode, paye_le, moyen) VALUES ($(id frank), $((P-2)), '$((P-2))-02-01', 'cheque')"
sql "UPDATE membre SET cree_le = NOW() - INTERVAL 3 YEAR WHERE email IN ('eve@example.org', 'dave@example.org', 'frank@example.org')"
check "tâche d'effacement : 2 personnes, météo affichée" "$(c "$U/cotisations" >/dev/null; grep -c 'name="membres\[\]"' "$B") $(grep -c 'meteo-eclaircie' "$B")" "2 [1-9]*"
check "le compte indique la tâche au responsable" "$(c "$U/compte" >/dev/null; grep -c 'donnée(s) à effacer' "$B")" "1"
c "$U/cotisations/export-effacement" >/dev/null
check "export avant effacement : dave et eve" "$(grep -c 'dave@example.org\|eve@example.org' "$B")" "2"
post "$U/cotisations" "$U/cotisations/effacer" -d "membres[]=$(id dave)&membres[]=$(id eve)&membres[]=$(id alice)" >/dev/null
check "dave et eve effacés, alice (non éligible) intacte" "$(sql "SELECT GROUP_CONCAT(prenom ORDER BY prenom) FROM membre WHERE email IN ('dave@example.org', 'eve@example.org', 'alice@example.org')")" "Alice"
check "plus aucune trace de dave dans le journal" "$(sql "SELECT COUNT(*) FROM journal WHERE action LIKE '%Dave%' OR action LIKE '%dave@%'")" "0"
check "ses cotisations ont disparu avec lui" "$(sql "SELECT COUNT(*) FROM cotisation WHERE periode = $((P-4))")" "0"
check "plus rien à effacer : soleil" "$(c "$U/cotisations" >/dev/null; grep -c 'meteo-soleil' "$B")" "[1-9]*"

# MCP
post "$U/compte/cles" "$U/compte/cles" -d 'libelle=IA&portee=ecriture&duree=7' >/dev/null; c "$U/compte/cles" >/dev/null; KEY=$(grep -o 'bsl_[a-f0-9]\{8\}_[a-f0-9]\{40\}' "$B" | head -1)
check "MCP : marquer_cotisation" "$(curl -s -H "Authorization: Bearer $KEY" -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"marquer_cotisation","arguments":{"email":"chloe@example.org","moyen":"virement"}}}' "$U/mcp" | grep -o '"isError":[a-z]*') $(sql "SELECT moyen FROM cotisation WHERE membre_id = $(id chloe)")" '"isError":false virement'
check "journal : saisie marquée via IA" "$(sql "SELECT via FROM journal WHERE action LIKE '%(Virement)%'")" "ia"
check "MCP : ma_cotisation" "$(curl -s -H "Authorization: Bearer $KEY" -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"ma_cotisation","arguments":{}}}' "$U/mcp" | grep -o 'a_jour[^,]*' | head -1)" 'a_jour\\": false'
bilan
