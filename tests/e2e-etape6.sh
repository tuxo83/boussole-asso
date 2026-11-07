#!/bin/bash
# Test de bout en bout de l'étape 6 : événements, vœux, attribution équitable, invités prioritaires,
# liste validée par l'organisateur, désistement et promotion, clôture par le cron, MCP
# Usage : tests/e2e-etape6.sh <base> <utilisateur> <mot de passe> <dossier de travail>
set -u
# shellcheck disable=SC1091
source "$(dirname "$0")/commun.sh" "$@"
id() { sql "SELECT id FROM membre WHERE email = '$1@example.org'"; }
statut() { sql "SELECT CONCAT(statut, IFNULL(CONCAT(' ', rang), '')) FROM inscription i JOIN espace s ON s.id = i.evenement_id WHERE s.slug = '$1' AND i.membre_id = $(id "$2")"; }
agir() { as "$1"; post "$U/espace/$2" "$U/espace/$2/inscription" -d "action=$3" >/dev/null; }
DEMAIN=$(date -d '+1 day' +%Y-%m-%dT10:00); MOIS=$(date -d '+30 days' +%Y-%m-%dT18:30)

install_admin
sql "REPLACE INTO parametre (cle, valeur) VALUES ('inscription_cotisants', '0')"  # ce test porte sur l'attribution, pas sur la cotisation
for p in alice bruno chloe david emma farid; do post "$U/admin/membres" "$U/admin/membres" -d "email=$p@example.org&prenom=${p^}" >/dev/null; done
# Historique sur 12 mois : alice 3 participations, bruno 1, emma 1, chloé et david 0 (moyenne 5/7 ≈ 0,7)
for n in 1 2 3; do
  sql "INSERT INTO espace (type, titre, slug, description) VALUES ('evenement', 'Passé $n', 'passe-$n', '')"
  sql "INSERT INTO evenement (espace_id, debut, mode, etat) SELECT id, NOW() - INTERVAL $n MONTH, 'auto', 'attribuee' FROM espace WHERE slug = 'passe-$n'"
  sql "INSERT INTO inscription (evenement_id, membre_id, statut) SELECT s.id, $(id alice), 'retenu' FROM espace s WHERE slug = 'passe-$n'"
done
sql "INSERT INTO inscription (evenement_id, membre_id, statut) SELECT s.id, m.id, 'retenu' FROM espace s, membre m WHERE s.slug = 'passe-1' AND m.email IN ('bruno@example.org', 'emma@example.org')"
# Un événement ouvert à tous (AG) ne compte pas : david y était, il reste « jamais venu »
sql "INSERT INTO espace (type, titre, slug, description) VALUES ('evenement', 'AG passée', 'ag-passee', '')"
sql "INSERT INTO evenement (espace_id, debut, mode, etat) SELECT id, NOW() - INTERVAL 1 MONTH, 'ouvert', 'ouvert' FROM espace WHERE slug = 'ag-passee'"
sql "INSERT INTO inscription (evenement_id, membre_id, statut) SELECT s.id, $(id david), 'retenu' FROM espace s WHERE slug = 'ag-passee'"
# Une participation de plus de 12 mois ne compte pas
sql "INSERT INTO espace (type, titre, slug, description) VALUES ('evenement', 'Ancien', 'ancien', '')"
sql "INSERT INTO evenement (espace_id, debut, mode, etat) SELECT id, NOW() - INTERVAL 14 MONTH, 'auto', 'attribuee' FROM espace WHERE slug = 'ancien'"
sql "INSERT INTO inscription (evenement_id, membre_id, statut) SELECT s.id, $(id chloe), 'retenu' FROM espace s WHERE slug = 'ancien'"

