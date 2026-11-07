#!/bin/bash
# Test de bout en bout de l'étape 8 : rappels des dates clés et des événements (délais réglables, jamais en double)
# Usage : tests/e2e-etape8.sh <base> <utilisateur> <mot de passe> <dossier de travail>
set -u
# shellcheck disable=SC1091
source "$(dirname "$0")/commun.sh" "$@"
id() { sql "SELECT id FROM membre WHERE email = '$1@example.org'"; }
cron() { php "$ROOT/app/bin/cli.php" cron | grep 'rappel(s) envoyé'; }
mails() { grep -c "^To: $1@" "$MAIL" 2>/dev/null; }
J() { date -d "+$1 days" +%Y-%m-%d; }

install_admin
for p in bob carl dan; do post "$U/admin/membres" "$U/admin/membres" -d "email=$p@example.org&prenom=${p^}" >/dev/null; done
post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=projet&titre=Projet phare&visibilite=equipe' >/dev/null
post "$U/espace/projet-phare" "$U/espace/projet-phare/equipe" -d 'email=bob@example.org&niveau=n1' >/dev/null
post "$U/espace/projet-phare" "$U/espace/projet-phare/equipe" -d 'email=carl@example.org&niveau=n2' >/dev/null
post "$U/espace/projet-phare" "$U/espace/projet-phare/equipe" -d 'email=dan@example.org&niveau=membre' >/dev/null
sql "REPLACE INTO parametre (cle, valeur) VALUES ('cotisation_relance_jours', '0')"  # rien que les rappels dans ce test
: > "$MAIL"

post "$U/espace/projet-phare" "$U/espace/projet-phare/jalons" -d "date_jalon=$(J 3)&libelle=Rendre le dossier&destinataires=binome" >/dev/null
check "rappel à J-3 : n°1 et n°2" "$(cron)" "cron : 2 rappel(s) envoyé(s)"
check "bob et carl prévenus, pas dan" "$(mails bob) $(mails carl) $(mails dan)" "1 1 0"
check "e-mail clair : « dans 3 jours »" "$(grep -c 'Rendre le dossier, dans 3 jours' "$MAIL")" "2"
check "jamais deux fois le même rappel" "$(cron)" "cron : 0 rappel(s) envoyé(s)"

post "$U/espace/projet-phare" "$U/espace/projet-phare/jalons" -d "date_jalon=$(J 10)&libelle=Réunion de chantier&destinataires=equipe" >/dev/null
check "rappel à toute l'équipe (délai de 14 jours)" "$(cron)" "cron : 3 rappel(s) envoyé(s)"
JID=$(sql "SELECT id FROM jalon WHERE libelle = 'Réunion de chantier'")
sql "UPDATE jalon SET date_jalon = '$(J 2)' WHERE id = $JID"
check "le délai suivant (3 jours) part à son tour" "$(cron)" "cron : 3 rappel(s) envoyé(s)"

# Cron manqué : seul le délai le plus proche part
post "$U/espace/projet-phare" "$U/espace/projet-phare/jalons" -d "date_jalon=$(J 1)&libelle=Commande&destinataires=binome" >/dev/null
check "cron manqué : un seul rappel, pas trois" "$(cron)" "cron : 2 rappel(s) envoyé(s)"
post "$U/espace/projet-phare" "$U/espace/projet-phare/jalons" -d "date_jalon=$(J 3)&libelle=Déjà fait&destinataires=binome" >/dev/null
JF=$(sql "SELECT id FROM jalon WHERE libelle = 'Déjà fait'")
post "$U/espace/projet-phare" "$U/espace/projet-phare/jalons/$JF/fait" >/dev/null
check "date clé faite : pas de rappel" "$(cron)" "cron : 0 rappel(s) envoyé(s)"

# Événement : délais propres à l'organisation (60 jours) et rappel aux participants
post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=evenement&titre=Fête du phare&visibilite=adherents' >/dev/null
post "$U/espace/fete-du-phare" "$U/espace/fete-du-phare/equipe" -d 'email=bob@example.org&niveau=n1' >/dev/null
post "$U/espace/fete-du-phare" "$U/espace/fete-du-phare/jalons" -d "date_jalon=$(J 50)&libelle=Réserver la salle&destinataires=binome" >/dev/null
check "organisation d'un événement : rappel 2 mois avant" "$(cron)" "cron : 1 rappel(s) envoyé(s)"
post "$U/espace/fete-du-phare" "$U/espace/fete-du-phare/evenement" -d "mode=ouvert&debut=$(J 1)T18:00" >/dev/null
for p in carl dan; do as "$p"; login_link "$p" "$p@example.org"; post "$U/espace/fete-du-phare" "$U/espace/fete-du-phare/inscription" -d 'action=voeu' >/dev/null; done
check "participants prévenus la veille" "$(cron)" "cron : 2 rappel(s) envoyé(s)"
check "e-mail : « demain »" "$(grep -c 'Subject: Rappel : Fête du phare demain' "$MAIL")" "2"

# Délais réglables par l'administrateur
as admin; c "$U/admin/parametres" >/dev/null; T=$(tok)
c --data-urlencode "_csrf=$T" -d 'nom_association=Asso test&couleur_principale=%231f4e79&couleur_accent=%23c8102e&smtp_securite=starttls&rappel_jalons=quinze&rappel_jalons_evenements=60&rappel_participants=7' "$U/admin/parametres" >/dev/null
check "délai invalide refusé" "$(grep -c 'séparés par des virgules' "$B")" "[1-9]*"
c "$U/admin/parametres" >/dev/null; T=$(tok)
c --data-urlencode "_csrf=$T" -d 'nom_association=Asso test&couleur_principale=%231f4e79&couleur_accent=%23c8102e&smtp_securite=starttls&rappel_jalons=&rappel_jalons_evenements=60&rappel_participants=7' "$U/admin/parametres" >/dev/null
post "$U/espace/projet-phare" "$U/espace/projet-phare/jalons" -d "date_jalon=$(J 1)&libelle=Sans rappel&destinataires=equipe" >/dev/null
check "délai vide : aucun rappel" "$(cron)" "cron : 0 rappel(s) envoyé(s)"
bilan
