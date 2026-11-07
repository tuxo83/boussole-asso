<h1>Nouvel espace<?= $parent ? ' dans « ' . e($parent['titre']) . ' »' : '' ?></h1>
<form method="post" action="<?= e(url('/espaces/nouveau')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?php if ($parent): ?><input type="hidden" name="parent" value="<?= e($parent['slug']) ?>"><?php endif ?>
  <?= c_champ('type', 'Type', ['type' => 'select', 'valeur' => $v['type'] ?? 'projet', 'choix' => ESPACE_TYPES, 'erreur' => $erreurs['type'] ?? null]) ?>
  <?= c_champ('titre', 'Titre', ['valeur' => $v['titre'] ?? '', 'requis' => true, 'max' => 200, 'erreur' => $erreurs['titre'] ?? null]) ?>
  <?= c_champ('description', 'Description', ['type' => 'textarea', 'valeur' => $v['description'] ?? '']) ?>
  <?= c_champ('visibilite', 'Qui peut le voir ?', ['type' => 'select', 'valeur' => $v['visibilite'] ?? 'equipe', 'choix' => ESPACE_VISIBILITES, 'erreur' => $erreurs['visibilite'] ?? null]) ?>
  <button type="submit" class="bouton bouton-principal">Créer</button>
</form>
