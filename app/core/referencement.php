<?php
// Référencement et partage : description, adresse canonique, aperçu (Open Graph), données structurées, indexation.
// Règle : rien de réservé ni d'interne n'est proposé aux moteurs ; en construction, rien du tout.
declare(strict_types=1);

const SEO_DESCRIPTION_MAX = 160;

// Pages qu'un moteur peut indexer (le reste : compte, admin, connexion, espaces internes, API)
function seo_indexable(string $path): bool
{
    return $path === '/' || str_starts_with($path, '/page/') || str_starts_with($path, '/espace/');
}

// Texte court tiré du premier paragraphe d'une page à blocs
function seo_description_auto(?string $blocs): string
{
    $normalise = $blocs !== null ? blocs_normaliser($blocs) : null;
    foreach ($normalise !== null ? json_decode($normalise, true)['blocks'] : [] as $bloc) {
        if ($bloc['type'] === 'paragraph' && trim(strip_tags($bloc['data']['text'])) !== '') {
            return seo_couper($bloc['data']['text']);
        }
    }
    return '';
}

function seo_couper(string $html): string
{
    $texte = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (mb_strlen($texte) <= SEO_DESCRIPTION_MAX) {
        return $texte;
    }
    $coupe = mb_substr($texte, 0, SEO_DESCRIPTION_MAX - 1);
    $espace = mb_strrpos($coupe, ' ');
    return rtrim($espace !== false && $espace > 100 ? mb_substr($coupe, 0, $espace) : $coupe, ' ,;:.') . '…';
}

// Première image d'une page à blocs (aperçu de partage)
function seo_image_auto(?string $blocs): ?string
{
    $normalise = $blocs !== null ? blocs_normaliser($blocs) : null;
    foreach ($normalise !== null ? json_decode($normalise, true)['blocks'] : [] as $bloc) {
        if ($bloc['type'] === 'image') {
            return $bloc['data']['chemin'];
        }
    }
    return null;
}

// Balises de l'en-tête. $seo : description, image, type (website, article), jsonld (tableau), index (bool)
function seo_balises(string $titre_complet, array $seo): string
{
    $path = request_path();
    $index = !construction_active() && ($seo['index'] ?? seo_indexable($path));
    $html = '';
    if (!$index) {
        return '<meta name="robots" content="noindex, nofollow">' . "\n";
    }
    $nom = (string) setting('nom_association', 'Boussole');
    $description = trim((string) ($seo['description'] ?? ''));
    if ($description === '') {
        $description = trim((string) setting('slogan', ''));
    }
    $image = $seo['image'] ?? (setting('bandeau_image', '') ?: setting('logo', '')) ?: null;
    $canonique = absolute_url($path === '/' ? '' : $path);
    $html .= '<link rel="canonical" href="' . e($canonique) . '">' . "\n";
    if ($description !== '') {
        $html .= '<meta name="description" content="' . e($description) . '">' . "\n";
    }
    $og = ['og:site_name' => $nom, 'og:locale' => 'fr_FR', 'og:type' => $seo['type'] ?? 'website', 'og:title' => $titre_complet,
        'og:url' => $canonique, 'og:description' => $description, 'og:image' => $image !== null ? absolute_url($image) : ''];
    foreach ($og as $propriete => $valeur) {
        if ($valeur !== '') {
            $html .= '<meta property="' . $propriete . '" content="' . e($valeur) . '">' . "\n";
        }
    }
    $taille = $image !== null ? db_one('SELECT largeur, hauteur FROM image WHERE chemin = ?', [$image]) : null;
    if ($taille !== null) {
        $html .= '<meta property="og:image:width" content="' . (int) $taille['largeur'] . '">' . "\n" . '<meta property="og:image:height" content="' . (int) $taille['hauteur'] . '">' . "\n";
    }
    $html .= '<meta name="twitter:card" content="' . ($image !== null ? 'summary_large_image' : 'summary') . '">' . "\n";
    if (!empty($seo['jsonld'])) {
        // JSON dans une balise script : on neutralise « </ » pour ne jamais la refermer
        $json = str_replace('</', '<\/', json_encode(['@context' => 'https://schema.org'] + $seo['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $html .= '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }
    return $html;
}

// L'association (accueil)
function seo_jsonld_association(): array
{
    $logo = setting('logo', '');
    return array_filter([
        '@type' => 'NGO',
        'name' => (string) setting('nom_association', 'Boussole'),
        'url' => absolute_url(''),
        'description' => (string) setting('slogan', ''),
        'logo' => $logo ? absolute_url($logo) : null,
    ]);
}

// Un événement public (le lieu seulement s'il est renseigné)
function seo_jsonld_evenement(array $espace, array $ev): ?array
{
    if ($espace['visibilite'] !== 'public' || $ev['debut'] === null) {
        return null;
    }
    return array_filter([
        '@type' => 'Event',
        'name' => $espace['titre'],
        'startDate' => date('c', strtotime($ev['debut'])),
        'description' => seo_couper($espace['description']),
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => $ev['lieu'] !== '' ? ['@type' => 'Place', 'name' => $ev['lieu'], 'address' => $ev['lieu']] : null,
        'organizer' => ['@type' => 'Organization', 'name' => (string) setting('nom_association', 'Boussole'), 'url' => absolute_url('')],
        'url' => absolute_url('/espace/' . $espace['slug']),
    ]);
}

// robots.txt : tout fermé en construction ; sinon l'interne est exclu et le plan du site annoncé
function seo_robots(): string
{
    if (construction_active()) {
        return "User-agent: *\nDisallow: /\n";
    }
    $lignes = ['User-agent: *'];
    foreach (['/compte', '/admin', '/connexion', '/deconnexion', '/espaces', '/cotisations', '/fichier/', '/api/', '/mcp', '/acces', '/evenement/', '/app/'] as $interne) {
        $lignes[] = "Disallow: $interne";
    }
    $lignes[] = 'Sitemap: ' . absolute_url('sitemap.xml');
    return implode("\n", $lignes) . "\n";
}

// sitemap.xml : accueil, pages publiées non réservées, événements publics non archivés
function seo_sitemap(): string
{
    $urls = [[absolute_url(''), null]];
    foreach (db_all('SELECT slug, modifiee_le FROM page WHERE publiee = 1 AND reservee_membres = 0 AND est_accueil = 0 ORDER BY ordre, titre') as $p) {
        $urls[] = [absolute_url('/page/' . $p['slug']), $p['modifiee_le']];
    }
    foreach (db_all('SELECT slug, modifie_le FROM espace WHERE visibilite = "public" AND archive = 0 AND type = "evenement" ORDER BY titre') as $s) {
        $urls[] = [absolute_url('/espace/' . $s['slug']), $s['modifie_le']];
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as [$loc, $modif]) {
        $xml .= '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . ($modif ? '<lastmod>' . date('Y-m-d', strtotime($modif)) . '</lastmod>' : '') . "</url>\n";
    }
    return $xml . "</urlset>\n";
}
