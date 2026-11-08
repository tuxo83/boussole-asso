<?php
// Page « Gérer » d'un espace ou d'un événement : tout ce qui se règle, rangé dans des onglets toujours dans le même ordre
$action = fn(string $chemin) => e(url('/espace/' . $espace['slug'] . $chemin));
$evenement = $espace['type'] === 'evenement';
$garder = function (array $sauf) use ($espace): string {
    $html = csrf_field() . '<input type="hidden" name="retour" value="gerer">';
    foreach (['titre', 'description', 'visibilite', 'type'] as $champ) {
        if (!in_array($champ, $sauf, true)) {
            $html .= '<input type="hidden" name="' . $champ . '" value="' . e($espace[$champ]) . '">';
        }
    }
    return $html;
};
$onglets = array_filter([
    'reglages' => $evenement ? 'Réglages' : null,
    'organisation' => 'Organisation',
    'journal' => $journal ? 'Journal' : null,
    'archiver' => 'Archiver ou supprimer',
]);
?>
<nav class="ariane" aria-label="Fil d'Ariane"><a href="<?= e(url('/espace/' . $espace['slug'])) ?>">← <?= e($espace['titre']) ?></a></nav>
<h1>Gérer <span class="discret">· <?= e($espace['titre']) ?></span></h1>

<div data-onglets class="page-gerer">
  <div class="onglets" role="tablist" hidden>
    <?php $premier = array_key_first($onglets); foreach ($onglets as $id => $libelle): ?>
    <button type="button" role="tab" id="onglet-<?= $id ?>" aria-controls="<?= $id ?>" aria-selected="<?= $id === $premier ? 'true' : 'false' ?>" data-onglet="<?= $id ?>"><?= e($libelle) ?></button>
    <?php endforeach ?>
  </div>

  <?php if ($evenement): ?>
  <section id="reglages" role="tabpanel" aria-labelledby="onglet-reglages" class="panneau-onglet">
    <h2 class="titre-onglet">Réglages de l'événement</h2>
    <?= partial('evenements/reglages', ['espace' => $espace, 'ev' => evenement_de($espace)]) ?>
  </section>
  <?php endif ?>

  <section id="organisation" role="tabpanel" aria-labelledby="onglet-organisation" class="panneau-onglet">
    <h2 class="titre-onglet">Organisation</h2>
    <div class="bloc-reglage">
      <p class="petit">Le titre, la description, la météo et qui peut voir l'espace se modifient d'un clic, directement sur la page de l'espace.</p>
      <form method="post" action="<?= $action('/modifier') ?>" class="formulaire">
        <?= $garder(['type']) ?>
        <?= c_champ('type', 'Type', ['type' => 'select', 'valeur' => $espace['type'], 'choix' => ESPACE_TYPES]) ?>
        <?php if (is_admin($membre)): ?>
        <?= c_champ('parent', 'Rattaché à', ['type' => 'select', 'valeur' => $parent['slug'] ?? '', 'choix' => ['' => '— Aucun (premier niveau)'] + $parents_possibles]) ?>
        <?php endif ?>
        <button type="submit" class="bouton bouton-principal">Enregistrer</button>
      </form>
    </div>
    <?php if (!$evenement): ?>
    <div class="bloc-reglage">
      <p><strong>Sous-espace</strong><br><span class="discret">Un projet ou un groupe de travail à l'intérieur de cet espace.</span></p>
      <p><?= c_bouton('+ Créer un sous-espace', '/espaces/nouveau?parent=' . $espace['slug'], 'secondaire') ?></p>
    </div>
    <?php endif ?>
  </section>

  <?php if ($journal): ?>
  <section id="journal" role="tabpanel" aria-labelledby="onglet-journal" class="panneau-onglet">
    <h2 class="titre-onglet">Journal</h2>
    <ul class="journal-liste">
      <?php foreach ($journal as $ligne): ?>
      <li><time datetime="<?= e($ligne['le']) ?>"><?= e(date('d/m/Y H:i', strtotime($ligne['le']))) ?></time><span><?= e($ligne['action']) ?></span><span class="discret"><?= e($ligne['email'] ? membre_nom($ligne) : 'Système') ?><?= $ligne['via'] === 'ia' ? ' · via IA' : '' ?></span></li>
      <?php endforeach ?>
    </ul>
  </section>
  <?php endif ?>

  <section id="archiver" role="tabpanel" aria-labelledby="onglet-archiver" class="panneau-onglet">
    <h2 class="titre-onglet">Archiver ou supprimer</h2>
    <div class="bloc-reglage">
      <p><strong><?= $espace['archive'] ? 'Désarchiver' : 'Archiver' ?></strong><br><span class="discret"><?= $espace['archive'] ? 'Il revient dans les listes et dans l\'agenda.' : 'Il sort des listes et de l\'agenda, sans rien perdre. On peut le désarchiver.' ?></span></p>
      <form method="post" action="<?= $action('/archiver') ?>"><?= csrf_field() ?><button type="submit" class="bouton bouton-secondaire"<?= $espace['archive'] ? '' : ' data-confirmer="Archiver « ' . e($espace['titre']) . ' » ?"' ?>><?= $espace['archive'] ? 'Désarchiver' : 'Archiver' ?></button></form>
    </div>
    <?php if (is_admin($membre)): ?>
    <div class="bloc-reglage zone-sensible">
      <p><strong>Supprimer définitivement</strong><br><span class="discret">L'espace, ses dates, ses fichiers<?= $evenement ? ' et ses inscriptions' : '' ?> disparaissent. Impossible à annuler.</span></p>
      <form method="post" action="<?= $action('/supprimer') ?>" class="formulaire">
        <?= csrf_field() ?>
        <?= c_champ('confirmation', 'Pour confirmer, tapez son nom : ' . $espace['titre'], ['requis' => true, 'max' => 200]) ?>
        <button type="submit" class="bouton bouton-danger">Supprimer définitivement</button>
      </form>
    </div>
    <?php endif ?>
  </section>
</div>
