<?php
// Gabarit commun à toutes les pages : en-tête, menu, messages, contenu, pied de page
$nom = setting('nom_association', 'Boussole') ?? 'Boussole';
$logo = setting('logo');
$menu = installation_done() ? menu_items() : [];
$chemin = request_path();
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($titre) ? $titre . ' · ' . $nom : $nom) ?></title>
<link rel="stylesheet" href="<?= e(url('/assets/css/boussole.css')) ?>?v=<?= e(BOUSSOLE_VERSION) ?>">
<style>:root{--couleur-principale:<?= color_setting('couleur_principale', '#1f4e79') ?>;--couleur-accent:<?= color_setting('couleur_accent', '#c8102e') ?>}</style>
<?php if (installation_done() && trim((string) setting('css_personnalise', '')) !== ''): ?>
<link rel="stylesheet" href="<?= e(url('/theme.css')) ?>?v=<?= e(substr(md5((string) setting('css_personnalise')), 0, 8)) ?>">
<?php endif ?>
</head>
<body>
<a class="lien-evitement" href="#contenu">Aller au contenu</a>
<?php if (installation_done() && construction_active()): ?>
<div class="bandeau-construction" role="note"><?= c_icone('attention') ?><span><strong>Site en construction</strong> : version de travail, contenu en cours de rédaction.</span></div>
<?php endif ?>
<header class="entete">
  <div class="conteneur entete-interne">
    <a class="marque" href="<?= e(url('/')) ?>">
      <?php if ($logo): ?><img src="<?= e(url($logo)) ?>" alt="" height="48"><?php endif ?>
      <span><?= e($nom) ?></span>
    </a>
    <?php if ($menu): ?>
    <nav aria-label="Menu principal">
      <ul class="menu">
        <?php foreach ($menu as $item): ?>
        <li><a href="<?= e(url($item['lien'])) ?>"<?= $item['lien'] === $chemin ? ' aria-current="page"' : '' ?>><?= e($item['libelle']) ?></a></li>
        <?php endforeach ?>
      </ul>
    </nav>
    <?php endif ?>
  </div>
</header>
<main id="contenu" class="conteneur">
  <?php foreach (flash() as $message): ?><?= c_alerte($message['type'], (string) $message['message']) ?><?php endforeach ?>
  <?= $content ?>
</main>
<footer class="pied">
  <div class="conteneur">
    <p><?= e(setting('pied_de_page', '') ?? '') ?></p>
    <p class="discret"><?= e(t('pied.propulse')) ?></p>
  </div>
</footer>
<script src="<?= e(url('/assets/js/boussole.js')) ?>?v=<?= e(BOUSSOLE_VERSION) ?>" defer></script>
</body>
</html>
