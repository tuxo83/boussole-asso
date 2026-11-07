#!/bin/bash
# Test de bout en bout : événement payant ou à confirmer en phases (vœux, paiement, dernières places)
# Usage : tests/e2e-etape12.sh <base> <utilisateur> <mot de passe> <dossier de travail>
set -u
# shellcheck disable=SC1091
source "$(dirname "$0")/commun.sh" "$@"
id() { sql "SELECT id FROM membre WHERE email = '$1@example.org'"; }
nom() { sql "SELECT SUBSTRING_INDEX(email, '@', 1) FROM membre WHERE id = $1"; }
EV() { sql "SELECT id FROM espace WHERE slug = '$1'"; }
statut() { sql "SELECT statut FROM inscription WHERE evenement_id = $(EV "$1") AND membre_id = $(id "$2")"; }
qui() { sql "SELECT GROUP_CONCAT(SUBSTRING_INDEX(m.email, '@', 1) ORDER BY m.email) FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = $(EV "$1") AND i.statut = '$2'"; }
agir() { as "$1"; post "$U/espace/$2" "$U/espace/$2/inscription" -d "action=$3" >/dev/null; }
cron() { php "$ROOT/app/bin/cli.php" cron > /dev/null; }
phase() { tr -d '\n' < "$B" | grep -o 'aria-current="step">[[:space:]]*<strong>[^<]*' | sed 's/.*<strong>//'; }
J() { date -d "+$1 days" +%Y-%m-%dT%H:%M; }

install_admin
sql "REPLACE INTO parametre (cle, valeur) VALUES ('cotisation_relance_jours', '0'), ('rappel_participants', ''), ('rappel_jalons', ''), ('rappel_jalons_evenements', '')"
for p in alice bruno chloe david emma farid; do post "$U/admin/membres" "$U/admin/membres" -d "email=$p@example.org&prenom=${p^}" >/dev/null; done
for p in alice bruno chloe david emma farid; do login_link "$p" "$p@example.org"; done

