<?php if ($chemin): ?>
<nav aria-label="Fil d'Ariane" class="ariane"><a href="<?= e(url('/')) ?>">Accueil</a><?php foreach ($chemin as $ancetre): ?> › <?= $ancetre['publiee'] ? '<a href="' . e(url('/page/' . $ancetre['slug'])) . '">' . e($ancetre['titre']) . '</a>' : e($ancetre['titre']) ?><?php endforeach ?></nav>
<?php endif ?>
<article class="contenu">
  <h1><?= e($page['titre']) ?></h1>
  <?= $page['blocs'] !== null ? blocs_rendre($page['blocs']) : c_texte($page['contenu']) ?>
</article>
<?php if ($enfants): ?>
<nav aria-label="Sous-pages" class="sous-pages">
  <h2>À lire aussi</h2>
  <div class="cartes"><?php foreach ($enfants as $enfant): ?><article class="carte carte-action"><h3><a href="<?= e(url('/page/' . $enfant['slug'])) ?>"><?= e($enfant['titre']) ?></a></h3><?= !empty($enfant['reservee_membres']) ? '<p>Réservé aux membres</p>' : '' ?></article><?php endforeach ?></div>
</nav>
<?php endif ?>
