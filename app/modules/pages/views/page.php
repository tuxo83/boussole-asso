<?php if ($chemin): ?>
<nav aria-label="Fil d'Ariane" class="ariane"><a href="<?= e(url('/')) ?>">Accueil</a><?php foreach ($chemin as $ancetre): ?> › <?= $ancetre['publiee'] ? '<a href="' . e(url('/page/' . $ancetre['slug'])) . '">' . e($ancetre['titre']) . '</a>' : e($ancetre['titre']) ?><?php endforeach ?></nav>
<?php endif ?>
<article class="contenu">
  <h1><?= e($page['titre']) ?></h1>
  <?= $page['blocs'] !== null ? blocs_rendre($page['blocs']) : c_texte($page['contenu']) ?>
</article>
<?php if ($enfants): ?>
<nav aria-label="Sous-pages" class="sous-pages">
  <ul class="liste"><?php foreach ($enfants as $enfant): ?><li><a href="<?= e(url('/page/' . $enfant['slug'])) ?>"><?= e($enfant['titre']) ?></a></li><?php endforeach ?></ul>
</nav>
<?php endif ?>
