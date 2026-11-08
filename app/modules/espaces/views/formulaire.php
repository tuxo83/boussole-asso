<?php $evenement = ($v['type'] ?? '') === 'evenement' && $parent;
// Visibilité dite dans les mots de la situation : une réunion de l'équipe, ou un événement ouvert
$visibilites = $evenement
    ? ['equipe' => "L'équipe de « " . $parent['titre'] . ' » seulement (réunion de travail)', 'adherents' => 'Tous les membres connectés', 'public' => 'Tout le monde (public)']
    : ESPACE_VISIBILITES; ?>
<h1><?= $evenement ? 'Organiser un événement' : 'Nouvel espace' ?><?= $parent ? ' dans « ' . e($parent['titre']) . ' »' : '' ?></h1>
<form method="post" action="<?= e(url('/espaces/nouveau')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?php if ($parent): ?><input type="hidden" name="parent" value="<?= e($parent['slug']) ?>"><?php endif ?>
  <?php if ($evenement): ?>
  <input type="hidden" name="type" value="evenement">
  <?php else: ?>
  <?= c_champ('type', 'Type', ['type' => 'select', 'valeur' => $v['type'] ?? 'projet', 'choix' => ESPACE_TYPES, 'erreur' => $erreurs['type'] ?? null]) ?>
  <?php endif ?>
  <?= c_champ('titre', $evenement ? 'Intitulé' : 'Titre', ['valeur' => $v['titre'] ?? '', 'requis' => true, 'max' => 200, 'erreur' => $erreurs['titre'] ?? null]) ?>
  <?php if ($evenement): ?>
  <?= c_champ('debut', 'Date et heure', ['type' => 'datetime-local', 'valeur' => (string) ($_POST['debut'] ?? ''), 'erreur' => $erreurs['debut'] ?? null, 'aide' => 'Facultatif : vous pourrez la fixer plus tard.']) ?>
  <?= c_champ('lieu', 'Lieu', ['valeur' => (string) ($_POST['lieu'] ?? ''), 'max' => 200, 'aide' => 'Une salle, une adresse, ou « en visio ».']) ?>
  <?php endif ?>
  <?= c_champ('description', $evenement ? 'Ordre du jour ou présentation' : 'Description', ['type' => 'textarea', 'valeur' => $v['description'] ?? '']) ?>
  <?= c_champ('visibilite', 'Qui le voit ?', ['type' => 'select', 'valeur' => $v['visibilite'] ?? 'equipe', 'choix' => $visibilites, 'erreur' => $erreurs['visibilite'] ?? null,
      'aide' => $evenement ? 'Ouvert aux membres ou au public, il apparaît dans l\'agenda de tous ; réservé à l\'équipe, seulement dans l\'agenda de ses membres.' : null]) ?>
  <button type="submit" class="bouton bouton-principal"><?= $evenement ? 'Créer l\'événement' : 'Créer' ?></button>
</form>
