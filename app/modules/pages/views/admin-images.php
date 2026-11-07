<h1>Images</h1>
<p><a href="<?= e(url('/admin/pages')) ?>">← Pages du site</a></p>
<p class="petit">Les images s'ajoutent depuis l'éditeur d'une page. Ici, vous voyez où chacune sert ; une image qui ne sert plus peut être supprimée.</p>
<?php if (!$images): ?><p>Aucune image envoyée pour l'instant.</p><?php endif ?>
<ul class="mediatheque">
  <?php foreach ($images as $image): ?>
  <li class="carte">
    <a href="<?= e(url($image['chemin'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url($image['chemin'])) ?>" alt="" loading="lazy" width="<?= (int) $image['largeur'] ?>" height="<?= (int) $image['hauteur'] ?>"></a>
    <p class="petit"><?= (int) $image['largeur'] ?> × <?= (int) $image['hauteur'] ?> · <?= e(date('d/m/Y', strtotime($image['depose_le']))) ?><?= $image['email'] ? ' · ' . e(membre_nom($image)) : '' ?></p>
    <?php if ($image['usages']): ?>
    <p class="petit">Sert dans : <?= e(implode(', ', $image['usages'])) ?></p>
    <?php else: ?>
    <p class="petit discret">Ne sert nulle part.</p>
    <form method="post" action="<?= e(url('/admin/images/' . (int) $image['id'] . '/supprimer')) ?>"><?= csrf_field() ?><button type="submit" class="lien-bouton lien-danger" data-confirmer="Supprimer cette image ? C'est irréversible.">Supprimer</button></form>
    <?php endif ?>
  </li>
  <?php endforeach ?>
</ul>
