<?php
// Application installable (Android, iPhone) : manifeste, icônes tirées du logo, service minimal (page « hors ligne »).
// Rien de personnel n'est gardé sur le téléphone : seule la page « hors ligne » est mise de côté.
// Tout est sous /app, ouvert même en mode construction (le téléphone les demande sans cookie).
declare(strict_types=1);

const APPLICATION_TAILLES = [180, 192, 512];

return [
    'ordre' => 95,
    'routes' => [
        ['GET', '/app/manifest.webmanifest', function () {
            if (!application_active()) {
                application_absente();
            }
            $nom = (string) setting('nom_association', 'Boussole');
            header('Content-Type: application/manifest+json; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            $icones = [];
            foreach ([192, 512] as $taille) {
                $icones[] = ['src' => url("/app/icone-$taille.png") . '?v=' . application_version_icones(), 'sizes' => "{$taille}x$taille", 'type' => 'image/png', 'purpose' => 'any'];
            }
            $icones[] = ['src' => url('/app/icone-masque-512.png') . '?v=' . application_version_icones(), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
            echo json_encode([
                'id' => url('/'),
                'name' => $nom,
                'short_name' => application_nom_court(),
                'lang' => 'fr',
                'start_url' => url('/app/demarrer'),
                'scope' => url('/'),
                'display' => 'standalone',
                'background_color' => '#ffffff',
                'theme_color' => color_setting('couleur_principale', '#1f4e79'),
                'icons' => $icones,
                // Permet au site, ouvert dans Chrome, de savoir que l'application est déjà installée
                'related_applications' => [['platform' => 'webapp', 'url' => absolute_url('/app/manifest.webmanifest')]],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }],
        ['GET', '/app/icone-{nom}.png', function (array $p) {
            $masque = str_starts_with($p['nom'], 'masque-');
            $taille = (int) ($masque ? substr($p['nom'], 7) : $p['nom']);
            if (!application_active() || !in_array($taille, APPLICATION_TAILLES, true) || !extension_loaded('gd')) {
                application_absente();
            }
            // Fabriquée une fois par version (logo, couleur, nom), puis lue sur le disque
            $fichier = BOUSSOLE_ROOT . '/stockage/icone-' . application_version_icones() . '-' . ($masque ? 'masque-' : '') . $taille . '.png';
            if (!is_file($fichier)) {
                foreach (glob(BOUSSOLE_ROOT . '/stockage/icone-*.png') ?: [] as $ancienne) {
                    if (!str_contains($ancienne, application_version_icones())) {
                        @unlink($ancienne);
                    }
                }
                imagepng(application_icone($taille, $masque), $fichier . '.tmp', 6);
                @rename($fichier . '.tmp', $fichier);
            }
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=604800');
            header('Content-Length: ' . filesize($fichier));
            readfile($fichier);
        }],
        // Ouverture de l'application : la page d'arrivée réglée pour les membres (« Après la connexion »), sinon la connexion
        ['GET', '/app/demarrer', function () {
            $member = current_member();
            redirect($member !== null ? connexion_arrivee($member) : '/connexion');
        }],
        ['GET', '/app/sw.js', function () {
            header('Content-Type: text/javascript; charset=utf-8');
            header('Cache-Control: no-cache');
            header('Service-Worker-Allowed: ' . url('/'));
            // Application coupée : le service déjà installé sur les téléphones se retire de lui-même
            if (!application_active()) {
                echo "self.addEventListener('install', () => self.skipWaiting());\n"
                    . "self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.map(n => caches.delete(n)))).then(() => self.registration.unregister())); });\n";
                return;
            }
            $hors_ligne = json_encode(url('/app/hors-ligne'));
            $cache = json_encode('boussole-' . application_version_icones());
            echo <<<JS
            // Service minimal : si le réseau manque à l'ouverture d'une page, la page « hors ligne » s'affiche.
            // Aucune autre page n'est gardée sur l'appareil.
            const CACHE = $cache, HORS_LIGNE = $hors_ligne;
            self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.add(HORS_LIGNE))); self.skipWaiting(); });
            self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.filter(n => n !== CACHE).map(n => caches.delete(n)))).then(() => self.clients.claim())); });
            self.addEventListener('fetch', e => {
              if (e.request.mode !== 'navigate') return;
              e.respondWith(fetch(e.request).catch(() => caches.match(HORS_LIGNE)));
            });
            JS;
        }],
        ['GET', '/app/hors-ligne', function () {
            $nom = e((string) setting('nom_association', 'Boussole'));
            $couleur = color_setting('couleur_principale', '#1f4e79');
            header('Content-Type: text/html; charset=utf-8');
            echo <<<HTML
            <!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Hors ligne · $nom</title>
            <style>body{font-family:system-ui,sans-serif;margin:0;min-height:100vh;display:grid;place-items:center;background:#fff;color:#1b2430;text-align:center;padding:1.5rem;box-sizing:border-box}
            h1{font-size:1.4rem;color:$couleur}button{font:inherit;font-weight:600;background:$couleur;color:#fff;border:0;border-radius:999px;padding:.8rem 1.6rem;min-height:44px}</style></head>
            <body><main><h1>Pas de connexion</h1><p>$nom n'est pas joignable pour l'instant.<br>Vérifiez le réseau, puis réessayez.</p><button onclick="location.reload()">Réessayer</button></main></body></html>
            HTML;
        }],
    ],
];

