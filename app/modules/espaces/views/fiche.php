<?php $action = fn(string $chemin) => e(url('/espace/' . $espace['slug'] . $chemin)); ?>
<p class="discret"><?= e(ESPACE_TYPES[$espace['type']]) ?><?php if ($parent): ?> · dans <a href="<?= e(url('/espace/' . $parent['slug'])) ?>"><?= e($parent['titre']) ?></a><?php endif ?></p>
<h1><?= e($espace['titre']) ?></h1>
<p><?= c_meteo($espace['meteo']) ?> · <span class="discret"><?= e(ESPACE_VISIBILITES[$espace['visibilite']]) ?></span></p>

<?php if ($alertes && $peut_contribuer): ?>
<div class="alerte alerte-info" role="status"><strong>À traiter :</strong>
  <ul><?php foreach ($alertes as $alerte): ?><li><?= e($alerte) ?></li><?php endforeach ?></ul>
</div>
<?php endif ?>

<?= c_texte($espace['description']) ?>

<?php if ($peut_contribuer): ?>
<form method="post" action="<?= $action('/meteo') ?>" class="en-ligne">
  <?= csrf_field() ?>
  <label for="meteo">Météo</label>
  <select id="meteo" name="meteo">
    <?php foreach (['soleil' => '☀️ À jour', 'eclaircie' => '🌤️ En cours', 'pluie' => '🌧️ En retard', 'orage' => '⛈️ Bloqué'] as $cle => $libelle): ?>
    <option value="<?= $cle ?>"<?= $espace['meteo'] === $cle ? ' selected' : '' ?>><?= $libelle ?></option>
    <?php endforeach ?>
  </select>
  <button type="submit" class="bouton bouton-secondaire">Mettre à jour</button>
</form>
<?php endif ?>

<div class="colonnes">
<section>
  <h2>Équipe</h2>
  <?php if (!$equipe): ?><p>Personne pour l'instant.</p><?php endif ?>
  <ul class="liste">
    <?php foreach ($equipe as $personne): ?>
    <li><strong><?= e(membre_nom($personne)) ?></strong> · <?= e($personne['libelle'] !== '' ? $personne['libelle'] : ESPACE_NIVEAUX[$personne['niveau']]) ?>
      <?php if ($peut_gerer): ?>
      <form method="post" action="<?= $action('/equipe/retirer') ?>" class="en-ligne"><?= csrf_field() ?><input type="hidden" name="membre_id" value="<?= (int) $personne['id'] ?>"><button type="submit" class="lien-bouton">retirer</button></form>
      <?php endif ?>
    </li>
    <?php endforeach ?>
  </ul>
  <?php if ($peut_gerer): ?>
  <details><summary>Ajouter ou changer le rôle de quelqu'un</summary>
  <form method="post" action="<?= $action('/equipe') ?>" class="formulaire">
    <?= csrf_field() ?>
    <?= c_champ('email', 'E-mail du membre', ['type' => 'email', 'requis' => true]) ?>
    <?= c_champ('niveau', 'Niveau', ['type' => 'select', 'valeur' => 'membre', 'choix' => ESPACE_NIVEAUX]) ?>
    <?= c_champ('libelle', 'Libellé affiché (facultatif)', ['aide' => 'Ex. président, secrétaire, sage.', 'max' => 100]) ?>
    <button type="submit" class="bouton bouton-secondaire">Enregistrer</button>
  </form>
  </details>
  <?php endif ?>
</section>

