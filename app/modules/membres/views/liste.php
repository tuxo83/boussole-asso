<h1>Membres</h1>
<details open><summary>Ajouter un membre</summary>
<form method="post" action="<?= e(url('/admin/membres')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= c_champ('email', 'E-mail', ['type' => 'email', 'requis' => true]) ?>
  <?= c_champ('prenom', 'Prénom', ['max' => 100]) ?>
  <?= c_champ('nom', 'Nom', ['max' => 100]) ?>
  <?= c_champ('type_membre_id', 'Type de membre', ['type' => 'select', 'choix' => ['' => '—'] + array_column($types, 'libelle', 'id')]) ?>
  <button type="submit" class="bouton bouton-principal">Ajouter</button>
</form>
</details>

<h2><?= count($membres) ?> membre<?= count($membres) > 1 ? 's' : '' ?></h2>
<?php foreach ($membres as $m): $droits = member_rights($m['id']); ?>
<details class="fiche-membre<?= $m['actif'] ? '' : ' inactif' ?>">
  <summary><?= e(membre_nom($m)) ?> <span class="discret">· <?= e($m['type_libelle'] ?? 'sans type') ?><?= $droits ? ' · ' . e(implode(', ', array_map(fn($d) => DROITS[$d], $droits))) : '' ?><?= $m['actif'] ? '' : ' · inactif' ?></span></summary>
  <form method="post" action="<?= e(url('/admin/membres/' . (int) $m['id'])) ?>" class="formulaire">
    <?= csrf_field() ?>
    <p class="discret"><?= e($m['email']) ?> · dernière connexion : <?= e($m['derniere_connexion'] ? date('d/m/Y', strtotime($m['derniere_connexion'])) : 'jamais') ?></p>
    <?= c_champ('prenom', 'Prénom', ['valeur' => $m['prenom'], 'max' => 100]) ?>
    <?= c_champ('nom', 'Nom', ['valeur' => $m['nom'], 'max' => 100]) ?>
    <?= c_champ('type_membre_id', 'Type de membre', ['type' => 'select', 'valeur' => (string) $m['type_membre_id'], 'choix' => ['' => '—'] + array_column($types, 'libelle', 'id')]) ?>
    <label class="case"><input type="checkbox" name="actif" value="1"<?= $m['actif'] ? ' checked' : '' ?>> Compte actif</label>
    <?php if (is_admin($gestionnaire)): ?>
    <fieldset><legend>Droits de plateforme (double authentification obligatoire)</legend>
      <?php foreach (DROITS as $cle => $libelle): ?>
      <label class="case"><input type="checkbox" name="droits[]" value="<?= e($cle) ?>"<?= in_array($cle, $droits, true) ? ' checked' : '' ?>> <?= e($libelle) ?></label>
      <?php endforeach ?>
    </fieldset>
    <?php endif ?>
    <button type="submit" class="bouton bouton-secondaire">Enregistrer</button>
  </form>
</details>
<?php endforeach ?>

<?php if (is_admin($gestionnaire)): ?>
<section class="separe">
  <h2>Types de membres</h2>
  <p><?= e(implode(' · ', array_column($types, 'libelle'))) ?></p>
  <form method="post" action="<?= e(url('/admin/types-membre')) ?>" class="en-ligne">
    <?= csrf_field() ?>
    <input type="text" name="libelle" maxlength="100" aria-label="Nouveau type de membre" placeholder="Ex. membre d'honneur">
    <button type="submit" class="bouton bouton-secondaire">Ajouter</button>
  </form>
</section>
<?php endif ?>