function application_active(): bool
{
    return setting('application_active', '1') === '1';
}

function application_absente(): never
{
    http_response_code(404);
    exit;
}

// Nom sous l'icône : réglage, sinon le début du nom (avant « – ») ; 12 caractères tiennent sous une icône
function application_nom_court(): string
{
    $court = trim((string) setting('application_nom_court', ''));
    if ($court === '') {
        $court = trim(preg_split('/\s+[–-]\s+/u', (string) setting('nom_association', 'Boussole'))[0]);
    }
    return mb_substr($court, 0, 30);
}

// Change quand le logo ou la couleur change : le téléphone reprend les nouvelles icônes
function application_version_icones(): string
{
    return substr(md5((string) setting('logo', '') . '|' . color_setting('couleur_principale', '#1f4e79') . '|' . application_nom_court()), 0, 8);
}

// Icône carrée : le logo sur fond blanc (marge plus grande pour l'icône « masquable » qu'Android découpe en rond),
// sinon les initiales sur la couleur principale
function application_icone(int $taille, bool $masque): GdImage
{
    $image = imagecreatetruecolor($taille, $taille);
    $logo_chemin = (string) setting('logo', '');
    // Logo d'une taille raisonnable seulement (une image immense épuiserait la mémoire) ; sinon les initiales
    $info = $logo_chemin !== '' && is_file(BOUSSOLE_ROOT . '/' . $logo_chemin) ? @getimagesize(BOUSSOLE_ROOT . '/' . $logo_chemin) : false;
    $logo = $info !== false && $info[0] <= LOGO_COTE_MAX && $info[1] <= LOGO_COTE_MAX ? @imagecreatefromstring((string) file_get_contents(BOUSSOLE_ROOT . '/' . $logo_chemin)) : false;
    [$r, $g, $b] = sscanf(color_setting('couleur_principale', '#1f4e79'), '#%02x%02x%02x');
    if ($logo !== false) {
        // Logo clair (fait pour un bandeau foncé) : sur la couleur principale ; logo foncé : sur fond blanc
        $fond = application_logo_clair($logo) ? imagecolorallocate($image, $r, $g, $b) : imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $fond);
        $zone = (int) round($taille * ($masque ? 0.62 : 0.84));
        [$w, $h] = [imagesx($logo), imagesy($logo)];
        $ratio = min($zone / $w, $zone / $h);
        [$lw, $lh] = [max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio))];
        imagecopyresampled($image, $logo, intdiv($taille - $lw, 2), intdiv($taille - $lh, 2), 0, 0, $lw, $lh, $w, $h);
        return $image;
    }
    imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
    // Initiales dessinées petit puis agrandies (sans police à installer)
    $initiales = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', application_nom_court()), 0, 3));
    $petit = imagecreatetruecolor(imagefontwidth(5) * max(1, strlen($initiales)) + 4, imagefontheight(5) + 4);
    imagefill($petit, 0, 0, imagecolorallocate($petit, $r, $g, $b));
    imagestring($petit, 5, 2, 2, $initiales, imagecolorallocate($petit, 255, 255, 255));
    $largeur = (int) round($taille * ($masque ? 0.5 : 0.62));
    $hauteur = (int) round($largeur * imagesy($petit) / imagesx($petit));
    imagecopyresampled($image, $petit, intdiv($taille - $largeur, 2), intdiv($taille - $hauteur, 2), 0, 0, $largeur, $hauteur, imagesx($petit), imagesy($petit));
    return $image;
}

// Luminosité moyenne des pixels visibles du logo (échantillon de 48 × 48) : clair au-delà de 70 %
function application_logo_clair(GdImage $logo): bool
{
    [$w, $h] = [imagesx($logo), imagesy($logo)];
    $somme = 0.0;
    $poids = 0.0;
    for ($i = 0; $i < 48; $i++) {
        for ($j = 0; $j < 48; $j++) {
            $c = imagecolorsforindex($logo, imagecolorat($logo, intdiv($i * $w, 48), intdiv($j * $h, 48)));
            $opacite = 1 - $c['alpha'] / 127;
            $somme += $opacite * (0.2126 * $c['red'] + 0.7152 * $c['green'] + 0.0722 * $c['blue']) / 255;
            $poids += $opacite;
        }
    }
    return $poids > 0 && $somme / $poids > 0.7;
}
