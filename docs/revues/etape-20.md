# Relecture du panel : étape 20 (référencement et partage)

Relecture menée par Julien (référencement), avec Nadia (discrétion) et Thomas (sécurité), sur le contenu de l'AR20 reproduit en local.

## État de départ

Aucun plan du site, aucun `robots.txt`, aucune description ni aperçu de partage, aucune donnée structurée.

## Retours et décisions

| Personnage | Retour | Décision |
|---|---|---|
| Julien | Les moteurs ne savent ni quoi indexer ni quoi éviter | `robots.txt` : en construction tout est fermé ; une fois en ligne, compte, administration, connexion, espaces, API et fichiers sont exclus, et le plan du site est annoncé. `sitemap.xml` : accueil, pages publiées non réservées, événements publics non archivés, avec la date de mise à jour (absent en construction) |
| Julien | Pas de description : les moteurs inventent un extrait, les partages s'affichent sans texte | Champ « Description pour les moteurs » par page (éditeur et MCP). À défaut, le début du premier paragraphe, coupé proprement à 160 caractères |
| Julien | Un lien partagé (WhatsApp, LinkedIn, e-mail) s'affiche nu | Aperçu Open Graph : titre, description, image (bandeau ou première image de la page, sinon logo) avec ses dimensions, adresse canonique |
| Julien | Titre de l'accueil trop long (103 caractères) : coupé dans les résultats | Nom et accroche seulement s'ils tiennent en 65 caractères, sinon le nom seul. Pour l'AR20, une accroche plus courte ferait apparaître les mots clés dans le titre : à décider par le bureau |
| Julien | Données structurées | L'association sur l'accueil (`NGO`) ; chaque événement public daté (`Event` : nom, date, lieu, organisateur) |
| Nadia | Rien de réservé ne doit fuir par le référencement | Pages réservées, espaces internes et pages de compte : `noindex` et absents du plan du site. Un événement réservé ne produit aucune donnée structurée |
| Thomas | Données structurées injectées dans la page | JSON encodé, `</` neutralisé : impossible de refermer la balise `script` |
| Julien | Images sans texte alternatif | Seul le logo de l'en-tête en a un vide, ce qui est voulu (décoratif, le nom est écrit à côté) : rien à changer |

## Limites connues

- Les images sont enregistrées en WebP. Facebook et WhatsApp les affichent, mais LinkedIn ne les prend pas toujours en charge. À revoir si les partages LinkedIn comptent.
- La vérification Google Search Console passe déjà par le DNS du domaine de l'AR20 : rien à ajouter dans le site.

Tests : `tests/e2e-etape5.sh` (robots, plan du site, description, aperçu, données structurées, noindex) et `tests/e2e-etape11.sh` (construction : tout fermé).
