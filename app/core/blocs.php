<?php
// Contenu en blocs (éditeur à blocs) : validé à l'enregistrement, ré-échappé à l'affichage.
// Le seul HTML accepté est la mise en forme en ligne : gras, italique, lien, retour à la ligne.
declare(strict_types=1);

const BLOCS_MAX = 500;
const BLOCS_DYNAMIQUES = [
    'evenements' => 'Les 3 prochains événements',
    'espaces-publics' => 'Liste des espaces publics',
    'espace-membres' => 'Bouton « Espace membres »',
    'adhesion' => 'Bouton « Adhérer » (lien HelloAsso des réglages)',
    'agenda' => 'Agenda complet (à venir et passés)',
    'contact' => 'Formulaire de contact',
];

// Mise en forme en ligne : on reconstruit le HTML à partir d'une liste blanche, jamais de copie brute
function bloc_inline(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    // Hébergeur sans l'extension DOM : texte seul, sans mise en forme (sûr)
    if (!class_exists(DOMDocument::class)) {
        return e(html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    // Un « < » qui n'ouvre pas une balise est du texte (« 3 < 5 ») : les anciennes versions de libxml le perdraient
    $html = preg_replace('/<(?![a-zA-Z\/!])/', '&lt;', $html);
    $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $doc->getElementsByTagName('div')->item(0);
    return $root ? bloc_inline_nodes($root, 0) : '';
}

function bloc_inline_nodes(DOMNode $node, int $depth): string
{
    $out = '';
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMText) {
            $out .= e($child->textContent);
            continue;
        }
        if (!$child instanceof DOMElement || $depth > 6) {
            continue;
        }
        $tag = strtolower($child->tagName);
        // Contenu jamais affiché, même comme texte
        if (in_array($tag, ['script', 'style', 'noscript', 'template', 'iframe', 'object', 'svg', 'math'], true)) {
            continue;
        }
        $inner = bloc_inline_nodes($child, $depth + 1);
        switch ($tag) {
            case 'b':
            case 'strong':
                $out .= "<strong>$inner</strong>";
                break;
            case 'i':
            case 'em':
                $out .= "<em>$inner</em>";
                break;
            case 'br':
                $out .= '<br>';
                break;
            case 'a':
                $href = bloc_lien_sur(trim($child->getAttribute('href')));
                if ($href === null) {
                    $out .= $inner;
                    break;
                }
                $externe = (bool) preg_match('#^https?://#i', $href);
                $out .= '<a href="' . e($href) . '"' . ($externe ? ' target="_blank" rel="noopener noreferrer nofollow"' : '') . ">$inner</a>";
                break;
            default:
                // Balise inconnue : on garde le texte, pas la balise
                $out .= $inner;
        }
    }
    return $out;
}

// Liens autorisés : http(s), mailto, tel, ou chemin du site ; jamais javascript:, data:…
function bloc_lien_sur(string $href): ?string
{
    if (preg_match('#^(https?://|mailto:|tel:)#i', $href) || (str_starts_with($href, '/') && !str_starts_with($href, '//'))) {
        return $href;
    }
    return null;
}

// Validation à l'enregistrement : seuls les types connus et leurs champs utiles sont gardés
function blocs_normaliser(string $json): ?string
{
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['blocks']) || !is_array($data['blocks']) || count($data['blocks']) > BLOCS_MAX) {
        return null;
    }
    $blocks = [];
    foreach ($data['blocks'] as $block) {
        $clean = bloc_normaliser(is_array($block) ? $block : []);
        if ($clean !== null) {
            $blocks[] = $clean;
        }
    }
    return json_encode(['blocks' => $blocks], JSON_UNESCAPED_UNICODE);
}

