#!/bin/bash
# Test de bout en bout de l'étape 3 : membres, espaces, droits (équipe, associé, extérieur, visiteur), fichiers, journal
# Usage : tests/e2e-etape3.sh <base> <utilisateur> <mot de passe> <dossier de travail>
set -u
# shellcheck disable=SC1091
source "$(dirname "$0")/commun.sh" "$@"

install_admin
check "admin : gestion des membres accessible" "$(c "$U/admin/membres")" "200 "
for m in alice bob carole dan; do post "$U/admin/membres" "$U/admin/membres" -d "email=$m@example.org&prenom=${m^}" >/dev/null; done
check "4 membres ajoutés" "$(sql 'SELECT COUNT(*) FROM membre')" "5"
check "doublon refusé" "$(post "$U/admin/membres" "$U/admin/membres" -d 'email=alice@example.org' >/dev/null; c "$U/admin/membres" >/dev/null; grep -c 'existe déjà' "$B")" "1"
check "création d'un espace" "$(post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=comite&titre=Comité de direction&visibilite=equipe&description=Pilotage')" "303 $U/espace/comite-de-direction"
c "$U/espace/comite-de-direction" >/dev/null
check "alertes : n°1 et n°2 manquants" "$(grep -o 'Pas de n°[12]' "$B" | wc -l)" "2"
post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/equipe" -d 'email=alice@example.org&niveau=n1&libelle=Présidente' >/dev/null
post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/equipe" -d 'email=bob@example.org&niveau=n2&libelle=Secrétaire' >/dev/null
c "$U/espace/comite-de-direction" >/dev/null
check "n°1 et n°2 en place : plus d'alerte" "$(grep -o 'Pas de n°[12]' "$B" | wc -l)" "0"
check "libellé libre affiché" "$(grep -c 'Présidente' "$B")" "1"
check "membre inconnu refusé dans l'équipe" "$(post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/equipe" -d 'email=inconnu@example.org&niveau=membre' >/dev/null; c "$U/espace/comite-de-direction" >/dev/null; grep -c 'Membre inconnu' "$B")" "1"

login_link bob bob@example.org
check "bob (n°2) voit l'espace" "$(c "$U/espace/comite-de-direction")" "200 "
check "bob ajoute carole comme associée « sage »" "$(post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/equipe" -d 'email=carole@example.org&niveau=associe&libelle=Sage')" "303 $U/espace/comite-de-direction"
check "bob ajoute une date clé passée" "$(post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/jalons" -d 'date_jalon=2020-01-15&libelle=Bilan annuel')" "303 $U/espace/comite-de-direction"
c "$U/espace/comite-de-direction" >/dev/null
check "alerte : date dépassée" "$(grep -c '1 date clé dépassée' "$B")" "1"
printf 'Ordre du jour\n' > "$W/odj.txt"; printf '<?php echo "pirate";' > "$W/pirate.php"
c "$U/espace/comite-de-direction" >/dev/null; T=$(tok)
check "bob dépose un fichier" "$(c -F "_csrf=$T" -F "fichier=@$W/odj.txt" "$U/espace/comite-de-direction/fichiers")" "303 $U/espace/comite-de-direction"
check "un fichier .php est stocké hors du site, jamais exécuté" "$(c -F "_csrf=$T" -F "fichier=@$W/pirate.php" "$U/espace/comite-de-direction/fichiers" >/dev/null; find "$ROOT/app/stockage" -name '*.php' | wc -l)" "0"
FID=$(sql "SELECT id FROM fichier WHERE nom = 'odj.txt'")
check "bob télécharge le fichier (pièce jointe)" "$(curl -s -b "$J" -D - -o /dev/null "$U/fichier/$FID" | grep -i -c 'content-disposition: attachment')" "1"
check "bob change la météo" "$(post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/meteo" -d 'meteo=pluie')" "303 $U/espace/comite-de-direction"
check "météo enregistrée" "$(sql "SELECT meteo FROM espace WHERE slug = 'comite-de-direction'")" "pluie"

