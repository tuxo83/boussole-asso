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
check "libellé libre affiché" "$(grep -c '>Présidente\|· Présidente' "$B")" "1"
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
check "aperçu refusé pour un .txt : reste en pièce jointe" "$(curl -s -b "$J" -D - -o /dev/null "$U/fichier/$FID?apercu=1" | grep -i -c 'content-disposition: attachment')" "1"
printf '%%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%%%EOF\n' > "$W/cr.pdf"
printf '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' > "$W/x.svg"
c -F "_csrf=$T" -F "fichier=@$W/cr.pdf" "$U/espace/comite-de-direction/fichiers" >/dev/null
c -F "_csrf=$T" -F "fichier=@$W/x.svg" "$U/espace/comite-de-direction/fichiers" >/dev/null
PID=$(sql "SELECT id FROM fichier WHERE nom = 'cr.pdf'"); SID=$(sql "SELECT id FROM fichier WHERE nom = 'x.svg'")
check "aperçu d'un PDF : affiché dans le navigateur" "$(curl -s -b "$J" -D - -o /dev/null "$U/fichier/$PID?apercu=1" | grep -i -c 'content-disposition: inline\|content-type: application/pdf')" "2"
check "PDF sans ?apercu : pièce jointe" "$(curl -s -b "$J" -D - -o /dev/null "$U/fichier/$PID" | grep -i -c 'content-disposition: attachment')" "1"
check "aperçu refusé pour un SVG (peut porter du code)" "$(curl -s -b "$J" -D - -o /dev/null "$U/fichier/$SID?apercu=1" | grep -i -c 'content-type: application/octet-stream')" "1"
c "$U/espace/comite-de-direction" >/dev/null
check "fiche : fenêtre d'aperçu et lien du PDF marqué" "$(grep -c 'id="dialogue-fichier"\|data-fichier="pdf"' "$B")" "2"
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
N_JOURNAL=$(sql "SELECT COUNT(*) FROM journal j JOIN espace e ON e.id = j.espace_id WHERE e.slug = 'comite-de-direction'")
check "journal : actions tracées (9 à 11)" "$( [ "$N_JOURNAL" -ge 9 ] && [ "$N_JOURNAL" -le 11 ] && echo "$N_JOURNAL")" "$N_JOURNAL"
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
check "suppression refusée si le nom n'est pas recopié" "$(post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/supprimer" -d 'confirmation=autre chose' >/dev/null; sql "SELECT COUNT(*) FROM espace WHERE slug = 'comite-de-direction'")" "1"
check "admin supprime le comité (nom recopié)" "$(post "$U/espace/comite-de-direction" "$U/espace/comite-de-direction/supprimer" -d 'confirmation=comité de direction')" "303 $U/espaces"
check "espace, équipe, dates et fichiers effacés" "$(sql "SELECT (SELECT COUNT(*) FROM espace WHERE slug = 'comite-de-direction') + (SELECT COUNT(*) FROM fichier WHERE nom = 'odj.txt') + (SELECT COUNT(*) FROM jalon j JOIN espace e ON e.id = j.espace_id WHERE e.slug = 'comite-de-direction')")" "0"
check "fichier effacé du disque" "$(find "$ROOT/app/stockage" -name "$STOCK" | wc -l)" "0"
check "le sous-espace remonte d'un niveau" "$(sql "SELECT COALESCE(parent_id, 'aucun') FROM espace WHERE slug = 'sous-projet'")" "aucun"
check "admin change l'e-mail d'un membre" "$(post "$U/admin/membres" "$U/admin/membres/$ALICE" -d 'email=alice.nouvelle@example.org&prenom=Alice&actif=1&droits[]=membres' >/dev/null; sql "SELECT email FROM membre WHERE id = $ALICE")" "alice.nouvelle@example.org"
check "e-mail déjà pris : refusé" "$(post "$U/admin/membres" "$U/admin/membres/$ALICE" -d 'email=bob@example.org&prenom=Alice&actif=1&droits[]=membres' >/dev/null; sql "SELECT email FROM membre WHERE id = $ALICE")" "alice.nouvelle@example.org"
BOB=$(sql "SELECT id FROM membre WHERE email = 'bob@example.org'")
post "$U/admin/membres" "$U/admin/membres/$BOB/mot-de-passe-provisoire" >/dev/null; c "$U/admin/membres" >/dev/null
PROV=$(grep -o 'bob@example.org) : [A-Za-z0-9]\{4\}-[A-Za-z0-9]\{4\}-[A-Za-z0-9]\{4\}' "$B" | sed 's/.* : //')
check "mot de passe provisoire affiché une fois à l'administrateur" "$([ -n "$PROV" ] && echo oui) $(c "$U/admin/membres" >/dev/null; grep -c "$PROV" "$B")" "oui 0"
as bobmdp
check "bob se connecte avec le provisoire → doit en choisir un" "$(hpost "$U/connexion" "$U/connexion" -d "email=bob@example.org&mot_de_passe=$PROV" >/dev/null; c "$U/espaces")" "303 $U/compte/nouveau-mot-de-passe"
check "provisoire : réutiliser le même est refusé" "$(post "$U/compte/nouveau-mot-de-passe" "$U/compte/nouveau-mot-de-passe" -d "mot_de_passe=$PROV&confirmation=$PROV" >/dev/null; grep -c 'différent du mot de passe provisoire' "$B")" "1"
check "provisoire : nouveau mot de passe choisi, accès rétabli" "$(post "$U/compte/nouveau-mot-de-passe" "$U/compte/nouveau-mot-de-passe" -d 'mot_de_passe=bob-nouveau-mot-de-passe&confirmation=bob-nouveau-mot-de-passe' >/dev/null; c "$U/espaces" | cut -c1-3) $(sql "SELECT mot_de_passe_provisoire FROM membre WHERE id = $BOB")" "200 0"
as admin
check "admin ne peut pas s'effacer lui-même" "$(post "$U/admin/membres" "$U/admin/membres/$ADMIN/effacer" >/dev/null; sql "SELECT COUNT(*) FROM membre WHERE id = $ADMIN")" "1"
check "admin efface dan définitivement" "$(post "$U/admin/membres" "$U/admin/membres/$DAN/effacer" >/dev/null; sql "SELECT COUNT(*) FROM membre WHERE email = 'dan@example.org'")" "0"
check "déplacer un espace sous un autre, et changer son type" "$(post "$U/espace/sous-projet" "$U/espace/sous-projet/modifier" -d 'titre=Sous projet&visibilite=equipe&type=commission&parent=journee-portes-ouvertes' >/dev/null; sql "SELECT CONCAT(e.type, ' ', p.slug) FROM espace e JOIN espace p ON p.id = e.parent_id WHERE e.slug = 'sous-projet'")" "commission journee-portes-ouvertes"
check "déplacement en boucle refusé" "$(post "$U/espace/journee-portes-ouvertes" "$U/espace/journee-portes-ouvertes/modifier" -d 'titre=Journée portes ouvertes&visibilite=public&type=evenement&parent=sous-projet' >/dev/null; sql "SELECT COALESCE(parent_id, 'aucun') FROM espace WHERE slug = 'journee-portes-ouvertes'")" "aucun"
check "rubriques vides cachées à qui ne peut pas les remplir" "$(as visiteur; c "$U/espace/journee-portes-ouvertes" >/dev/null; grep -c '<h2>Équipe</h2>\|<h2>Dates clés</h2>\|<h2>Fichiers</h2>' "$B") $(as admin; c "$U/espace/journee-portes-ouvertes" >/dev/null; grep -c '<h2>Équipe</h2>\|<h2>Dates clés</h2>\|<h2>Fichiers</h2>' "$B")" "0 3"
check "journal : lignes plus vieilles que la durée réglée effacées par les tâches" "$(sql "INSERT INTO journal (action, le) VALUES ('ancienne', NOW() - INTERVAL 4 YEAR), ('récente', NOW() - INTERVAL 2 YEAR)"; php "$ROOT/app/bin/cli.php" cron >/dev/null; sql "SELECT COUNT(*) FROM journal WHERE action IN ('ancienne', 'récente')")" "1"
as intrus
hpost "$U/connexion" "$U/connexion" -d 'email=carole@example.org&mot_de_passe=mauvais-mot-de-passe-123' >/dev/null
as admin
CAROLE=$(sql "SELECT id FROM membre WHERE email = 'carole@example.org'")
check "historique du compte : mot de passe choisi, connexions, échec de connexion" "$(c "$U/admin/membres/$ALICE/historique" >/dev/null; grep -c 'Mot de passe choisi' "$B") $(grep -c 'Connexion (e-mail)' "$B") $(c "$U/admin/membres/$CAROLE/historique" >/dev/null; grep -c 'Échec de connexion' "$B")" "1 1 1"
check "historique : action d'un administrateur signée" "$(c "$U/admin/membres/$BOB/historique" >/dev/null; grep -c 'Mot de passe provisoire donné par un administrateur' "$B")" "1"
check "mon historique dans Mon compte" "$(as bobmdp; c "$U/compte/parametres" >/dev/null; grep -c 'Mot de passe provisoire remplacé' "$B")" "1"
as admin
check "compte de démonstration : l'administrateur fixe son mot de passe" "$(post "$U/admin/membres" "$U/admin/membres/$BOB" -d 'email=bob@example.org&prenom=Bob&actif=1&compte_demo=1&mot_de_passe_demo=demo-fixe-123' >/dev/null; sql "SELECT CONCAT(compte_demo, mot_de_passe_provisoire) FROM membre WHERE id = $BOB")" "10"
check "compte de démonstration : pas de mot de passe provisoire" "$(post "$U/admin/membres" "$U/admin/membres/$BOB/mot-de-passe-provisoire" >/dev/null; sql "SELECT mot_de_passe_provisoire FROM membre WHERE id = $BOB")" "0"
as demo
hpost "$U/connexion" "$U/connexion" -d 'email=bob@example.org&mot_de_passe=demo-fixe-123' >/dev/null
check "compte de démonstration : connexion avec le mot de passe fixé, formulaire de changement absent" "$(c "$U/compte/parametres" >/dev/null; grep -c 'Compte de démonstration : fixé par un administrateur' "$B") $(grep -c 'name="mot_de_passe"' "$B")" "1 0"
check "compte de démonstration : ses utilisateurs ne changent ni mot de passe ni clés" "$(post "$U/compte" "$U/compte/mot-de-passe" -d 'mot_de_passe=pirate-mot-de-passe&confirmation=pirate-mot-de-passe' >/dev/null; post "$U/compte/cles" "$U/compte/cles" -d 'libelle=x&duree=30' >/dev/null; sql "SELECT COUNT(*) FROM cle_api WHERE membre_id = $BOB") $(as admin; c "$U/admin/membres/$BOB/historique" >/dev/null; grep -c 'Mot de passe changé' "$B")" "0 0"
as admin
check "espace : titre, description et visibilité modifiables sur place par qui gère, pas par un visiteur" "$(c "$U/espace/journee-portes-ouvertes" >/dev/null; grep -c 'data-en-place>' "$B") $(as visiteur; c "$U/espace/journee-portes-ouvertes" >/dev/null; grep -c 'data-en-place>' "$B")" "3 0"
as admin
check "modifier seulement la description garde le titre et la visibilité" "$(post "$U/espace/journee-portes-ouvertes" "$U/espace/journee-portes-ouvertes/modifier" -d 'titre=Journée portes ouvertes&visibilite=public&type=evenement&description=Nouvelle description' >/dev/null; sql "SELECT CONCAT(titre, ' | ', visibilite, ' | ', description) FROM espace WHERE slug = 'journee-portes-ouvertes'")" "Journée portes ouvertes | public | Nouvelle description"
# Événement rattaché à un espace : réunion d'équipe visible de l'équipe de l'espace, pas des autres membres
as admin
post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=commission&titre=Cyber&visibilite=adherents' >/dev/null
post "$U/espace/cyber" "$U/espace/cyber/equipe" -d 'email=carole@example.org&niveau=n1' >/dev/null
JOUR=$(date -d '+10 days' +%Y-%m-%dT18:30)
as carole
check "espace : bouton « Organiser un événement » pour qui le gère" "$(c "$U/espace/cyber" >/dev/null; grep -c '+ Organiser<' "$B")" "1"
check "réunion d'équipe créée dans l'espace, avec date et lieu" "$(post "$U/espace/cyber" "$U/espaces/nouveau" -d "type=evenement&parent=cyber&titre=Réunion cyber&debut=$JOUR&lieu=En visio&visibilite=equipe") $(sql "SELECT CONCAT(p.slug, ' ', e.lieu) FROM espace s JOIN espace p ON p.id = s.parent_id JOIN evenement e ON e.espace_id = s.id WHERE s.slug = 'reunion-cyber'")" "303 $U/espace/reunion-cyber cyber En visio"
check "la réunion apparaît dans l'espace et dans « Mes prochains rendez-vous »" "$(c "$U/espace/cyber" >/dev/null; grep -c 'Réunion cyber' "$B") $(c "$U/compte" >/dev/null; grep -c 'Réunion · Cyber' "$B")" "1 1"
as alice
check "un membre hors de l'équipe ne la voit pas" "$(c "$U/espace/reunion-cyber" | cut -c1-3) $(c "$U/espace/cyber" >/dev/null; grep -c 'Réunion cyber' "$B")" "403 0"
# Bloc « Équipe d'un espace » dans une page : noms et rôles lus dans l'espace, jamais l'adresse
as admin
post "$U/espace/cyber" "$U/espace/cyber/equipe" -d 'email=carole@example.org&niveau=n1&libelle=Bureau : Présidente ; Délégations : Cyber' >/dev/null
sql "INSERT INTO page (slug, titre, contenu, blocs, publiee) VALUES ('qui-fait-quoi', 'Qui fait quoi', '', '{\"blocks\":[{\"type\":\"equipe\",\"data\":{\"espace\":\"cyber\"}}]}', 1)"
check "bloc équipe : groupes et rôles venus de l'espace, sans adresse" "$(c "$U/page/qui-fait-quoi" >/dev/null; grep -c '<h3>Bureau</h3>' "$B") $(grep -c '<h3>Délégations</h3>' "$B") $(grep -c 'Présidente' "$B") $(grep -c 'carole@example.org' "$B")" "1 1 1 0"
post "$U/espace/cyber" "$U/espace/cyber/equipe" -d 'email=carole@example.org&niveau=n1&libelle=Bureau : Trésorière' >/dev/null
check "bloc équipe : un changement dans l'espace se voit aussitôt sur la page" "$(c "$U/page/qui-fait-quoi" >/dev/null; grep -c 'Trésorière' "$B") $(grep -c 'Présidente' "$B")" "1 0"
check "équipe : groupe et rôle saisis séparément" "$(post "$U/espace/cyber" "$U/espace/cyber/equipe" -d 'email=carole@example.org&niveau=n1&groupe=Bureau&role=Secrétaire' >/dev/null; sql "SELECT em.libelle FROM espace_membre em JOIN espace s ON s.id = em.espace_id JOIN membre m ON m.id = em.membre_id WHERE s.slug = 'cyber' AND m.email = 'carole@example.org'")" "Bureau : Secrétaire"
# Météo automatique : d'après le responsable et les dates clés ; un choix à la main la coupe
as carole
post "$U/espace/cyber" "$U/espace/cyber/meteo-auto" -d 'meteo_auto=1' >/dev/null
sql "INSERT INTO jalon (espace_id, libelle, date_jalon) SELECT id, 'Rapport', CURDATE() - INTERVAL 3 DAY FROM espace WHERE slug = 'cyber'"
check "météo auto : une date clé dépassée = en retard, avec la raison" "$(c "$U/espace/cyber" >/dev/null; sql "SELECT CONCAT(meteo, ' ', meteo_auto) FROM espace WHERE slug = 'cyber'") $(grep -c 'meteo-raison discret">1 date clé dépassée' "$B")" "pluie 1 1"
sql "UPDATE jalon SET date_jalon = CURDATE() - INTERVAL 40 DAY WHERE libelle = 'Rapport'"
check "météo auto : dépassée depuis longtemps = bloqué (tâche planifiée)" "$(php "$ROOT/app/bin/cli.php" cron >/dev/null; sql "SELECT meteo FROM espace WHERE slug = 'cyber'")" "orage"
post "$U/espace/cyber" "$U/espace/cyber/meteo" -d 'meteo=soleil' >/dev/null
check "météo choisie à la main : le mode automatique se coupe" "$(c "$U/espace/cyber" >/dev/null; sql "SELECT CONCAT(meteo, ' ', meteo_auto) FROM espace WHERE slug = 'cyber'")" "soleil 0"
# Page « Gérer » : réservée à qui gère ; la page de l'espace ne garde que le contenu
as admin
check "page Gérer : onglets Organisation, Journal, Archiver" "$(c "$U/espace/cyber/gerer" | cut -c1-3) $(grep -c 'id="organisation"' "$B") $(grep -c 'id="journal"' "$B") $(grep -c 'id="archiver"' "$B")" "200 1 1 1"
check "la page de l'espace n'a plus la zone de gestion, mais un bouton Gérer" "$(c "$U/espace/cyber" >/dev/null; grep -c 'Supprimer définitivement' "$B") $(grep -c '/espace/cyber/gerer' "$B")" "0 1"
as alice
check "page Gérer refusée à qui ne gère pas" "$(c "$U/espace/cyber/gerer" | cut -c1-3)" "403"
# Vue membre / vue organisateur
as admin
check "vue membre : ni gestion ni « À faire », choix de vue proposé" "$(c "$U/espace/cyber?vue=membre" >/dev/null; grep -c 'class="a-faire"' "$B") $(grep -c 'data-en-place>' "$B") $(grep -c '?vue=orga' "$B")" "0 0 1"
check "vue organisateur retenue" "$(c "$U/espace/cyber?vue=orga" >/dev/null; c "$U/espace/cyber" >/dev/null; grep -c 'data-en-place>' "$B")" "3"
bilan