<section>
  <h2>Dates clés</h2>
  <?php if (!$jalons): ?><p>Aucune date.</p><?php endif ?>
  <ul class="liste">
    <?php foreach ($jalons as $jalon): $depasse = !$jalon['fait'] && $jalon['date_jalon'] < date('Y-m-d'); ?>
    <li class="<?= $jalon['fait'] ? 'fait' : ($depasse ? 'depasse' : '') ?>">
      <time datetime="<?= e($jalon['date_jalon']) ?>"><?= e(date('d/m/Y', strtotime($jalon['date_jalon']))) ?></time> · <?= e($jalon['libelle']) ?>
      <?= $jalon['fait'] ? ' <span class="discret">(fait)</span>' : ($depasse ? ' <strong>(dépassée)</strong>' : '') ?>
      <?php if ($peut_contribuer): ?>
      <form method="post" action="<?= $action('/jalons/' . (int) $jalon['id'] . '/fait') ?>" class="en-ligne"><?= csrf_field() ?><button type="submit" class="lien-bouton"><?= $jalon['fait'] ? 'rouvrir' : 'marquer fait' ?></button></form>
      <?php endif ?>
    </li>
    <?php endforeach ?>
  </ul>
  <?php if ($peut_contribuer): ?>
  <details><summary>Ajouter une date clé</summary>
  <form method="post" action="<?= $action('/jalons') ?>" class="formulaire">
    <?= csrf_field() ?>
    <?= c_champ('date_jalon', 'Date', ['type' => 'date', 'requis' => true]) ?>
    <?= c_champ('libelle', 'Libellé', ['requis' => true, 'max' => 200]) ?>
    <button type="submit" class="bouton bouton-secondaire">Ajouter</button>
  </form>
  </details>
  <?php endif ?>
</section>
</div>

<section>
  <h2>Fichiers</h2>
  <?php if (!$fichiers): ?><p>Aucun fichier.</p><?php endif ?>
  <ul class="liste">
    <?php foreach ($fichiers as $fichier): ?>
    <li><a href="<?= e(url('/fichier/' . (int) $fichier['id'])) ?>"><?= e($fichier['nom']) ?></a>
      <span class="discret">· <?= e(number_format($fichier['taille'] / 1024, 0, ',', ' ')) ?> Ko · <?= e(date('d/m/Y', strtotime($fichier['depose_le']))) ?></span>
      <?php if ($peut_gerer): ?>
      <form method="post" action="<?= e(url('/fichier/' . (int) $fichier['id'] . '/supprimer')) ?>" class="en-ligne"><?= csrf_field() ?><button type="submit" class="lien-bouton">supprimer</button></form>
      <?php endif ?>
    </li>
    <?php endforeach ?>
  </ul>
  <?php if ($peut_contribuer): ?>
  <form method="post" action="<?= $action('/fichiers') ?>" enctype="multipart/form-data" class="en-ligne">
    <?= csrf_field() ?>
    <input type="file" name="fichier" required aria-label="Fichier à déposer">
    <button type="submit" class="bouton bouton-secondaire">Déposer</button>
  </form>
  <?php endif ?>
</section>

<?php if ($enfants || $peut_gerer): ?>
<section>
  <h2>Sous-espaces</h2>
  <ul class="liste"><?php foreach ($enfants as $enfant): ?><li><a href="<?= e(url('/espace/' . $enfant['slug'])) ?>"><?= e($enfant['titre']) ?></a> · <?= c_meteo($enfant['meteo']) ?></li><?php endforeach ?></ul>
  <?php if ($peut_gerer): ?><p><?= c_bouton('Créer un sous-espace', '/espaces/nouveau?parent=' . $espace['slug'], 'secondaire') ?></p><?php endif ?>
</section>
<?php endif ?>

<?php if ($peut_gerer): ?>
<details class="separe"><summary>Modifier l'espace</summary>
<form method="post" action="<?= $action('/modifier') ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= c_champ('titre', 'Titre', ['valeur' => $espace['titre'], 'requis' => true, 'max' => 200]) ?>
  <?= c_champ('description', 'Description', ['type' => 'textarea', 'valeur' => $espace['description']]) ?>
  <?= c_champ('visibilite', 'Qui peut le voir ?', ['type' => 'select', 'valeur' => $espace['visibilite'], 'choix' => ESPACE_VISIBILITES]) ?>
  <button type="submit" class="bouton bouton-secondaire">Enregistrer</button>
</form>
</details>
<?php endif ?>

<?php if ($journal): ?>
<section class="separe">
  <h2>Journal</h2>
  <ul class="liste journal">
    <?php foreach ($journal as $ligne): ?>
    <li><time datetime="<?= e($ligne['le']) ?>"><?= e(date('d/m/Y H:i', strtotime($ligne['le']))) ?></time> · <?= e($ligne['email'] ? membre_nom($ligne) : 'Système') ?> · <?= e($ligne['action']) ?><?= $ligne['via'] === 'ia' ? ' <span class="badge">via IA</span>' : '' ?></li>
    <?php endforeach ?>
  </ul>
</section>
<?php endif ?>