login_link carole carole@example.org
check "carole (associée) voit l'espace" "$(c "$U/espace/comite-de-direction")" "200 "
# Sa page n'a aucun formulaire de gestion : le jeton est pris sur une autre page, le refus doit venir des droits
check "carole ne peut pas ajouter de date" "$(post "$U/compte" "$U/espace/comite-de-direction/jalons" -d 'date_jalon=2030-01-01&libelle=x')" "403 "
check "carole ne peut pas gérer l'équipe" "$(post "$U/compte" "$U/espace/comite-de-direction/equipe" -d 'email=dan@example.org&niveau=n1')" "403 "
check "carole télécharge le fichier" "$(c "$U/fichier/$FID")" "200 "

login_link dan dan@example.org
check "dan (hors équipe) : espace refusé" "$(c "$U/espace/comite-de-direction")" "403 "
check "dan : absent de sa liste d'espaces" "$(c "$U/espaces" >/dev/null; grep -c 'Comité de direction' "$B")" "0"
check "dan : fichier introuvable" "$(c "$U/fichier/$FID")" "404 "
check "dan : ne peut pas créer d'espace" "$(c "$U/espaces/nouveau")" "403 "
check "dan : gestion des membres refusée" "$(c "$U/admin/membres")" "403 "
as visiteur
check "visiteur : espace d'équipe → connexion demandée" "$(c "$U/espace/comite-de-direction")" "303 $U/connexion"
check "visiteur : fichier introuvable" "$(c "$U/fichier/$FID")" "404 "

