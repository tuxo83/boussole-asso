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
  <div class="cartes"><?php foreach ($enfants as $enfant): $verrou = !empty($enfant['reservee_membres']) && current_member() === null; ?>
    <?php // Même bandeau sur chaque carte (comme les événements) : les titres restent alignés ?>
    <article class="carte carte-action<?= !empty($enfant['reservee_membres']) ? ' carte-reservee carte-page-reservee' : ' acces-public' ?>"><span class="ruban"><?= !empty($enfant['reservee_membres']) ? 'Réservé aux membres' : 'Ouvert à tous' ?></span>
      <h3><a href="<?= e(url('/page/' . $enfant['slug'])) ?>"><?= $verrou ? c_icone('cadenas') . ' ' : '' ?><?= e($enfant['titre']) ?></a></h3><?= $verrou ? '<p class="petit">Connectez-vous pour y accéder.</p>' : '' ?></article>
    <?php endforeach ?></div>
</nav>
<?php endif ?>