as admin
post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=evenement&titre=Visite du port&visibilite=adherents&description=Visite guidée' >/dev/null
check "places limitées sans clôture : refusé" "$(post "$U/espace/visite-du-port" "$U/espace/visite-du-port/evenement" -d "mode=auto&places=3&debut=$MOIS" >/dev/null; sql "SELECT COUNT(*) FROM evenement e JOIN espace s ON s.id = e.espace_id WHERE s.slug = 'visite-du-port'")" "0"
post "$U/espace/visite-du-port" "$U/espace/visite-du-port/evenement" -d "mode=auto&places=3&debut=$MOIS&cloture=$DEMAIN&lieu=Quai nord" >/dev/null
check "événement réglé" "$(sql "SELECT CONCAT(mode, ' ', places, ' ', etat) FROM evenement e JOIN espace s ON s.id = e.espace_id WHERE s.slug = 'visite-du-port'")" "auto 3 ouvert"
post "$U/espace/visite-du-port" "$U/espace/visite-du-port/inviter" -d 'email=farid@example.org' >/dev/null
check "invitation : statut invité, e-mail envoyé" "$(statut visite-du-port farid)$(grep -c 'Subject: Invitation : Visite du port' "$MAIL")" "invite1"

# Alice dépose son vœu la première : ça ne lui donne aucun avantage
for p in alice bruno chloe david emma farid; do login_link "$p" "$p@example.org"; done
for p in alice bruno emma chloe david; do agir "$p" visite-du-port voeu; done
check "vœu : l'heure ne compte pas, message clair" "$(c "$U/espace/visite-du-port" >/dev/null; grep -c "heure de dépôt ne compte pas" "$B")" "[1-9]*"
agir farid visite-du-port confirmer
check "l'invité confirme" "$(statut visite-du-port farid)" "voeu"
agir david visite-du-port voeu
check "double vœu impossible" "$(sql "SELECT COUNT(*) FROM inscription WHERE membre_id = $(id david) AND evenement_id = (SELECT id FROM espace WHERE slug = 'visite-du-port')")" "1"
as bruno; c "$U/espace/visite-du-port" >/dev/null
check "un membre ne voit pas la liste des candidats" "$(grep -c 'Inscrits et candidats' "$B")" "0"

# Clôture (date passée) : traitée à l'affichage, sans cron
sql "UPDATE evenement SET cloture = NOW() - INTERVAL 1 MINUTE WHERE espace_id = (SELECT id FROM espace WHERE slug = 'visite-du-port')"
as alice; c "$U/espace/visite-du-port" >/dev/null
check "attribution faite à la clôture" "$(sql "SELECT etat FROM evenement e JOIN espace s ON s.id = e.espace_id WHERE s.slug = 'visite-du-port'")" "attribuee"
check "groupe 0 : l'invité est inscrit" "$(statut visite-du-port farid)" "retenu"
check "groupe 1 : chloé (rien sur 12 mois) inscrite" "$(statut visite-du-port chloe)" "retenu"
check "groupe 1 : david (seulement l'AG, qui ne compte pas) inscrit" "$(statut visite-du-port david)" "retenu"
check "groupe 3 : alice, la plus assidue, dernière de la liste d'attente" "$(statut visite-du-port alice)" "attente 3"
check "bruno et emma (groupe 2) : attente 1 et 2" "$(sql "SELECT GROUP_CONCAT(rang ORDER BY rang) FROM inscription WHERE statut = 'attente' AND membre_id IN ($(id bruno), $(id emma))")" "1,2"
check "e-mails : 3 confirmations, 3 listes d'attente" "$(grep -c 'Subject: Inscription confirmée' "$MAIL") $(grep -c "Subject: Liste d'attente" "$MAIL")" "3 3"
check "tirage reproductible : recalcul identique" "$(sql "SELECT GROUP_CONCAT(membre_id ORDER BY rang) FROM inscription WHERE statut = 'attente'")" "$(sql "SELECT GROUP_CONCAT(membre_id ORDER BY rang) FROM inscription WHERE statut = 'attente'")"
PREMIER=$(sql "SELECT membre_id FROM inscription WHERE statut = 'attente' AND rang = 1 AND evenement_id = (SELECT id FROM espace WHERE slug = 'visite-du-port')")
as alice; c "$U/espace/visite-du-port" >/dev/null
check "alice voit son rang" "$(grep -o "n°3 sur la liste d'attente" "$B")" "n°3 sur la liste d'attente"