function bloc_normaliser(array $block): ?array
{
    $d = is_array($block['data'] ?? null) ? $block['data'] : [];
    $text = fn(string $key, int $max = 20000) => mb_substr(bloc_inline((string) ($d[$key] ?? '')), 0, $max);
    return match ($block['type'] ?? '') {
        'paragraph' => ['type' => 'paragraph', 'data' => ['text' => $text('text')]],
        'header' => ['type' => 'header', 'data' => ['text' => $text('text', 500), 'level' => max(2, min(4, (int) ($d['level'] ?? 2)))]],
        'list' => ['type' => 'list', 'data' => ['style' => ($d['style'] ?? '') === 'ordered' ? 'ordered' : 'unordered', 'items' => bloc_liste_items($d['items'] ?? [], 0)]],
        'quote' => ['type' => 'quote', 'data' => ['text' => $text('text'), 'caption' => $text('caption', 500)]],
        'delimiter' => ['type' => 'delimiter', 'data' => []],
        'image' => preg_match('#^uploads/img-[a-f0-9]{24}\.(webp|png|jpg)$#', (string) ($d['chemin'] ?? ''))
            ? ['type' => 'image', 'data' => ['chemin' => $d['chemin'], 'legende' => mb_substr(trim(strip_tags((string) ($d['legende'] ?? ''))), 0, 300), 'alt' => mb_substr(trim(strip_tags((string) ($d['alt'] ?? ''))), 0, 300)]]
            : null,
        'boussole' => isset(BLOCS_DYNAMIQUES[$d['bloc'] ?? '']) ? ['type' => 'boussole', 'data' => ['bloc' => $d['bloc']]] : null,
        'cartes' => bloc_cartes($d),
        'chiffres' => bloc_chiffres($d),
        'frise' => bloc_frise($d),
        'bouton' => bloc_bouton($d),
        default => null,
    };
}

// Texte simple (sans mise en forme) d'un champ
function bloc_texte_simple(mixed $value, int $max): string
{
    return mb_substr(trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, $max);
}

// Lien d'un bouton ou d'une carte : adresse sûre, ou « adhesion » (le lien d'adhésion des réglages)
function bloc_lien_bouton(mixed $value): string
{
    $lien = trim((string) $value);
    return $lien === 'adhesion' ? 'adhesion' : (bloc_lien_sur(mb_substr($lien, 0, 300)) ?? '');
}

// Cartes : icône, titre, texte court, lien (12 au plus)
function bloc_cartes(array $d): ?array
{
    $items = [];
    foreach (array_slice(is_array($d['items'] ?? null) ? $d['items'] : [], 0, 12) as $item) {
        $titre = bloc_texte_simple($item['titre'] ?? '', 80);
        if ($titre === '') {
            continue;
        }
        $items[] = ['icone' => isset(ICONES[$item['icone'] ?? '']) ? $item['icone'] : '', 'titre' => $titre,
            'texte' => mb_substr(bloc_inline((string) ($item['texte'] ?? '')), 0, 600), 'lien' => bloc_lien_bouton($item['lien'] ?? '')];
    }
    return $items ? ['type' => 'cartes', 'data' => ['items' => $items]] : null;
}

// Frise chronologique : date courte (année, mois…) et texte d'une ou deux lignes, 20 étapes au plus
function bloc_frise(array $d): ?array
{
    $items = [];
    foreach (array_slice(is_array($d['items'] ?? null) ? $d['items'] : [], 0, 20) as $item) {
        $date = bloc_texte_simple($item['date'] ?? '', 20);
        $texte = mb_substr(bloc_inline((string) ($item['texte'] ?? '')), 0, 400);
        if ($date !== '' && $texte !== '') {
            $items[] = ['date' => $date, 'texte' => $texte];
        }
    }
    return $items ? ['type' => 'frise', 'data' => ['items' => $items]] : null;
}

// Chiffres clés : valeur et libellé (6 au plus)
function bloc_chiffres(array $d): ?array
{
    $items = [];
    foreach (array_slice(is_array($d['items'] ?? null) ? $d['items'] : [], 0, 6) as $item) {
        $valeur = bloc_texte_simple($item['valeur'] ?? '', 12);
        if ($valeur !== '') {
            $items[] = ['valeur' => $valeur, 'libelle' => bloc_texte_simple($item['libelle'] ?? '', 80)];
        }
    }
    return $items ? ['type' => 'chiffres', 'data' => ['items' => $items]] : null;
}

