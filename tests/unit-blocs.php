<?php
// Tests du contenu en blocs : tout ce qui pourrait exécuter du script doit disparaître. php tests/unit-blocs.php
declare(strict_types=1);
define('BOUSSOLE_ROOT', dirname(__DIR__) . '/app');
require BOUSSOLE_ROOT . '/core/view.php';
require BOUSSOLE_ROOT . '/core/components.php';
require BOUSSOLE_ROOT . '/core/blocs.php';
function url(string $p = '/'): string { return '/' . ltrim($p, '/'); }

$ok = $ko = 0;
function check(string $label, bool $test): void { global $ok, $ko; $test ? $ok++ : $ko++; echo ($test ? 'OK  ' : 'KO  ') . $label . "\n"; }
function rendu(array $blocks): string { return blocs_rendre(json_encode(['blocks' => $blocks])); }
$p = fn(string $t) => ['type' => 'paragraph', 'data' => ['text' => $t]];
$dangereux = fn(string $html) => (bool) preg_match('/<script|onerror|onload|onclick|javascript:|data:text|<iframe|<img(?![^>]*src="\/uploads\/img-)|<svg|<style|style=/i', $html);

$attaques = [
    '<script>alert(1)</script>bonjour',
    '<img src=x onerror=alert(1)>',
    '<a href="javascript:alert(1)">clic</a>',
    '<a href=" JaVaScRiPt:alert(1)">clic</a>',
    '<a href="data:text/html,<script>alert(1)</script>">clic</a>',
    '<a href="//evil.example/x">clic</a>',
    '<b onclick="alert(1)">gras</b>',
    '<svg onload=alert(1)>',
    '<iframe src="https://evil.example"></iframe>',
    '<p style="background:url(javascript:alert(1))">x</p>',
    '&lt;script&gt;alert(1)&lt;/script&gt;',
    '<a href="https://ok.example" onclick="x()">lien</a>',
    '<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>',
];
foreach ($attaques as $attaque) {
    $html = rendu([$p($attaque)]);
    check('neutralisé : ' . mb_strimwidth($attaque, 0, 50, '…') . ' → ' . mb_strimwidth($html, 0, 60, '…'), !$dangereux($html));
}
check('gras et italique gardés', rendu([$p('<b>gras</b> et <i>italique</i>')]) === '<p><strong>gras</strong> et <em>italique</em></p>');
check('lien externe gardé, avec rel', str_contains(rendu([$p('<a href="https://exemple.org">site</a>')]), '<a href="https://exemple.org" rel="noopener noreferrer nofollow">site</a>'));
check('lien interne gardé', str_contains(rendu([$p('<a href="/page/statuts">statuts</a>')]), '<a href="/page/statuts">statuts</a>'));
check('lien mailto gardé', str_contains(rendu([$p('<a href="mailto:contact@example.org">écrire</a>')]), 'href="mailto:contact@example.org"'));
check('texte « < » échappé', rendu([$p('3 < 5 & 2 > 1')]) === '<p>3 &lt; 5 &amp; 2 &gt; 1</p>');
check('titre : niveau forcé entre 2 et 4', rendu([['type' => 'header', 'data' => ['text' => 'T', 'level' => 1]], ['type' => 'header', 'data' => ['text' => 'T', 'level' => '9 onclick=x']]]) === '<h2>T</h2><h4>T</h4>');
check('image hors du dossier des images refusée', rendu([['type' => 'image', 'data' => ['chemin' => '../config/config.php']]]) === '');
check('image externe refusée', rendu([['type' => 'image', 'data' => ['chemin' => 'https://evil.example/x.png']]]) === '');
check('image valide affichée, légende échappée', str_contains(rendu([['type' => 'image', 'data' => ['chemin' => 'uploads/img-0123456789abcdef01234567.webp', 'legende' => '<b>x</b>"', 'alt' => 'phare']]]), 'alt="phare"') && !str_contains(rendu([['type' => 'image', 'data' => ['chemin' => 'uploads/img-0123456789abcdef01234567.webp', 'legende' => '<script>"']]]), '<script'));
check('type de bloc inconnu ignoré', rendu([['type' => 'raw', 'data' => ['html' => '<script>alert(1)</script>']]]) === '');
check('bloc dynamique inconnu ignoré', rendu([['type' => 'boussole', 'data' => ['bloc' => 'admin']]]) === '');
check('liste imbriquée, 3 niveaux au plus', substr_count(rendu([['type' => 'list', 'data' => ['style' => 'unordered', 'items' => [['content' => 'a', 'items' => [['content' => 'b', 'items' => [['content' => 'c', 'items' => [['content' => 'd', 'items' => []]]]]]]]]]]]), '<ul>') === 3);
check('JSON invalide : rien', blocs_rendre('{pas du json') === '');
check('trop de blocs refusé', blocs_normaliser(json_encode(['blocks' => array_fill(0, BLOCS_MAX + 1, $p('x'))])) === null);
echo "Bilan : $ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