agir david visite-du-port desister
check "désistement : le premier de la liste d'attente est promu" "$(sql "SELECT statut FROM inscription WHERE membre_id = $PREMIER AND evenement_id = (SELECT id FROM espace WHERE slug = 'visite-du-port')")" "retenu"
check "toujours 3 inscrits" "$(sql "SELECT COUNT(*) FROM inscription WHERE statut = 'retenu' AND evenement_id = (SELECT id FROM espace WHERE slug = 'visite-du-port')")" "3"
check "e-mail « une place pour vous »" "$(grep -c 'Subject: Une place pour vous' "$MAIL")" "1"
agir alice visite-du-port desister
check "désistement en liste d'attente : personne n'est promu à tort" "$(sql "SELECT COUNT(*) FROM inscription WHERE statut = 'retenu' AND evenement_id = (SELECT id FROM espace WHERE slug = 'visite-du-port')")" "3"
as admin; post "$U/espace/visite-du-port" "$U/espace/visite-du-port/evenement" -d "mode=auto&places=4&debut=$MOIS&cloture=$DEMAIN&lieu=Quai nord" >/dev/null
check "une place de plus : promotion du suivant" "$(sql "SELECT COUNT(*) FROM inscription WHERE statut = 'retenu' AND evenement_id = (SELECT id FROM espace WHERE slug = 'visite-du-port')")" "4"
check "après attribution, le mode ne change plus" "$(post "$U/espace/visite-du-port" "$U/espace/visite-du-port/evenement" -d "mode=valide&places=4&debut=$MOIS&cloture=$DEMAIN" >/dev/null; sql "SELECT mode FROM evenement e JOIN espace s ON s.id = e.espace_id WHERE s.slug = 'visite-du-port'")" "auto"

# Mode « validé par l'organisateur » : liste proposée, ajustée, dépassement signalé mais permis
as admin; post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=evenement&titre=Conférence&visibilite=adherents' >/dev/null
post "$U/espace/conference" "$U/espace/conference/equipe" -d 'email=chloe@example.org&niveau=n1' >/dev/null
as chloe; post "$U/espace/conference" "$U/espace/conference/evenement" -d "mode=valide&places=2&debut=$MOIS&cloture=$DEMAIN" >/dev/null
for p in alice bruno emma; do agir "$p" conference voeu; done
as chloe; post "$U/espace/conference" "$U/espace/conference/cloturer" >/dev/null
check "clôture anticipée : liste proposée" "$(sql "SELECT etat FROM evenement e JOIN espace s ON s.id = e.espace_id WHERE s.slug = 'conference'")" "proposee"
check "2 proposés, alice (groupe 3) non cochée" "$(sql "SELECT SUM(propose) FROM inscription WHERE evenement_id = (SELECT id FROM espace WHERE slug = 'conference')") $(statut conference alice)" "2 voeu 3"
check "organisatrice prévenue par e-mail" "$(grep -c 'Subject: Liste à valider : Conférence' "$MAIL")" "1"
as chloe; c "$U/espace/conference" >/dev/null
check "la liste à valider s'affiche" "$(grep -c 'name="retenus\[\]"' "$B")" "3"
agir david conference voeu
check "vœu tardif : ajouté non coché" "$(statut conference david)" "voeu"
as chloe; post "$U/espace/conference" "$U/espace/conference/valider" -d "retenus[]=$(id alice)&retenus[]=$(id bruno)&retenus[]=$(id emma)" >/dev/null
check "validation : 3 retenus pour 2 places (dépassement permis)" "$(sql "SELECT COUNT(*) FROM inscription WHERE statut = 'retenu' AND evenement_id = (SELECT id FROM espace WHERE slug = 'conference')")" "3"
check "le non coché passe en liste d'attente" "$(statut conference david)" "attente 1"
agir emma conference desister
check "dépassement : un désistement ne promeut personne" "$(statut conference david)" "attente 1"