# Événement payant : 2 places, 3 jours pour payer, dernières places 2 jours avant
as admin; post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=evenement&titre=Croisière&visibilite=adherents' >/dev/null
post "$U/espace/croisiere" "$U/espace/croisiere/evenement" -d "mode=auto&places=2&debut=$(J 10)&cloture=$(J 1)&reglement=paiement&lien_paiement=https://www.helloasso.com/associations/les-amis/evenements/croisiere-2026&delai_jours=3&dernieres_jours=2" >/dev/null
check "réglages : paiement, lien, billetterie lue dans le lien" "$(sql "SELECT CONCAT(reglement, ' ', delai_jours, ' ', dernieres_jours, ' ', helloasso_formulaire) FROM evenement WHERE espace_id = $(EV croisiere)")" "paiement 3 2 croisiere-2026"
for p in alice bruno chloe david; do agir "$p" croisiere voeu; done
as alice; c "$U/espace/croisiere" >/dev/null
check "frise : phase « Vœux » en cours, avec le temps restant" "$(phase) $(grep -c 'encore [0-9]* heures' "$B")" "Vœux [1-9]*"
check "frise : phases nommées, sans numéro" "$(tr -d '\n' < "$B" | grep -o '<ol class="frise">.*</ol>' | grep -o '<strong>[^<]*</strong>' | tr '\n' ' ')" "<strong>Vœux</strong> <strong>Paiement</strong> <strong>Dernières places</strong> <strong>Événement</strong> "
as admin; post "$U/espace/croisiere" "$U/espace/croisiere/cloturer" >/dev/null
R1=$(qui croisiere retenu)
check "attribution : 2 places réservées, 2 en attente" "$(sql "SELECT COUNT(*) FROM inscription WHERE evenement_id = $(EV croisiere) AND statut = 'retenu' AND echeance BETWEEN NOW() + INTERVAL 71 HOUR AND NOW() + INTERVAL 73 HOUR") $(sql "SELECT COUNT(*) FROM inscription WHERE evenement_id = $(EV croisiere) AND statut = 'attente'")" "2 2"
check "e-mail : place réservée, lien et date limite" "$(grep -c 'Subject: Place réservée : Croisière' "$MAIL") $(grep -c 'payez avant le .*helloasso.com/associations/les-amis/evenements/croisiere-2026' "$MAIL")" "2 2"
P1=${R1%%,*}; P2=${R1##*,}
as "$P1"; c "$U/espace/croisiere" >/dev/null
check "page : place réservée, bouton payer, temps restant, rappel annoncé" "$(grep -c 'Une place vous est réservée' "$B") $(grep -c 'Payer sur HelloAsso' "$B") $(grep -c 'encore [23] jours' "$B") $(grep -c 'Rappel automatique' "$B")" "1 1 [1-9]* 1"
check "frise : phase « Paiement » en cours" "$(phase)" "Paiement"
as admin; post "$U/espace/croisiere" "$U/espace/croisiere/paye" -d "membre_id=$(id "$P1")" >/dev/null
check "paiement noté par l'organisateur : plus d'échéance" "$(sql "SELECT CONCAT(paye_le IS NOT NULL, ' ', echeance IS NULL) FROM inscription WHERE evenement_id = $(EV croisiere) AND membre_id = $(id "$P1")")" "1 1"

# Délai dépassé : la place passe au premier de la liste d'attente, avec à son tour 3 jours
A1=$(nom "$(sql "SELECT membre_id FROM inscription WHERE evenement_id = $(EV croisiere) AND statut = 'attente' ORDER BY rang LIMIT 1")")
sql "UPDATE inscription SET echeance = NOW() - INTERVAL 1 MINUTE WHERE evenement_id = $(EV croisiere) AND membre_id = $(id "$P2")"
cron
check "délai dépassé : place perdue, e-mail" "$(statut croisiere "$P2") $(grep -c 'Subject: Place libérée : Croisière' "$MAIL")" "expire 1"
check "le premier de la liste d'attente a la place, avec 3 jours" "$(statut croisiere "$A1") $(sql "SELECT echeance BETWEEN NOW() + INTERVAL 71 HOUR AND NOW() + INTERVAL 73 HOUR FROM inscription WHERE evenement_id = $(EV croisiere) AND membre_id = $(id "$A1")") $(grep -c 'Subject: Une place pour vous : Croisière' "$MAIL")" "retenu 1 1"
as "$P2"; c "$U/espace/croisiere" >/dev/null
check "la personne en retard voit pourquoi et peut revenir en attente" "$(grep -c 'Le délai pour payer est passé' "$B") $(grep -c 'Me remettre sur la liste' "$B")" "1 1"

# Rappel la veille de l'échéance, une seule fois
sql "UPDATE inscription SET echeance = NOW() + INTERVAL 12 HOUR WHERE evenement_id = $(EV croisiere) AND membre_id = $(id "$A1")"
cron; cron
check "rappel la veille de l'échéance, une seule fois" "$(grep -c 'Subject: Rappel : paiement avant le' "$MAIL")" "1"

# Dernières places : plus de tour par tour, toute la liste d'attente est prévenue, premier qui paie
agir "$P2" croisiere voeu
A2=$(nom "$(sql "SELECT membre_id FROM inscription WHERE evenement_id = $(EV croisiere) AND statut = 'attente' ORDER BY rang LIMIT 1")")
sql "UPDATE evenement SET debut = NOW() + INTERVAL 1 DAY WHERE espace_id = $(EV croisiere)"
agir "$A1" croisiere desister
check "dernières places : pas de promotion individuelle" "$(qui croisiere retenu)" "$P1"
check "toute la liste d'attente est prévenue" "$(grep -c 'Subject: Dernières places : Croisière' "$MAIL")" "2"
as "$A2"; c "$U/espace/croisiere" >/dev/null
check "page : « il en reste 1 », bouton payer, phase en cours" "$(grep -c 'il en reste 1' "$B") $(grep -c 'Payer sur HelloAsso' "$B") $(phase)" "1 1 Dernières places"
as admin; post "$U/espace/croisiere" "$U/espace/croisiere/paye" -d "membre_id=$(id "$P2")" >/dev/null
check "premier qui paie : la place est à lui (même s'il n'est pas premier de la liste)" "$(statut croisiere "$P2") $(sql "SELECT echeance IS NULL FROM inscription WHERE evenement_id = $(EV croisiere) AND membre_id = $(id "$P2")")" "retenu 1"
post "$U/espace/croisiere" "$U/espace/croisiere/paye" -d "membre_id=$(id "$A2")" >/dev/null
check "payé trop tard : signalé « sans place » à l'organisateur" "$(statut croisiere "$A2") $(c "$U/espace/croisiere" >/dev/null; grep -c 'sans place' "$B")" "attente 1"
as "$A2"; c "$U/espace/croisiere" >/dev/null
check "et la personne sait qu'elle sera remboursée" "$(grep -c 'vous remboursera' "$B")" "1"

# Événement gratuit à confirmer
as admin; post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=evenement&titre=Atelier&visibilite=adherents' >/dev/null
post "$U/espace/atelier" "$U/espace/atelier/evenement" -d "mode=auto&places=1&debut=$(J 10)&cloture=$(J 1)&reglement=confirmation&delai_jours=2&dernieres_jours=3" >/dev/null
agir emma atelier voeu; agir farid atelier voeu
as admin; post "$U/espace/atelier" "$U/espace/atelier/cloturer" >/dev/null
RE=$(qui atelier retenu); AT=$(qui atelier attente)
as "$RE"; c "$U/espace/atelier" >/dev/null
check "à confirmer : bouton « Je confirme ma venue »" "$(grep -c 'Je confirme ma venue' "$B") $(phase)" "1 Confirmation"
agir "$RE" atelier venue
check "venue confirmée : plus d'échéance" "$(sql "SELECT CONCAT(confirme_le IS NOT NULL, ' ', echeance IS NULL) FROM inscription WHERE evenement_id = $(EV atelier) AND membre_id = $(id "$RE")")" "1 1"
sql "UPDATE evenement SET debut = NOW() + INTERVAL 2 DAY WHERE espace_id = $(EV atelier)"
agir "$RE" atelier desister
agir "$AT" atelier prendre
check "dernière place prise d'un clic" "$(statut atelier "$AT") $(sql "SELECT confirme_le IS NOT NULL FROM inscription WHERE evenement_id = $(EV atelier) AND membre_id = $(id "$AT")")" "retenu 1"
agir "$RE" atelier voeu; agir "$RE" atelier prendre
check "plus de place : refus clair" "$(statut atelier "$RE")" "attente"
bilan
