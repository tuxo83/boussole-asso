<?php
// Contenu en blocs (éditeur à blocs) : validé à l'enregistrement, ré-échappé à l'affichage.
// Le seul HTML accepté est la mise en forme en ligne : gras, italique, lien, retour à la ligne.
declare(strict_types=1);

const BLOCS_MAX = 500;
const BLOCS_DYNAMIQUES = [
    'evenements' => 'Liste des événements publics',
    'espaces-publics' => 'Liste des espaces publics',
    'espace-membres' => 'Bouton « Espace membres »',
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
                $out .= '<a href="' . e($href) . '"' . ($externe ? ' rel="noopener noreferrer nofollow"' : '') . ">$inner</a>";
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
        default => null,
    };
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
function blocs_rendre(?string $json): string
{
    $normalise = $json !== null ? blocs_normaliser($json) : null;
    if ($normalise === null) {
        return '';
    }
    $html = '';
    foreach (json_decode($normalise, true)['blocks'] as $block) {
        $d = $block['data'];
        $html .= match ($block['type']) {
            'paragraph' => $d['text'] !== '' ? "<p>{$d['text']}</p>" : '',
            'header' => "<h{$d['level']}>{$d['text']}</h{$d['level']}>",
            'list' => bloc_liste_html($d['items'], $d['style']),
            'quote' => '<blockquote><p>' . $d['text'] . '</p>' . ($d['caption'] !== '' ? '<footer>' . $d['caption'] . '</footer>' : '') . '</blockquote>',
            'delimiter' => '<hr>',
            'image' => '<figure><img src="' . e(url($d['chemin'])) . '" alt="' . e($d['alt']) . '" loading="lazy">'
                . ($d['legende'] !== '' ? '<figcaption>' . e($d['legende']) . '</figcaption>' : '') . '</figure>',
            'boussole' => bloc_dynamique($d['bloc']),
            default => '',
        };
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
            evenements_cloturer_echus();
            $evenements = db_all('SELECT s.*, e.debut, e.lieu FROM espace s LEFT JOIN evenement e ON e.espace_id = s.id
                WHERE s.type = "evenement" AND s.visibilite = "public" AND s.archive = 0 AND (e.debut IS NULL OR e.debut >= CURDATE()) ORDER BY e.debut IS NULL, e.debut');
            if (!$evenements) {
                return '<p class="discret">Aucun événement à venir pour l\'instant.</p>';
            }
            $html = '<div class="grille">';
            foreach ($evenements as $ev) {
                $html .= c_carte($ev['titre'], '<p><strong>' . e(evenement_date($ev['debut'])) . '</strong>' . ($ev['lieu'] ? ' · ' . e($ev['lieu']) : '') . '</p><p>' . e(mb_strimwidth($ev['description'], 0, 160, '…')) . '</p>', '/espace/' . $ev['slug']);
            }
            return $html . '</div>';
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
        case 'espace-membres':
            return '<p>' . c_bouton(current_member() !== null ? 'Mon espace' : 'Espace membres', current_member() !== null ? '/compte' : '/connexion') . '</p>';
    }
    return '';
}