as admin
check "espace public : visible sans connexion" "$(post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=evenement&titre=Journée portes ouvertes&visibilite=public' >/dev/null; as visiteur; c "$U/espace/journee-portes-ouvertes")" "200 "
as admin
check "journal : actions tracées" "$(sql "SELECT COUNT(*) FROM journal j JOIN espace e ON e.id = j.espace_id WHERE e.slug = 'comite-de-direction'")" "[7-9]"
ALICE=$(sql "SELECT id FROM membre WHERE email = 'alice@example.org'")
post "$U/admin/membres" "$U/admin/membres/$ALICE" -d 'prenom=Alice&actif=1&droits[]=membres' >/dev/null
check "délégation « membres » donnée à alice" "$(sql "SELECT droit FROM droit_plateforme WHERE membre_id = $ALICE")" "membres"
ADMIN=$(sql "SELECT id FROM membre WHERE email = 'admin@example.org'")
post "$U/admin/membres" "$U/admin/membres/$ADMIN" -d 'actif=1' >/dev/null
check "le dernier administrateur ne peut pas perdre son droit" "$(sql "SELECT droit FROM droit_plateforme WHERE membre_id = $ADMIN")" "admin"
login_link alice alice@example.org
check "alice (lien e-mail seul) : seconde vérification demandée" "$(c "$U/admin/membres")" "303 $U/connexion/double-authentification"
check "alice : pas de second moyen, elle doit choisir un mot de passe" "$(c "$U/connexion/double-authentification" >/dev/null; grep -c 'Aucun second moyen' "$B")" "1"
post "$U/compte" "$U/compte/mot-de-passe" -d 'mot_de_passe=alice-mot-de-passe-long&confirmation=alice-mot-de-passe-long' >/dev/null
c "$U/connexion/double-authentification" >/dev/null; T=$(tok)
c --data-urlencode "_csrf=$T" -d 'mot_de_passe=alice-mot-de-passe-long' "$U/connexion/double-authentification/mot-de-passe" >/dev/null
check "alice : gestion des membres accessible" "$(c "$U/admin/membres")" "200 "
check "alice : pas de cases « droits » (réservé à l'administrateur)" "$(grep -c 'name="droits\[\]"' "$B")" "0"
check "alice : réglages refusés" "$(c "$U/admin/parametres")" "403 "
DAN=$(sql "SELECT id FROM membre WHERE email = 'dan@example.org'")
post "$U/admin/membres" "$U/admin/membres/$DAN" -d 'prenom=Dan&actif=1&droits[]=admin' >/dev/null
check "alice ne peut pas donner de droits" "$(sql "SELECT COUNT(*) FROM droit_plateforme WHERE membre_id = $DAN")" "0"
# Archiver / supprimer un espace
as bob
check "bob (n°2) archive le comité" "$(post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/archiver")" "303 $U/espace/comite-de-direction"
check "archivé : absent de la liste" "$(c "$U/espaces" >/dev/null; grep -c '>Comité de direction<' "$B")" "0"
check "archivé : présent dans « Espaces archivés »" "$(c "$U/espaces?archives=1" >/dev/null; grep -c '>Comité de direction<' "$B")" "1"
check "archivé : bandeau et bouton « Désarchiver »" "$(c "$U/espace/comite-de-direction" >/dev/null; grep -c 'Désarchiver' "$B")" "1"
check "bob ne peut pas supprimer (administrateur seulement)" "$(post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/supprimer")" "403 "
check "bob désarchive" "$(post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/archiver" >/dev/null; sql "SELECT archive FROM espace WHERE slug = 'comite-de-direction'")" "0"
as admin
post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=projet&titre=Sous projet&visibilite=equipe&parent=comite-de-direction' >/dev/null
sql "UPDATE espace SET parent_id = (SELECT id FROM (SELECT id FROM espace WHERE slug = 'comite-de-direction') x) WHERE slug = 'sous-projet'"
STOCK=$(sql "SELECT stockage FROM fichier WHERE nom = 'odj.txt'")
check "suppression d'une date clé" "$(as bob; c "$U/espace/comite-de-direction" >/dev/null; JID=$(sql "SELECT id FROM jalon WHERE libelle = 'Bilan annuel'"); post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/jalons/$JID/supprimer" >/dev/null; sql "SELECT COUNT(*) FROM jalon WHERE libelle = 'Bilan annuel'")" "0"
as admin
TID=$(sql "SELECT id FROM type_membre WHERE libelle = 'Membre associé'")
check "retrait d'un type de membre" "$(post "$U/admin/membres" "$U/admin/types-membre/$TID/supprimer" >/dev/null; sql "SELECT COUNT(*) FROM type_membre WHERE id = $TID")" "0"
check "admin supprime le comité" "$(post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/supprimer")" "303 $U/espaces"
check "espace, équipe, dates et fichiers effacés" "$(sql "SELECT (SELECT COUNT(*) FROM espace WHERE slug = 'comite-de-direction') + (SELECT COUNT(*) FROM fichier WHERE nom = 'odj.txt') + (SELECT COUNT(*) FROM jalon j JOIN espace e ON e.id = j.espace_id WHERE e.slug = 'comite-de-direction')")" "0"
check "fichier effacé du disque" "$(find "$ROOT/app/stockage" -name "$STOCK" | wc -l)" "0"
check "le sous-espace remonte d'un niveau" "$(sql "SELECT COALESCE(parent_id, 'aucun') FROM espace WHERE slug = 'sous-projet'")" "aucun"
check "admin ne peut pas s'effacer lui-même" "$(post "$U/admin/membres" "$U/admin/membres/$ADMIN/effacer" >/dev/null; sql "SELECT COUNT(*) FROM membre WHERE id = $ADMIN")" "1"
check "admin efface dan définitivement" "$(post "$U/admin/membres" "$U/admin/membres/$DAN/effacer" >/dev/null; sql "SELECT COUNT(*) FROM membre WHERE email = 'dan@example.org'")" "0"
check "déplacer un espace sous un autre, et changer son type" "$(post "$U/espace/sous-projet" "$U/espace/sous-projet/modifier" -d 'titre=Sous projet&visibilite=equipe&type=commission&parent=journee-portes-ouvertes' >/dev/null; sql "SELECT CONCAT(e.type, ' ', p.slug) FROM espace e JOIN espace p ON p.id = e.parent_id WHERE e.slug = 'sous-projet'")" "commission journee-portes-ouvertes"
check "déplacement en boucle refusé" "$(post "$U/espace/journee-portes-ouvertes" "$U/espace/journee-portes-ouvertes/modifier" -d 'titre=Journée portes ouvertes&visibilite=public&type=evenement&parent=sous-projet' >/dev/null; sql "SELECT COALESCE(parent_id, 'aucun') FROM espace WHERE slug = 'journee-portes-ouvertes'")" "aucun"
bilan
