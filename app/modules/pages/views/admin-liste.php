<h1>Pages du site</h1>
<p><a href="<?= e(url('/admin/images')) ?>">Images envoyées (médiathèque)</a></p>
<form method="post" action="<?= e(url('/admin/pages')) ?>" class="en-ligne">
  <?= csrf_field() ?>
  <input type="text" name="titre" required maxlength="200" aria-label="Titre de la nouvelle page" placeholder="Titre de la nouvelle page">
  <select name="parent_id" aria-label="Page parente">
    <option value="">Au premier niveau</option>
    <?php foreach ($arbre as $page): ?><option value="<?= (int) $page['id'] ?>"><?= str_repeat('— ', $page['niveau']) . e($page['titre']) ?></option><?php endforeach ?>
  </select>
  <button type="submit" class="bouton bouton-principal">Créer</button>
</form>
<?php if (!$arbre): ?><p>Aucune page pour l'instant.</p><?php endif ?>
<ul class="liste arbre">
  <?php foreach ($arbre as $page): ?>
  <li style="--niveau: <?= (int) $page['niveau'] ?>">
    <a href="<?= e(url('/admin/pages/' . (int) $page['id'])) ?>"><?= e($page['titre']) ?></a>
    <span class="discret">
      <?= $page['est_accueil'] ? '· page d\'accueil ' : '' ?><?= $page['publiee'] ? '· publiée' : '· brouillon' ?><?= $page['reservee_membres'] ? ' · membres seulement' : '' ?><?= $page['dans_menu'] && $page['parent_id'] === null ? ' · dans le menu' : '' ?>
    </span>
    <?php if ($page['publiee']): ?> · <a href="<?= e(url('/page/' . $page['slug'])) ?>">voir</a><?php endif ?>
  </li>
  <?php endforeach ?>
</ul>
