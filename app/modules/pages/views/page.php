<?php if ($chemin): ?>
<nav aria-label="Fil d'Ariane" class="ariane"><a href="<?= e(url('/')) ?>">Accueil</a><?php foreach ($chemin as $ancetre): ?> › <?= $ancetre['publiee'] ? '<a href="' . e(url('/page/' . $ancetre['slug'])) . '">' . e($ancetre['titre']) . '</a>' : e($ancetre['titre']) ?><?php endforeach ?></nav>
<?php endif ?>
<?php $peut_publier = current_member() !== null && has_right(current_member(), 'publications'); $edition = page_mode_edition(); $editeur = url('/admin/pages/' . (int) $page['id']); ?>
<?php if ($peut_publier): ?><?= page_barre_edition($page, $edition) ?><?php endif ?>
<article class="contenu<?= $edition ? ' en-edition' : '' ?>">
  <?php if ($edition): ?>
  <div class="bloc-editable" data-bloc="titre"><a class="bloc-crayon" href="<?= e($editeur . '#champ-titre') ?>"><?= c_icone('crayon') ?>Titre et description</a><h1><?= e($page['titre']) ?></h1></div>
  <?php else: ?>
  <h1><?= e($page['titre']) ?></h1>
  <?php endif ?>
  <?= $page['blocs'] !== null ? blocs_rendre($page['blocs'], $edition ? $editeur : null) : c_texte($page['contenu']) ?>
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
