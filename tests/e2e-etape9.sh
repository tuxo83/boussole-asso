#!/bin/bash
# Test de bout en bout de l'étape 9 : connexion HelloAsso (faux serveur HelloAsso local)
# Usage : tests/e2e-etape9.sh <base> <utilisateur> <mot de passe> <dossier de travail>
set -u
# shellcheck disable=SC1091
source "$(dirname "$0")/commun.sh" "$@"
sed -i "s#'mail_fichier'#'helloasso_api' => 'http://127.0.0.1:8096', 'mail_fichier'#" "$ROOT/app/config/config.php"
HA="$W/helloasso"; rm -rf "$HA"; mkdir -p "$HA"
HELLOASSO_SIMULE="$HA" php -S 127.0.0.1:8096 "$ROOT/tests/helloasso-simule.php" > "$W/helloasso.log" 2>&1 &
HAPID=$!; trap 'kill $SERVER $HAPID; rm -f "$ROOT/app/config/config.php"' EXIT; sleep 1
id() { sql "SELECT id FROM membre WHERE email = '$1@example.org'"; }
appels() { { tr -d '\n' < "$HA/appels-$1.txt"; } 2>/dev/null | wc -c; }
P=$(date +%Y)

install_admin
sql "REPLACE INTO parametre (cle, valeur) VALUES ('cotisation_relance_jours', '0')"
for p in alice bob carl; do post "$U/admin/membres" "$U/admin/membres" -d "email=$p@example.org&prenom=${p^}" >/dev/null; done
check "sans réglage : rien n'est appelé" "$(php "$ROOT/app/bin/cli.php" cron | grep HelloAsso) $(appels jeton)" "cron : 0 mise(s) à jour HelloAsso 0"

cat > "$HA/paiements-Membership-adhesion-$P.json" <<JSON
[{"date":"$P-02-03T10:12:00+01:00","state":"Authorized","payer":{"email":"Alice@Example.org","firstName":"Alice"},"amount":3000},
 {"date":"$P-02-04T10:12:00+01:00","state":"Refused","payer":{"email":"bob@example.org"}},
 {"date":"$P-02-05T10:12:00+01:00","state":"Authorized","payer":{"email":"inconnu@example.org"}}]
JSON
post "$U/cotisations" "$U/cotisations/helloasso" -d "helloasso_organisation=https://www.helloasso.com/associations/les-amis-du-phare&helloasso_adhesion=https://www.helloasso.com/associations/les-amis-du-phare/adhesions/adhesion-$P&helloasso_client_id=id-test&helloasso_secret=secret-test&helloasso_intervalle=10" >/dev/null
check "adresses HelloAsso collées : noms extraits" "$(sql "SELECT GROUP_CONCAT(valeur ORDER BY cle) FROM parametre WHERE cle IN ('helloasso_organisation', 'helloasso_adhesion')")" "adhesion-$P,les-amis-du-phare"
check "secret chiffré en base" "$(sql "SELECT COUNT(*) FROM parametre WHERE cle = 'helloasso_secret' AND valeur LIKE '%secret-test%'")" "0"
post "$U/cotisations" "$U/cotisations/helloasso/synchroniser" >/dev/null
check "paiement autorisé : alice payée par HelloAsso, avec sa date" "$(sql "SELECT CONCAT(moyen, ' ', paye_le) FROM cotisation WHERE membre_id = $(id alice)")" "helloasso $P-02-03"
check "paiement refusé : bob pas à jour" "$(sql "SELECT COUNT(*) FROM cotisation WHERE membre_id = $(id bob)")" "0"
check "payeur inconnu : aucun compte créé" "$(sql "SELECT COUNT(*) FROM membre WHERE email = 'inconnu@example.org'")" "0"
check "aucun montant gardé" "$(sql "SELECT COUNT(*) FROM parametre WHERE valeur LIKE '%3000%'")" "0"
check "cron juste après : rien (intervalle de 10 minutes)" "$(php "$ROOT/app/bin/cli.php" cron | grep HelloAsso) $(appels paiements)" "cron : 0 mise(s) à jour HelloAsso 1"
post "$U/cotisations" "$U/cotisations/helloasso/synchroniser" >/dev/null
check "jeton réutilisé : une seule connexion pour deux lectures" "$(appels jeton) $(appels paiements)" "1 2"
check "état affiché : active, dernière lecture" "$(c "$U/cotisations" >/dev/null; grep -c 'badge-ok">Active' "$B")" "1"

