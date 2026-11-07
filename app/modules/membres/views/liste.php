<h1>Membres</h1>
<?php if ($en_attente): ?>
<section class="statut-inscription statut-a-regler" aria-labelledby="titre-attente">
  <h2 id="titre-attente">En attente de validation (<?= count($en_attente) ?>)</h2>
  <p class="petit">Adhésions payées par des personnes sans compte. Elles n'ont pas accès au site tant que vous n'avez pas validé.</p>
  <ul class="liste">
    <?php foreach ($en_attente as $m): ?>
    <li class="candidat"><span class="qui"><strong><?= e(membre_nom($m)) ?></strong><span class="discret"><?= e($m['email']) ?><?= $m['paye_le'] ? ' · cotisation payée le ' . e(date('d/m/Y', strtotime($m['paye_le']))) : '' ?></span></span>
      <span class="en-ligne">
        <form method="post" action="<?= e(url('/admin/membres/' . (int) $m['id'] . '/validation')) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="valider"><button type="submit" class="bouton bouton-principal">Valider</button></form>
        <form method="post" action="<?= e(url('/admin/membres/' . (int) $m['id'] . '/validation')) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="refuser"><button type="submit" class="bouton bouton-secondaire" data-confirmer="Refuser l'adhésion de <?= e(membre_nom($m)) ?> ? Le compte sera supprimé.">Refuser</button></form>
      </span></li>
    <?php endforeach ?>
  </ul>
</section>
<?php endif ?>
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
<?php if ($jamais > 0): ?>
<form method="post" action="<?= e(url('/admin/membres/inviter')) ?>" class="en-ligne">
  <?= csrf_field() ?>
  <span><?= $jamais ?> membre(s) ne se sont encore jamais connectés.</span>
  <button type="submit" class="bouton bouton-principal" data-confirmer="Envoyer l'invitation de bienvenue aux <?= $jamais ?> membre(s) qui ne se sont jamais connectés ?">Envoyer l'invitation de bienvenue</button>
</form>
<?php endif ?>
<?php foreach ($membres as $m): $droits = member_rights($m['id']); ?>
<details class="fiche-membre<?= $m['actif'] ? '' : ' inactif' ?>">
  <summary><?= e(membre_nom($m)) ?> <span class="discret">· <?= e($m['type_libelle'] ?? 'sans type') ?><?= $droits ? ' · ' . e(implode(', ', array_map(fn($d) => DROITS[$d], $droits))) : '' ?><?= $m['actif'] ? '' : ' · inactif' ?></span></summary>
  <form method="post" action="<?= e(url('/admin/membres/' . (int) $m['id'])) ?>" class="formulaire">
    <?= csrf_field() ?>
    <p class="discret"><?= e($m['email']) ?> · dernière connexion : <?= e($m['derniere_connexion'] ? date('d/m/Y', strtotime($m['derniere_connexion'])) : 'jamais') ?><?= $m['invite_le'] ? ' · invité le ' . e(date('d/m/Y', strtotime($m['invite_le']))) : '' ?></p>
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
  <?php if ($m['actif']): ?>
  <form method="post" action="<?= e(url('/admin/membres/inviter')) ?>"><?= csrf_field() ?><input type="hidden" name="membre_id" value="<?= (int) $m['id'] ?>">
    <button type="submit" class="lien-bouton"><?= $m['invite_le'] ? "Renvoyer l'invitation de bienvenue" : "Envoyer l'invitation de bienvenue" ?></button></form>
  <?php endif ?>
  <?php if (is_admin($gestionnaire) && (int) $m['id'] !== (int) $gestionnaire['id']): ?>
  <form method="post" action="<?= e(url('/admin/membres/' . (int) $m['id'] . '/effacer')) ?>"><?= csrf_field() ?>
    <button type="submit" class="lien-bouton lien-danger" data-confirmer="Effacer définitivement <?= e(membre_nom($m)) ?> ? Son compte, ses cotisations, ses inscriptions et ses clés disparaissent, et son nom est retiré du journal. Pour suspendre seulement l'accès, décochez « Compte actif ».">Effacer définitivement ce membre</button></form>
  <?php endif ?>
</details>
<?php endforeach ?>

<?php if (is_admin($gestionnaire)): ?>
<section class="separe">
  <h2>Types de membres</h2>
  <ul class="liste"><?php foreach ($types as $type): ?>
    <li><?= e($type['libelle']) ?>
      <form method="post" action="<?= e(url('/admin/types-membre/' . (int) $type['id'] . '/supprimer')) ?>" class="en-ligne"><?= csrf_field() ?><button type="submit" class="lien-bouton" data-confirmer="Retirer le type « <?= e($type['libelle']) ?> » ? Les membres concernés resteront, sans type." aria-label="Retirer <?= e($type['libelle']) ?>">retirer</button></form></li>
  <?php endforeach ?></ul>
  <form method="post" action="<?= e(url('/admin/types-membre')) ?>" class="en-ligne">
    <?= csrf_field() ?>
    <input type="text" name="libelle" maxlength="100" aria-label="Nouveau type de membre" placeholder="Ex. membre d'honneur">
    <button type="submit" class="bouton bouton-secondaire">Ajouter</button>
  </form>
</section>
<?php endif ?>
