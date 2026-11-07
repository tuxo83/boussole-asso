#!/bin/bash
# Test de bout en bout : site en construction (bandeau, code d'accès à 6 chiffres, membres, API, essais limités)
# Usage : tests/e2e-etape11.sh <base> <utilisateur> <mot de passe> <dossier de travail>
set -u
# shellcheck disable=SC1091
source "$(dirname "$0")/commun.sh" "$@"
reglages() { # reglages <actif 1 ou vide> <code>
    as admin; c "$U/admin/parametres" >/dev/null; local t; t=$(tok)
    c --data-urlencode "_csrf=$t" -d "nom_association=Asso test&couleur_principale=%231f4e79&couleur_accent=%23c8102e&smtp_securite=starttls&mail_expediteur=noreply@example.org&rappel_jalons=14&rappel_jalons_evenements=60&rappel_participants=7&construction_active=$1&construction_code=$2" "$U/admin/parametres"
}
code_actuel() { sql "SELECT valeur FROM parametre WHERE cle = 'construction_code'"; }

install_admin
check "code invalide refusé" "$(reglages 1 12ab; echo) $(code_actuel)" "422 *"
reglages 1 '' >/dev/null
CODE=$(code_actuel)
check "activé sans code : code tiré au hasard" "$CODE" "[0-9][0-9][0-9][0-9][0-9][0-9]"

as visiteur
check "visiteur : renvoyé vers le code d'accès" "$(c "$U/page/statuts")" "303 $U/acces?suite=%2Fpage%2Fstatuts"
check "page d'accès : bandeau et champ du code" "$(c "$U/acces" >/dev/null; grep -c 'bandeau-construction' "$B") $(grep -c 'name="code"' "$B")" "1 1"
check "pas d'indexation par les moteurs" "$(curl -s -D - -o /dev/null "$U/acces" | grep -ci 'x-robots-tag: noindex')" "1"
check "connexion des membres accessible sans code" "$(c "$U/connexion" | cut -c1-3)" "200"
check "mauvais code : refusé" "$(post "$U/acces" "$U/acces" -d 'code=000000&suite=/' | cut -c1-3)" "422"
check "bon code : retour à la page demandée" "$(post "$U/acces" "$U/acces" -d "code=$CODE&suite=/espaces")" "303 $U/espaces"
check "ensuite le site s'ouvre, bandeau affiché" "$(c "$U/" | cut -c1-3) $(grep -c 'Site en construction' "$B")" "200 1"
check "redirection vers un autre site impossible" "$(as visiteur2; post "$U/acces" "$U/acces" -d "code=$CODE&suite=//evil.example/x")" "303 $U/"

as admin
check "membre connecté : pas de code demandé" "$(c "$U/compte" | cut -c1-3)" "200"
post "$U/compte/cles" "$U/compte/cles" -d 'libelle=IA&portee=lecture&duree=7' >/dev/null; c "$U/compte/cles" >/dev/null; KEY=$(grep -o 'bsl_[a-f0-9]\{8\}_[a-f0-9]\{40\}' "$B" | head -1)
check "API et MCP : pas de code demandé" "$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $KEY" "$U/api/moi")" "200"

reglages 1 654321 >/dev/null
check "code changé : l'ancien accès est fermé" "$(as visiteur; c "$U/" | cut -c1-3)" "303"

as robot
for _ in $(seq 1 10); do post "$U/acces" "$U/acces" -d 'code=111111' >/dev/null; done
check "essais limités (10 par 15 minutes)" "$(post "$U/acces" "$U/acces" -d 'code=654321' | cut -c1-3)" "429"

reglages '' 654321 >/dev/null
check "mode désactivé : site ouvert, plus de bandeau" "$(as visiteur3; c "$U/" | cut -c1-3) $(grep -c 'bandeau-construction' "$B")" "200 0"
check "page d'accès inutile : renvoi à l'accueil" "$(c "$U/acces")" "303 $U/"
# Pages réservées aux membres connectés
sql "INSERT INTO page (slug, titre, contenu, blocs, publiee, dans_menu, reservee_membres) VALUES ('comite-directeur', 'Comité directeur', '', '{\"blocks\":[{\"type\":\"paragraph\",\"data\":{\"text\":\"Président : X\"}}]}', 1, 1, 1), ('association', 'L''association', '', '{\"blocks\":[]}', 1, 1, 0)"
sql "UPDATE page SET parent_id = (SELECT id FROM (SELECT id FROM page WHERE slug = 'association') t) WHERE slug = 'comite-directeur'"
sql "UPDATE page SET parent_id = NULL WHERE slug = 'comite-directeur'"
as visiteur4
check "page réservée : un visiteur est envoyé vers la connexion" "$(c "$U/page/comite-directeur")" "303 $U/connexion"
check "page réservée : absente du menu pour un visiteur" "$(c "$U/" >/dev/null; grep -c 'Comité directeur' "$B")" "0"
as admin
check "page réservée : visible et dans le menu une fois connecté" "$(c "$U/page/comite-directeur" | cut -c1-3) $(grep -c 'Président : X' "$B") $(grep -c '>Comité directeur<' "$B")" "200 1 [1-9]*"
# Agenda public : événement réservé aux membres montré en aperçu, sans détail
sql "INSERT INTO espace (type, titre, slug, description, visibilite) VALUES ('evenement', 'Visite réservée', 'visite-reservee', 'Détail confidentiel du rendez-vous', 'adherents'), ('evenement', 'Réunion d''équipe', 'reunion-equipe', 'Interne', 'equipe')"
sql "INSERT INTO page (slug, titre, contenu, blocs, publiee) VALUES ('agenda', 'Agenda', '', '{\"blocks\":[{\"type\":\"boussole\",\"data\":{\"bloc\":\"evenements\"}}]}', 1)"
as visiteur5
check "agenda public : un événement réservé est signalé, sans titre ni détail ni lien" "$(c "$U/page/agenda" >/dev/null; grep -c 'Réservé aux membres' "$B") $(grep -c 'Visite réservée\|Détail confidentiel\|visite-reservee' "$B")" "1 0"
check "agenda public : rien des événements d'équipe" "$(grep -c 'Réunion' "$B")" "0"
as admin
check "membre connecté : détail visible" "$(c "$U/page/agenda" >/dev/null; grep -c 'Détail confidentiel' "$B")" "1"
# Niveau « membres à jour de cotisation »
sql "INSERT INTO espace (type, titre, slug, description, visibilite) VALUES ('evenement', 'Dîner des cotisants', 'diner-cotisants', 'Menu et lieu du dîner', 'cotisants')"
sql "INSERT INTO membre (email, prenom, actif) VALUES ('lea@example.org', 'Léa', 1)"
login_link lea lea@example.org
check "membre pas à jour : titre et invitation à cotiser, sans détail" "$(c "$U/page/agenda" >/dev/null; grep -c 'Dîner des cotisants' "$B") $(grep -c 'Réglez votre cotisation' "$B") $(grep -c 'Menu et lieu' "$B")" "1 1 0"
check "membre pas à jour : la page de l'événement est refusée" "$(c "$U/espace/diner-cotisants" | cut -c1-3)" "403"
sql "INSERT INTO cotisation (membre_id, periode, paye_le, moyen) SELECT id, $(date +%Y), CURDATE(), 'cheque' FROM membre WHERE email = 'lea@example.org'"
check "membre à jour : détail visible" "$(c "$U/page/agenda" >/dev/null; grep -c 'Menu et lieu' "$B") $(c "$U/espace/diner-cotisants" | cut -c1-3)" "1 200"
bilan