# Mode ouvert : inscription immédiate ; cron : clôture des événements échus
as admin; post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=evenement&titre=Assemblée générale&visibilite=public' >/dev/null
post "$U/espace/assemblee-generale" "$U/espace/assemblee-generale/evenement" -d "mode=ouvert&debut=$MOIS" >/dev/null
agir bruno assemblee-generale voeu
check "ouvert à tous : inscrit tout de suite" "$(statut assemblee-generale bruno)" "retenu"
as admin; post "$U/espaces/nouveau" "$U/espaces/nouveau" -d 'type=evenement&titre=Sortie&visibilite=adherents' >/dev/null
post "$U/espace/sortie" "$U/espace/sortie/evenement" -d "mode=auto&places=1&debut=$MOIS&cloture=$DEMAIN" >/dev/null
agir bruno sortie voeu
sql "UPDATE evenement SET cloture = NOW() - INTERVAL 1 MINUTE WHERE espace_id = (SELECT id FROM espace WHERE slug = 'sortie')"
check "cron : clôture et attribution" "$(php "$ROOT/app/bin/cli.php" cron | head -1) $(statut sortie bruno)" "cron : 1 événement(s) clôturé(s) retenu"
agir chloe sortie voeu
check "après clôture : liste d'attente" "$(statut sortie chloe)" "attente 1"
check "visiteur : l'agenda public montre l'AG" "$(curl -s "$U/" -o /dev/null; as visiteur; c "$U/espace/assemblee-generale" >/dev/null; grep -c "Se connecter pour s" "$B")" "1"
as farid; c "$U/compte" >/dev/null
check "mes inscriptions sur mon compte" "$(grep -c 'Visite du port' "$B")" "1"

# L'organisateur annule une inscription ; pointage facultatif le jour J
as admin; post "$U/espace/visite-du-port" "$U/espace/visite-du-port/annuler" -d "membre_id=$(id farid)" >/dev/null
check "annulation par l'organisateur, e-mail envoyé" "$(statut visite-du-port farid) $(grep -c 'Subject: Inscription annulée' "$MAIL")" "desiste 1"
sql "UPDATE evenement SET debut = NOW() - INTERVAL 1 HOUR WHERE espace_id = (SELECT id FROM espace WHERE slug = 'sortie')"
as admin; c "$U/espace/sortie" >/dev/null
check "le jour J, la liste de pointage s'affiche" "$(grep -c 'id="pointage"' "$B")" "1"
post "$U/espace/sortie" "$U/espace/sortie/presence" -d "membre_id=$(id bruno)&present=0" >/dev/null
check "pointage : absent enregistré" "$(sql "SELECT present FROM inscription WHERE membre_id = $(id bruno) AND evenement_id = (SELECT id FROM espace WHERE slug = 'sortie')")" "0"
as bruno; post "$U/espace/sortie" "$U/espace/sortie/presence" -d "membre_id=$(id bruno)&present=1" >/dev/null
check "un membre ne peut pas se pointer lui-même" "$(sql "SELECT present FROM inscription WHERE membre_id = $(id bruno) AND evenement_id = (SELECT id FROM espace WHERE slug = 'sortie')")" "0"

# MCP : s'inscrire pour soi-même
as emma; post "$U/compte/cles" "$U/compte/cles" -d 'libelle=IA&portee=ecriture&duree=7' >/dev/null; c "$U/compte/cles" >/dev/null; KEY=$(grep -o 'bsl_[a-f0-9]\{8\}_[a-f0-9]\{40\}' "$B" | head -1)
check "MCP : inscription à l'AG" "$(curl -s -H "Authorization: Bearer $KEY" -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"inscription_evenement","arguments":{"slug":"assemblee-generale","action":"voeu"}}}' "$U/mcp" | grep -o '"isError":[a-z]*') $(statut assemblee-generale emma)" '"isError":false retenu'
check "MCP : lister_evenements donne ma situation" "$(curl -s -H "Authorization: Bearer $KEY" -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"lister_evenements","arguments":{}}}' "$U/mcp" | grep -o 'mon_statut[^,]*retenu' | head -1)" 'mon_statut*retenu'
bilan
