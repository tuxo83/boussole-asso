<?php
// Installateur Boussole __VERSION__ : déposer ce seul fichier à la racine du site, puis l'ouvrir dans le navigateur.
// Il télécharge Boussole, vérifie son empreinte (inscrite ci-dessous), l'installe, puis se supprime.
// Fichier fabriqué par outils/paquet.sh : ne pas modifier les trois valeurs suivantes.
declare(strict_types=1);

const VERSION = '__VERSION__';
const EMPREINTE = '__SHA256__';
const ADRESSE = '__URL__';

$dossier = __DIR__;
$deja = is_file("$dossier/index.php") || is_file("$dossier/config/config.php");

// Vérifications : [libellé, obligatoire, réussi]
$verifs = [
    ['PHP 8.1 ou plus récent (ici ' . PHP_VERSION . ')', true, version_compare(PHP_VERSION, '8.1.0', '>=')],
    ['Extension pdo_mysql (base de données)', true, extension_loaded('pdo_mysql')],
    ['Extension openssl (chiffrement)', true, extension_loaded('openssl')],
    ['Extension mbstring (textes accentués)', true, extension_loaded('mbstring')],
    ['Extension zip (décompression)', true, class_exists('ZipArchive')],
    ['Téléchargement possible (curl ou allow_url_fopen)', true, function_exists('curl_init') || ini_get('allow_url_fopen')],
    ['Dossier du site accessible en écriture', true, is_writable($dossier)],
    ['Extension gd (images des pages) : conseillée', false, extension_loaded('gd')],
    ['Extension dom (mise en forme des pages) : conseillée', false, class_exists('DOMDocument')],
];
$pret = !$deja && !in_array(false, array_map(fn($v) => !$v[1] || $v[2], $verifs), true);

function telecharger(string $url, string $vers): bool
{
    if (function_exists('curl_init')) {
        $out = fopen($vers, 'wb');
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FILE => $out, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 120,
            CURLOPT_FAILONERROR => true, CURLOPT_USERAGENT => 'Boussole-installateur']);
        $ok = curl_exec($ch) !== false;
        curl_close($ch);
        fclose($out);
        return $ok;
    }
    $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'Boussole-installateur']]));
    return $data !== false && file_put_contents($vers, $data) !== false;
}

function installer(string $dossier): string
{
    $zip = tempnam(sys_get_temp_dir(), 'boussole');
    try {
        if (!telecharger(ADRESSE, $zip) || filesize($zip) > 50 * 1024 * 1024) {
            throw new RuntimeException('Téléchargement impossible : réessayez dans un moment.');
        }
        // Le fichier reçu doit être exactement celui de la version publiée
        if (!hash_equals(EMPREINTE, hash_file('sha256', $zip))) {
            throw new RuntimeException("Le fichier téléchargé n'est pas celui attendu (empreinte différente) : installation annulée.");
        }
        $archive = new ZipArchive();
        if ($archive->open($zip) !== true) {
            throw new RuntimeException('Archive illisible.');
        }
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $nom = $archive->getNameIndex($i);
            if ($nom === false || str_contains($nom, '..') || str_starts_with($nom, '/') || str_contains($nom, '\\')) {
                throw new RuntimeException('Archive refusée (chemin suspect).');
            }
        }
        if (!$archive->extractTo($dossier)) {
            throw new RuntimeException("Décompression impossible : vérifiez les droits d'écriture du dossier.");
        }
        $archive->close();
    } finally {
        @unlink($zip);
    }
    // Seule la personne qui voit cette page connaît le code : elle seule peut finir l'installation
    $code = implode('-', str_split(strtoupper(bin2hex(random_bytes(4))), 4));
    file_put_contents("$dossier/config/code-installation.php", "<?php\nreturn '$code';\n", LOCK_EX);
    @unlink(__FILE__);
    return $code;
}

$code = null;
$erreur = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pret) {
    try {
        $code = installer($dossier);
    } catch (Throwable $e) {
        $erreur = $e->getMessage();
    }
}
$e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Installer Boussole</title>
<style>
body { font-family: system-ui, sans-serif; line-height: 1.6; color: #1b1f24; background: #f5f6f8; margin: 0; padding: 2rem 1rem; }
main { max-width: 36rem; margin: 0 auto; background: #fff; border: 1px solid #e1e5ea; border-radius: 12px; padding: 1.5rem 1.75rem; }
h1 { margin-top: 0; font-size: 1.6rem; }
ul { list-style: none; padding: 0; }
li { padding: .35rem 0; border-bottom: 1px solid #eef0f3; }
.ok::before { content: "✓ "; color: #1f6a34; font-weight: 700; }
.ko::before { content: "✗ "; color: #a3192b; font-weight: 700; }
.avert::before { content: "! "; color: #8a5a00; font-weight: 700; }
button, .bouton { display: inline-block; min-height: 44px; padding: 0 1.4rem; border-radius: 999px; border: 0; background: #1f4e79; color: #fff; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; line-height: 44px; }
.code { font: 700 1.8rem ui-monospace, monospace; letter-spacing: .1em; background: #eef4fb; border-radius: 8px; padding: .5rem 1rem; display: inline-block; }
.erreur { background: #fdf0f1; border: 1px solid #f2c3c8; border-radius: 8px; padding: .75rem 1rem; }
</style>
</head>
<body>
<main>
<h1>Installer Boussole <?= $e(VERSION) ?></h1>
<?php if ($code !== null): ?>
  <p><strong>Boussole est en place.</strong> Notez ce code, il est demandé à l'étape suivante :</p>
  <p class="code" id="code"><?= $e($code) ?></p>
  <p>Gardez-le pour vous : il empêche quelqu'un d'autre de terminer l'installation à votre place. Cet installateur s'est supprimé.</p>
  <p><a class="bouton" href="installation">Continuer l'installation</a></p>
<?php elseif ($deja): ?>
  <p class="erreur">Boussole (ou un autre site) est déjà installé ici : rien n'a été modifié. Supprimez ce fichier.</p>
<?php else: ?>
  <?php if ($erreur !== null): ?><p class="erreur"><?= $e($erreur) ?></p><?php endif ?>
  <p>Vérifications de l'hébergement :</p>
  <ul>
    <?php foreach ($verifs as [$libelle, $obligatoire, $reussi]): ?>
    <li class="<?= $reussi ? 'ok' : ($obligatoire ? 'ko' : 'avert') ?>"><?= $e($libelle) ?></li>
    <?php endforeach ?>
  </ul>
  <?php if ($pret): ?>
  <form method="post"><button type="submit">Télécharger et installer</button></form>
  <p><small>Le fichier téléchargé est vérifié avant d'être installé (empreinte SHA-256).</small></p>
  <?php else: ?>
  <p class="erreur">Il manque un élément obligatoire ci-dessus : demandez à l'hébergeur de l'activer (souvent dans l'espace client, choix de la version de PHP).</p>
  <?php endif ?>
<?php endif ?>
</main>
</body>
</html>