# Notification HelloAsso : déclenche une lecture, son contenu n'est pas cru
CLE=$(sql "SELECT valeur FROM parametre WHERE cle = 'helloasso_notification_cle'")
check "notification, mauvaise clé : ignorée" "$(curl -s -o /dev/null -w '%{http_code}' -d '{"data":{"payer":{"email":"bob@example.org"}}}' "$U/helloasso/notification/mauvaise") $(appels paiements)" "200 2"
check "notification falsifiée (bonne clé, faux contenu) : bob toujours pas à jour" "$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' -d '{"eventType":"Payment","data":{"state":"Authorized","payer":{"email":"bob@example.org"}}}' "$U/helloasso/notification/$CLE") $(sql "SELECT COUNT(*) FROM cotisation WHERE membre_id = $(id bob)") $(appels paiements)" "200 0 3"
sed -i "s/^ {\"date\":\"$P-02-04T10:12:00+01:00\",\"state\":\"Refused\"/ {\"date\":\"$P-02-04T10:12:00+01:00\",\"state\":\"Authorized\"/" "$HA/paiements-Membership-adhesion-$P.json"
curl -s -o /dev/null -d '{}' "$U/helloasso/notification/$CLE"
check "vrai paiement + notification : bob à jour aussitôt" "$(sql "SELECT moyen FROM cotisation WHERE membre_id = $(id bob)")" "helloasso"

# Billetterie d'un événement
post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=evenement&titre=Dîner du phare&visibilite=adherents' >/dev/null
post "$U/espace/diner-du-phare" "$U/espace/diner-du-phare/evenement" -d "mode=ouvert&debut=$(date -d '+20 days' +%Y-%m-%d)T19:30&helloasso_formulaire=https://www.helloasso.com/associations/les-amis-du-phare/evenements/diner-$P" >/dev/null
check "billetterie enregistrée" "$(sql "SELECT helloasso_formulaire FROM evenement")" "diner-$P"
as carl; login_link carl carl@example.org; post "$U/espace/diner-du-phare" "$U/espace/diner-du-phare/inscription" -d 'action=voeu' >/dev/null
echo "[{\"date\":\"$(date +%Y-%m-%d)T09:00:00+01:00\",\"state\":\"Authorized\",\"payer\":{\"email\":\"carl@example.org\"}}]" > "$HA/paiements-Event-diner-$P.json"
as admin; post "$U/cotisations" "$U/cotisations/helloasso/synchroniser" >/dev/null
check "billet payé : carl marqué payé" "$(sql "SELECT paye_le FROM inscription WHERE membre_id = $(id carl)")" "$(date +%Y-%m-%d)"
check "l'organisateur voit « payé »" "$(c "$U/espace/diner-du-phare" >/dev/null; grep -c 'payé le' "$B")" "1"

# Mauvais secret : message clair, pas de plantage
post "$U/cotisations" "$U/cotisations/helloasso" -d "helloasso_organisation=les-amis-du-phare&helloasso_adhesion=adhesion-$P&helloasso_client_id=id-test&helloasso_secret=faux&helloasso_intervalle=10" >/dev/null
post "$U/cotisations" "$U/cotisations/helloasso/synchroniser" >/dev/null
check "mauvais secret : message clair" "$(c "$U/cotisations" >/dev/null; grep -c 'identifiant ou secret à vérifier' "$B")" "[1-9]*"
post "$U/cotisations" "$U/cotisations/helloasso" -d "helloasso_organisation=les-amis-du-phare&helloasso_adhesion=adhesion-$P&helloasso_client_id=id-test&desactiver=1&helloasso_intervalle=10" >/dev/null
check "désactivation : secret effacé" "$(sql "SELECT valeur FROM parametre WHERE cle = 'helloasso_secret'")" ""
bilan
