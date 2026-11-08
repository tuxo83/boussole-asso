<?php
// Création d'un espace ou d'un événement : le type en grands boutons (une ligne d'explication chacun),
// les champs de date et de lieu seulement pour un événement, la visibilité dite avec les mots de la situation
$type_choisi = (string) ($v['type'] ?? ($parent ? 'projet' : ''));
$evenement_seul = ($v['type'] ?? '') === 'evenement' && $parent; // « Organiser » depuis un espace : le type est fixé
$types = [
    'evenement' => ['calendrier', 'Événement', 'Une date, un lieu, des inscriptions : conférence, visite, réunion. Il apparaît dans l\'agenda.'],
    'projet' => ['dossier', 'Projet', 'Un travail avec un début et une fin : dossiers, dates clés, équipe.'],
    'commission' => ['personnes', 'Commission', 'Un groupe permanent sur un thème : trinôme, cadets, communication…'],
    'comite' => ['drapeau', 'Comité', 'L\'instance de direction (comité directeur, bureau).'],
];
$visibilites = $type_choisi === 'evenement'
    ? ['equipe' => $parent ? "L'équipe seulement (réunion de travail)" : "Les organisateurs seulement", 'adherents' => 'Tous les membres connectés', 'public' => 'Tout le monde (public)']
    : ESPACE_VISIBILITES;
$titre_page = $evenement_seul ? 'Organiser un événement' : ($type_choisi === 'evenement' ? 'Nouvel événement' : 'Créer');
?>
<h1><?= e($titre_page) ?><?= $parent ? ' dans « ' . e($parent['titre']) . ' »' : '' ?></h1>
<form method="post" action="<?= e(url('/espaces/nouveau')) ?>" class="formulaire formulaire-creation" data-creation>
  <?= csrf_field() ?>
  <?php if ($parent): ?><input type="hidden" name="parent" value="<?= e($parent['slug']) ?>"><?php endif ?>
  <?php if ($evenement_seul): ?>
  <input type="hidden" name="type" value="evenement">
  <?php else: ?>
  <fieldset class="choix-type"><legend>Que créez-vous ?</legend>
    <?php foreach ($types as $cle => [$icone, $libelle, $aide]): ?>
    <label class="carte-choix"><input type="radio" name="type" value="<?= $cle ?>" required<?= $type_choisi === $cle ? ' checked' : '' ?>>
      <span class="carte-choix-icone"><?= c_icone($icone) ?></span><span class="carte-choix-texte"><strong><?= e($libelle) ?></strong><small><?= e($aide) ?></small></span></label>
    <?php endforeach ?>
    <?php if (!empty($erreurs['type'])): ?><p class="erreur-champ"><?= e($erreurs['type']) ?></p><?php endif ?>
  </fieldset>
  <?php endif ?>
  <?= c_champ('titre', 'Titre', ['valeur' => $v['titre'] ?? '', 'requis' => true, 'max' => 200, 'erreur' => $erreurs['titre'] ?? null]) ?>
  <div data-si-evenement>
    <?= c_champ('debut', 'Date et heure', ['type' => 'datetime-local', 'valeur' => (string) ($_POST['debut'] ?? ''), 'erreur' => $erreurs['debut'] ?? null, 'aide' => 'Facultatif : vous pourrez la fixer plus tard.']) ?>
    <?= c_champ('lieu', 'Lieu', ['valeur' => (string) ($_POST['lieu'] ?? ''), 'max' => 200, 'aide' => 'Une salle, une adresse, ou « en visio ».']) ?>
  </div>
  <?= c_champ('description', 'Présentation', ['type' => 'textarea', 'lignes' => 4, 'valeur' => $v['description'] ?? '', 'aide' => 'Pour un événement : programme ou ordre du jour.']) ?>
  <?= c_visibilite_choix((string) ($v['visibilite'] ?? ($type_choisi === 'evenement' && !$parent ? 'adherents' : 'equipe')), $visibilites, $evenement_seul ? $parent['titre'] : null) ?>
  <?php if (!empty($erreurs['visibilite'])): ?><p class="erreur-champ"><?= e($erreurs['visibilite']) ?></p><?php endif ?>
  <button type="submit" class="bouton bouton-principal">Créer</button>
</form>