function bloc_bouton(array $d): ?array
{
    $texte = bloc_texte_simple($d['texte'] ?? '', 60);
    $lien = bloc_lien_bouton($d['lien'] ?? '');
    return $texte !== '' && $lien !== '' ? ['type' => 'bouton', 'data' => ['texte' => $texte, 'lien' => $lien, 'style' => ($d['style'] ?? '') === 'secondaire' ? 'secondaire' : 'principal']] : null;
}

function bloc_lien_resolu(string $lien): string
{
    return $lien === 'adhesion' ? (string) setting('lien_adhesion', '') : $lien;
}

// Listes : éléments texte ou objets { content, items } (listes imbriquées), 3 niveaux au plus
function bloc_liste_items(mixed $items, int $depth): array
{
    $out = [];
    foreach (is_array($items) ? array_slice($items, 0, 200) : [] as $item) {
        $content = is_array($item) ? (string) ($item['content'] ?? '') : (string) $item;
        $children = is_array($item) && $depth < 2 ? bloc_liste_items($item['items'] ?? [], $depth + 1) : [];
        $out[] = ['content' => mb_substr(bloc_inline($content), 0, 5000), 'items' => $children];
    }
    return $out;
}

// Affichage : le contenu est normalisé une seconde fois (défense en profondeur)
// $edition : adresse de l'éditeur de la page ; chaque bloc devient cliquable et mène à son endroit dans l'éditeur
function blocs_rendre(?string $json, ?string $edition = null): string
{
    $normalise = $json !== null ? blocs_normaliser($json) : null;
    if ($normalise === null) {
        return '';
    }
    $html = '';
    foreach (json_decode($normalise, true)['blocks'] as $n => $block) {
        $d = $block['data'];
        $morceau = match ($block['type']) {
            'paragraph' => $d['text'] !== '' ? "<p>{$d['text']}</p>" : '',
            'header' => "<h{$d['level']}>{$d['text']}</h{$d['level']}>",
            'list' => bloc_liste_html($d['items'], $d['style']),
            'quote' => '<blockquote><p>' . $d['text'] . '</p>' . ($d['caption'] !== '' ? '<footer>' . $d['caption'] . '</footer>' : '') . '</blockquote>',
            'delimiter' => '<hr>',
            'image' => '<figure><img src="' . e(url($d['chemin'])) . '" alt="' . e($d['alt']) . '" loading="lazy">'
                . ($d['legende'] !== '' ? '<figcaption>' . e($d['legende']) . '</figcaption>' : '') . '</figure>',
            'boussole' => bloc_dynamique($d['bloc']),
            'cartes' => '<div class="cartes">' . implode('', array_map(function ($c) {
                $lien = bloc_lien_resolu($c['lien']);
                $titre = $lien !== '' ? '<a href="' . e(str_starts_with($lien, '/') ? url($lien) : $lien) . '"' . (preg_match('#^https?://#', $lien) ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . e($c['titre']) . '</a>' : e($c['titre']);
                return '<article class="carte carte-action">' . ($c['icone'] !== '' ? '<span class="carte-icone">' . c_icone($c['icone']) . '</span>' : '')
                    . '<h3>' . $titre . '</h3>' . ($c['texte'] !== '' ? '<p>' . $c['texte'] . '</p>' : '') . '</article>';
            }, $d['items'])) . '</div>',
            'frise' => '<ol class="chronologie">' . implode('', array_map(fn($f) => '<li><strong class="chrono-date">' . e($f['date']) . '</strong><p>' . $f['texte'] . '</p></li>', $d['items'])) . '</ol>',
            'chiffres' => '<div class="chiffres">' . implode('', array_map(fn($c) => '<div><strong>' . e($c['valeur']) . '</strong><span>' . e($c['libelle']) . '</span></div>', $d['items'])) . '</div>',
            'bouton' => ($lien = bloc_lien_resolu($d['lien'])) !== '' ? '<p class="bloc-bouton"><a class="bouton bouton-' . $d['style'] . '" href="' . e(str_starts_with($lien, '/') ? url($lien) : $lien) . '"' . (preg_match('#^https?://#', $lien) ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . e($d['texte']) . '</a></p>' : '',
            default => '',
        };
        $html .= $edition !== null && $morceau !== ''
            ? '<div class="bloc-editable" data-bloc="' . $n . '"><a class="bloc-crayon" href="' . e($edition . '#bloc-' . $n) . '">' . c_icone('crayon') . 'Modifier</a>' . $morceau . '</div>'
            : $morceau;
    }
    return $html;
}

function bloc_liste_html(array $items, string $style): string
{
    $tag = $style === 'ordered' ? 'ol' : 'ul';
    $html = "<$tag>";
    foreach ($items as $item) {
        $html .= '<li>' . $item['content'] . ($item['items'] ? bloc_liste_html($item['items'], $style) : '') . '</li>';
    }
    return $html . "</$tag>";
}

// Blocs dynamiques : uniquement des contenus publics, quel que soit le visiteur
function bloc_dynamique(string $bloc): string
{
    switch ($bloc) {
        case 'evenements':
            // Accueil : les 3 prochains, et le lien vers l'agenda complet
            $prochains = evenements_visibles(true);
            if (!$prochains) {
                return '';
            }
            $agenda = page_agenda_slug();
            // Lien discret à côté du titre plutôt qu'un bouton de plus (Élise)
            return '<div class="titre-avec-lien"><h2 class="titre-bloc">Prochains événements</h2>'
                . ($agenda ? '<a href="' . e(url('/page/' . $agenda)) . '">Tout l\'agenda →</a>' : '') . '</div>' . cartes_evenements(array_slice($prochains, 0, 3));
        case 'contact':
            // L'adresse de l'association n'apparaît pas : le message passe par le site (Administration → Messages reçus)
            $moi = current_member();
            return '<form method="post" action="' . e(url('/contact')) . '" class="formulaire formulaire-contact">' . csrf_field() . humain_champs()
                . '<input type="hidden" name="retour" value="' . e(request_path()) . '">'
                . c_champ('nom', 'Votre nom', ['requis' => true, 'max' => 100, 'valeur' => $moi ? membre_nom($moi) : ''])
                . c_champ('email', 'Votre adresse e-mail', ['type' => 'email', 'requis' => true, 'valeur' => $moi['email'] ?? '', 'aide' => 'Pour vous répondre.'])
                . c_champ('objet', 'Objet', ['max' => 200])
                . c_champ('message', 'Votre message', ['type' => 'textarea', 'requis' => true, 'max' => 5000])
                . '<p class="petit">Vos coordonnées servent seulement à vous répondre (<a href="' . e(url('/page/confidentialite')) . '">confidentialité</a>).</p>'
                . '<button type="submit" class="bouton bouton-principal">Envoyer</button></form>';
        case 'agenda':
            // Agenda complet : tout ce qui vient, puis ce qui a eu lieu sur 12 mois
            $prochains = evenements_visibles(true);
            $passes = evenements_visibles(false);
            // Visiteur seulement : comment s'inscrire (un membre connecté n'a pas besoin de ce rappel)
            $aide = current_member() === null ? '<p class="agenda-visiteur">' . c_icone('cadenas') . ' Membres : <a href="' . e(url('/connexion')) . '">connectez-vous</a> pour voir les événements réservés et vous inscrire.'
                . (setting('lien_adhesion', '') !== '' ? ' Pas encore membre ? <a href="' . e(url('/page/adherer')) . '">Adhérer</a>.' : '') . '</p>' : '';
            // Membre connecté : ses inscriptions à venir d'abord (elles restent aussi dans la liste complète, avec leur statut)
            $miens = [];
            if (($moi = current_member()) !== null) {
                $statuts = array_column(db_all('SELECT evenement_id, statut, rang FROM inscription WHERE membre_id = ? AND statut IN ("invite", "voeu", "retenu", "attente")', [$moi['id']]), null, 'evenement_id');
                foreach ($prochains as $k => $ev) {
                    if (isset($statuts[$ev['id']])) {
                        $prochains[$k]['mon_statut'] = $statuts[$ev['id']];
                        $miens[] = $prochains[$k];
                    }
                }
            }
            return $aide . ($miens ? '<section class="agenda-miens"><h2 class="titre-bloc">Mes inscriptions</h2>' . cartes_evenements($miens) . '</section>' : '')
                . ($prochains ? '<h2 class="titre-bloc">À venir</h2>' . cartes_evenements($prochains) : '<p>Aucun événement annoncé pour l\'instant.</p>')
                . ($passes ? '<h2 class="titre-bloc">Événements passés</h2>' . cartes_evenements($passes, true) : '');
        case 'espaces-publics':
            $espaces = db_all('SELECT * FROM espace WHERE visibilite = "public" AND archive = 0 AND type <> "evenement" ORDER BY titre');
            if (!$espaces) {
                return '<p class="discret">Rien à afficher pour l\'instant.</p>';
            }
            $html = '<div class="grille">';
            foreach ($espaces as $espace) {
                $html .= c_carte($espace['titre'], '<p>' . e(mb_strimwidth($espace['description'], 0, 200, '…')) . '</p>', '/espace/' . $espace['slug']);
            }
            return $html . '</div>';
        case 'adhesion':
            $lien = (string) setting('lien_adhesion', '');
            return $lien !== '' ? '<p><a class="bouton bouton-principal" href="' . e($lien) . '" target="_blank" rel="noopener noreferrer">Adhérer ou renouveler sur HelloAsso</a></p>' : '';
        case 'espace-membres':
            return '<p>' . c_bouton(current_member() !== null ? 'Mon espace' : 'Espace membres', current_member() !== null ? '/compte' : '/connexion') . '</p>';
    }
    return '';
}

// Carte d'événement : vignette de date, bandeau d'accès (membres, cotisants), contenu
function carte_evenement(array $ev, string $titre, string $lien, string $contenu, bool $verrouille): string
{
    $mois = ['', 'janv.', 'févr.', 'mars', 'avril', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $date = $ev['debut'] !== null
        ? '<span class="jour">' . (int) date('j', strtotime($ev['debut'])) . '</span><span class="mois">' . $mois[(int) date('n', strtotime($ev['debut']))] . '</span>'
        : '<span class="date-icone" title="Date à venir">' . c_icone('calendrier') . '</span>';
    $equipe = $ev['visibilite'] === 'equipe';
    // Bandeau court (qui peut venir) ; l'espace organisateur est dit dans la carte, jamais à un visiteur pour un événement réservé
    $bandeau = $equipe ? 'Réunion d\'équipe' : ($ev['visibilite'] === 'public' ? 'Ouvert à tous' : 'Réservé aux membres');
    $organise = !$verrouille && !empty($ev['parent_titre']) ? '<p class="organise">' . e($ev['parent_titre']) . '</p>' : '';
    return '<article class="carte carte-evenement acces-' . ($equipe ? 'equipe' : ($ev['visibilite'] === 'public' ? 'public' : 'membres')) . ($verrouille ? ' carte-reservee' : '') . '">'
        . '<span class="ruban">' . e($bandeau) . '</span>'
        . '<div class="evenement-tete"><span class="date-tuile">' . $date . '</span><div class="evenement-titre"><h3><a href="' . e(url($lien)) . '">' . e($titre) . '</a></h3>' . $organise . '</div></div>'
        . ($ev['debut'] !== null ? '<p class="discret">' . e(evenement_date($ev['debut'])) . '</p>' : '') . $contenu . '</article>';
}

// Événements montrés dans l'agenda : publics et réservés aux membres (jamais ceux d'une équipe)
function evenements_visibles(bool $avenir): array
{
    evenements_cloturer_echus();
    $membre = current_member();
    // Les réunions d'équipe n'apparaissent que dans l'agenda des membres de cette équipe (ou de l'espace auquel elles sont rattachées)
    $lignes = db_all('SELECT s.*, e.debut, e.lieu, p.titre AS parent_titre FROM espace s LEFT JOIN evenement e ON e.espace_id = s.id LEFT JOIN espace p ON p.id = s.parent_id
        WHERE s.type = "evenement" AND s.visibilite IN ("public", "adherents", "cotisants"' . ($membre !== null ? ', "equipe"' : '') . ') AND s.archive = 0 AND '
        . ($avenir ? '(e.debut IS NULL OR e.debut >= CURDATE()) ORDER BY e.debut IS NULL, e.debut' : 'e.debut < CURDATE() AND e.debut >= CURDATE() - INTERVAL 12 MONTH ORDER BY e.debut DESC'));
    return array_values(array_filter($lignes, fn($ev) => match ($ev['visibilite']) {
        'public' => true,
        'equipe' => espace_niveau($membre, $ev) !== null || espace_equipe_parente($membre, $ev),
        default => $membre === null || espace_peut_voir($membre, $ev),
    }));
}

function cartes_evenements(array $evenements, bool $passes = false): string
{
    $membre = current_member();
    $html = '<div class="cartes' . ($passes ? ' cartes-passees' : '') . '">';
    foreach ($evenements as $ev) {
        if ($ev['visibilite'] !== 'public' && $membre === null) {
            // Visiteur : rien de l'événement réservé (ni titre, ni lieu, ni adresse de sa page), seulement qu'il existe et sa date
            $html .= carte_evenement($ev, 'Événement des membres', '/evenement/' . (int) $ev['id'], '<p class="petit">' . c_icone('cadenas') . ($passes ? ' Réservé aux membres.' : ' Connectez-vous pour le découvrir et vous inscrire.') . '</p>', true);
        } else {
            $html .= carte_evenement($ev, $ev['titre'], '/espace/' . $ev['slug'], ($ev['lieu'] ? '<p class="lieu">' . c_icone('lieu') . ' ' . e($ev['lieu']) . '</p>' : '')
                . (isset($ev['mon_statut']) ? '<p><span class="badge' . ($ev['mon_statut']['statut'] === 'retenu' ? ' badge-ok' : ' badge-attention') . '">' . e(ucfirst(INSCRIPTION_STATUTS[$ev['mon_statut']['statut']])) . ($ev['mon_statut']['statut'] === 'attente' ? ' n°' . (int) $ev['mon_statut']['rang'] : '') . '</span></p>' : '')
                . '<p class="resume">' . e($ev['description']) . '</p>', false);
        }
    }
    return $html . '</div>';
}

// Mode « cliquer pour modifier » des pages, pour qui peut publier : ?edition=1 l'active, ?edition=0 le coupe
function page_mode_edition(): bool
{
    $membre = current_member();
    if ($membre === null || !has_right($membre, 'publications')) {
        return false;
    }
    if (isset($_GET['edition'])) {
        $_SESSION['mode_edition'] = $_GET['edition'] === '1';
    }
    return !empty($_SESSION['mode_edition']);
}

// Pour qui peut publier : un petit crayon flottant en bas à droite, qui ne touche pas à la mise en page ;
// il déplie « Modifier cette page » et « Cliquer sur un bloc » ; en mode édition, il devient « Terminer »
function page_barre_edition(array $page, bool $actif): string
{
    $editeur = url('/admin/pages/' . (int) $page['id']);
    $ici = url(request_path());
    if ($actif) {
        return '<div class="crayon-flottant actif"><a class="crayon-bouton" href="' . e($ici . '?edition=0') . '">' . c_icone('crayon') . 'Terminer</a>'
            . '<a class="crayon-lien" href="' . e($editeur) . '">Ouvrir l\'éditeur</a></div>';
    }
    return '<details class="crayon-flottant"><summary class="crayon-bouton" aria-label="Modifier la page">' . c_icone('crayon') . '</summary>'
        . '<div class="crayon-menu"><a href="' . e($ici . '?edition=1') . '">Cliquer sur un bloc pour le modifier</a><a href="' . e($editeur) . '">Ouvrir l\'éditeur de la page</a></div></details>';
}


// Page qui porte l'agenda complet (pour y renvoyer), ou null
function page_agenda_slug(): ?string
{
    $slug = db_value('SELECT slug FROM page WHERE publiee = 1 AND blocs LIKE ? LIMIT 1', ['%"bloc":"agenda"%']);
    return $slug !== null && $slug !== false ? (string) $slug : null;
}
