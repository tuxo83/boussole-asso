<?php
// Page d'un espace (avis d'Élise et d'Inès) : en haut l'état et ce qu'il faut faire, au milieu on agit
// (équipe, dates, fichiers), en bas l'administration rangée dans « Gérer l'espace »
$action = fn(string $chemin) => e(url('/espace/' . $espace['slug'] . $chemin));
// Nom affiché : prénom et nom, sinon le début de l'adresse (l'adresse complète en infobulle)
$nom_court = fn(array $p) => trim(($p['prenom'] ?? '') . ' ' . ($p['nom'] ?? '')) ?: strstr((string) $p['email'], '@', true);
// Chaque point « à traiter » mène à l'endroit où on le règle
$cible_alerte = fn(string $a) => match (true) {
    str_contains($a, 'n°') => '#ajout-equipe',
    str_contains($a, 'fichier') => '#fichiers',
    str_contains($a, 'date') => '#dates',
    default => null,
};
// Modifier sur place (n°1, n°2, administrateur) : on clique sur le titre, la description ou la visibilité ;
// le formulaire renvoie les autres valeurs telles quelles
$garder = function (array $sauf) use ($espace): string {
    $html = csrf_field();
    foreach (['titre', 'description', 'visibilite', 'type'] as $champ) {
        if (!in_array($champ, $sauf, true)) {
            $html .= '<input type="hidden" name="' . $champ . '" value="' . e($espace[$champ]) . '">';
        }
    }
    return $html;
};
$boutons = '<span class="en-place-boutons"><button type="submit" class="bouton bouton-principal">Enregistrer</button> <button type="button" class="lien-bouton" data-en-place-annuler>Annuler</button></span>';
?>
<nav class="ariane" aria-label="Fil d'Ariane"><a href="<?= e(url('/espaces')) ?>">Espaces</a><?php if ($parent): ?> › <a href="<?= e(url('/espace/' . $parent['slug'])) ?>"><?= e($parent['titre']) ?></a><?php endif ?></nav>
<p class="surtitre surtitre-espace"><?= e(ESPACE_TYPES[$espace['type']]) ?></p>
<?php if ($peut_gerer): ?>
<div class="en-place" data-en-place>
  <h1 class="en-place-vue" tabindex="0" role="button" aria-label="Modifier le titre : <?= e($espace['titre']) ?>"><?= e($espace['titre']) ?><span class="en-place-crayon"><?= c_icone('crayon') ?></span></h1>
  <form method="post" action="<?= $action('/modifier') ?>" class="en-place-form"><?= $garder(['titre']) ?>
    <label for="sur-place-titre" class="visuellement-cache">Titre</label>
    <input id="sur-place-titre" name="titre" value="<?= e($espace['titre']) ?>" required maxlength="200" class="en-place-titre"><?= $boutons ?></form>
</div>
<?php else: ?>
<h1><?= e($espace['titre']) ?></h1>
<?php endif ?>
<?php if ($espace['archive']): ?>
<div class="alerte alerte-info" role="status">Cet espace est archivé : il n'apparaît plus dans les listes ni dans l'agenda.
  <?php if ($peut_gerer): ?><form method="post" action="<?= $action('/archiver') ?>" class="en-ligne"><?= csrf_field() ?><button type="submit" class="lien-bouton">Désarchiver</button></form><?php endif ?></div>
<?php endif ?>

<div class="espace-etat">
  <?php if ($peut_contribuer): ?>
  <details class="meteo-menu">
    <summary aria-label="Changer la météo"><?= c_meteo($espace['meteo']) ?><span class="meteo-fleche" aria-hidden="true">▾</span></summary>
    <form method="post" action="<?= $action('/meteo') ?>" class="meteo-options">
      <?= csrf_field() ?>
      <?php foreach (['soleil', 'eclaircie', 'pluie', 'orage'] as $cle): ?>
      <button type="submit" name="meteo" value="<?= $cle ?>" aria-pressed="<?= $espace['meteo'] === $cle ? 'true' : 'false' ?>"><?= c_meteo($cle) ?></button>
      <?php endforeach ?>
    </form>
  </details>
  <?php else: ?>
  <?= c_meteo($espace['meteo']) ?>
  <?php endif ?>
  <?php if ($peut_gerer): ?>
  <span class="en-place en-place-ligne" data-en-place>
    <span class="en-place-vue discret" tabindex="0" role="button" aria-label="Modifier qui peut voir l'espace"><?= e(ESPACE_VISIBILITES[$espace['visibilite']]) ?><span class="en-place-crayon"><?= c_icone('crayon') ?></span></span>
    <form method="post" action="<?= $action('/modifier') ?>" class="en-place-form"><?= $garder(['visibilite']) ?>
      <label for="sur-place-visibilite" class="visuellement-cache">Qui peut voir l'espace ?</label>
      <select id="sur-place-visibilite" name="visibilite"><?php foreach (ESPACE_VISIBILITES as $cle => $libelle): ?><option value="<?= e($cle) ?>"<?= $espace['visibilite'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option><?php endforeach ?></select><?= $boutons ?></form>
  </span>
  <?php else: ?>
  <span class="discret"><?= e(ESPACE_VISIBILITES[$espace['visibilite']]) ?></span>
  <?php endif ?>
</div>

<?php if ($alertes && $peut_contribuer): ?>
<div class="a-faire" role="status"><strong><?= c_icone('attention') ?>À faire</strong>
  <ul><?php foreach ($alertes as $alerte): $cible = $cible_alerte($alerte); $verbe_alerte = match ($cible) { '#ajout-equipe' => 'Choisir', '#fichiers' => 'Déposer', default => 'Voir' }; ?>
    <li><span><?= e($alerte) ?></span><?php if ($cible): ?><a class="bouton bouton-secondaire" href="<?= $cible ?>"<?= $cible === '#ajout-equipe' ? ' data-ouvrir="ajout-equipe"' : '' ?>><?= $verbe_alerte ?></a><?php endif ?></li><?php endforeach ?></ul>
  <?php if ($espace['meteo'] === 'soleil' && array_filter($alertes, fn($a) => str_contains($a, 'dépassée'))): ?><p class="petit">Une date clé est dépassée : la météo est-elle toujours « À jour » ?</p><?php endif ?>
</div>
<?php endif ?>

<?php if ($peut_gerer): ?>
<div class="en-place en-place-texte" data-en-place>
  <div class="en-place-vue" tabindex="0" role="button" aria-label="Modifier la description"><?= $espace['description'] !== '' ? c_texte($espace['description']) : '<p class="discret">Ajouter une description…</p>' ?><span class="en-place-crayon"><?= c_icone('crayon') ?></span></div>
  <form method="post" action="<?= $action('/modifier') ?>" class="en-place-form"><?= $garder(['description']) ?>
    <label for="sur-place-description" class="visuellement-cache">Description</label>
    <textarea id="sur-place-description" name="description" rows="4"><?= e($espace['description']) ?></textarea><?= $boutons ?></form>
</div>
<?php else: ?>
<?= c_texte($espace['description']) ?>
<?php endif ?>
<?php if ($espace['type'] === 'evenement'): ?><?= partial('evenements/bloc', evenement_vue($espace, $membre)) ?><?php endif ?>

<?php if ($espace['type'] !== 'evenement'): ?>
<nav class="ancres" aria-label="Dans cet espace"><?php foreach (['agenda' => 'Agenda', 'dates' => 'Dates', 'fichiers' => 'Fichiers', 'equipe' => 'Équipe'] as $id => $libelle): ?><a href="#<?= $id ?>"><?= $libelle ?></a><?php endforeach ?></nav>
<?php endif ?>
<div class="espace-colonnes">
<div class="espace-principal">
<?php if ($espace['type'] !== 'evenement' && ($evenements_espace || $peut_gerer)): ?>
<section class="espace-evenements" id="agenda">
  <div class="titre-avec-lien"><h2>Agenda</h2><?php if ($peut_gerer): ?><?= c_bouton('+ Organiser', '/espaces/nouveau?parent=' . $espace['slug'] . '&type=evenement', 'secondaire') ?><?php endif ?></div>
  <?php if ($evenements_espace): $mois_courts = ['', 'janv.', 'févr.', 'mars', 'avril', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.']; ?>
  <ul class="rdv">
    <?php foreach ($evenements_espace as $ev): $passe = $ev['debut'] !== null && strtotime($ev['debut']) < strtotime('today'); ?>
    <li<?= $passe ? ' class="rdv-passe"' : '' ?>><a href="<?= e(url('/espace/' . $ev['slug'])) ?>">
      <span class="date-tuile<?= ['public' => '', 'equipe' => ' date-equipe'][$ev['visibilite']] ?? ' date-membres' ?>"><?php if ($ev['debut']): ?><span class="jour"><?= (int) date('j', strtotime($ev['debut'])) ?></span><span class="mois"><?= $mois_courts[(int) date('n', strtotime($ev['debut']))] ?></span><?php else: ?><span class="date-icone"><?= c_icone('calendrier') ?></span><?php endif ?></span>
      <span class="rdv-texte"><strong><?= e($ev['titre']) ?></strong><span class="rdv-ligne"><span class="discret"><?= $ev['debut'] ? e(date('H\hi', strtotime($ev['debut']))) : 'Date à préciser' ?><?= $ev['lieu'] ? ' · ' . e($ev['lieu']) : '' ?></span>
        <?php [$classe, $texte] = ['equipe' => ['badge-equipe', 'Équipe'], 'public' => ['', 'Ouvert à tous']][$ev['visibilite']] ?? ['badge-membres', 'Membres']; ?><span class="badge <?= $classe ?>"><?= $texte ?></span></span></span>
    </a></li>
    <?php endforeach ?>
  </ul>
  <?php else: ?>
  <p class="vide"><?= c_icone('calendrier') ?><span>Aucun événement. Une réunion d'équipe ou un événement ouvert à tous : il apparaîtra ici et dans l'agenda.</span></p>
  <?php endif ?>
</section>
<?php endif ?>

<?php if ($jalons || $peut_contribuer): ?>
<section id="dates">
  <h2>Dates clés</h2>
  <?php if (!$jalons): ?><p class="discret">Aucune date.</p><?php endif ?>
  <ul class="dates-cles">
    <?php foreach ($jalons as $jalon): $depasse = !$jalon['fait'] && $jalon['date_jalon'] < date('Y-m-d'); ?>
    <li class="<?= $jalon['fait'] ? 'fait' : ($depasse ? 'depasse' : '') ?>">
      <?php if ($peut_contribuer): ?><form method="post" action="<?= $action('/jalons/' . (int) $jalon['id'] . '/fait') ?>" class="pastille-fait"><?= csrf_field() ?><button type="submit" aria-pressed="<?= $jalon['fait'] ? 'true' : 'false' ?>" title="<?= $jalon['fait'] ? 'Rouvrir' : 'Marquer fait' ?>"><span class="visuellement-cache"><?= $jalon['fait'] ? 'rouvrir' : 'marquer fait' ?> : <?= e($jalon['libelle']) ?></span></button></form>
      <?php else: ?><span class="pastille-fait"><span class="rond<?= $jalon['fait'] ? ' rond-fait' : '' ?>" aria-hidden="true"></span></span><?php endif ?>
      <time datetime="<?= e($jalon['date_jalon']) ?>"><?= e(date('d/m/Y', strtotime($jalon['date_jalon']))) ?></time><span class="libelle-date"><?= e($jalon['libelle']) ?></span>
      <?= $jalon['fait'] ? ' <span class="discret">(fait)</span>' : ($depasse ? ' <span class="badge badge-alerte">dépassée</span>' : '') ?>
      <?php if ($peut_contribuer): ?>
      <span class="actions-ligne">
      <form method="post" action="<?= $action('/jalons/' . (int) $jalon['id'] . '/supprimer') ?>" class="en-ligne"><?= csrf_field() ?><button type="submit" class="lien-bouton" data-confirmer="Supprimer la date « <?= e($jalon['libelle']) ?> » ?">supprimer</button></form>
      </span>
      <?php endif ?>
    </li>
    <?php endforeach ?>
  </ul>
  <?php if ($peut_contribuer): ?>
  <details><summary>+ Ajouter une date</summary>
  <form method="post" action="<?= $action('/jalons') ?>" class="formulaire">
    <?= csrf_field() ?>
    <?= c_champ('date_jalon', 'Date', ['type' => 'date', 'requis' => true]) ?>
    <?= c_champ('libelle', 'Libellé', ['requis' => true, 'max' => 200]) ?>
    <?= c_champ('destinataires', 'Rappel par e-mail à', ['type' => 'select', 'valeur' => 'binome', 'choix' => ['binome' => 'n°1 et n°2', 'equipe' => "toute l'équipe"], 'aide' => 'Délais réglés par l\'administrateur (ex. 14 et 3 jours avant).']) ?>
    <button type="submit" class="bouton bouton-secondaire">Ajouter</button>
  </form>
  </details>
  <?php endif ?>
</section>
<?php endif ?>

<?php if ($fichiers || $peut_contribuer): ?>
<section id="fichiers">
  <h2>Fichiers</h2>
  <?php if (!$fichiers && !$peut_contribuer): ?><p class="discret">Aucun fichier.</p><?php endif ?>

  <?php if ($peut_contribuer): ?>
  <form method="post" action="<?= $action('/fichiers') ?>" enctype="multipart/form-data" class="depot">
    <?= csrf_field() ?>
    <label class="zone-depot"><input type="file" name="fichier" required data-envoi-auto><span>Glissez un fichier ici, ou cliquez pour le choisir</span></label>
    <button type="submit" class="bouton bouton-secondaire" data-sans-js>Déposer</button>
  </form>
  <?php endif ?>
  <?php if ($fichiers): ?>
  <ul class="fichiers-liste">
    <?php foreach ($fichiers as $fichier): ?>
    <li><a href="<?= e(url('/fichier/' . (int) $fichier['id'])) ?>"><?= e($fichier['nom']) ?></a>
      <span class="discret">· <?= e(taille_lisible((int) $fichier['taille'])) ?> · <?= e(date('d/m/Y', strtotime($fichier['depose_le']))) ?></span>
      <?php if ($peut_gerer): ?>
      <form method="post" action="<?= e(url('/fichier/' . (int) $fichier['id'] . '/supprimer')) ?>" class="en-ligne"><?= csrf_field() ?><button type="submit" class="lien-bouton" data-confirmer="Supprimer le fichier <?= e($fichier['nom']) ?> ?" aria-label="Supprimer <?= e($fichier['nom']) ?>">supprimer</button></form>
      <?php endif ?>
    </li>
    <?php endforeach ?>
  </ul>
  <?php endif ?>
</section>
<?php endif ?>
</div>
<aside class="espace-cote">
<?php if ($equipe || $peut_gerer): ?>
<section id="equipe">
  <div class="titre-avec-lien"><h2>Équipe</h2></div>
  <?php if (!$equipe): ?><p class="discret">Personne pour l'instant.</p><?php endif ?>
  <ul class="equipe-liste">
    <?php foreach ($equipe as $personne): ?>
    <li><span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($nom_court($personne), 0, 1))) ?></span><span class="qui"><strong title="<?= e($personne['email']) ?>"><?= e($nom_court($personne)) ?></strong>
      <span class="discret"><?= e(in_array($personne['niveau'], ['n1', 'n2'], true) ? ($personne['niveau'] === 'n1' ? 'N°1' : 'N°2') . ($personne['libelle'] !== '' ? ' · ' . $personne['libelle'] : '') : ($personne['libelle'] !== '' ? $personne['libelle'] : ESPACE_NIVEAUX[$personne['niveau']])) ?></span></span></li>
    <?php endforeach ?>
  </ul>
  <?php if ($peut_gerer): ?>
  <details id="ajout-equipe"><summary>+ Ajouter ou modifier</summary>
  <form method="post" action="<?= $action('/equipe') ?>" class="formulaire">
    <?= csrf_field() ?>
    <div class="champ"><label for="champ-email">Membre</label>
      <input type="email" id="champ-email" name="email" list="liste-membres" required autocomplete="off" placeholder="Tapez un nom ou une adresse">
      <small>La liste se filtre pendant la saisie.</small></div>
    <datalist id="liste-membres"><?php foreach ($membres_actifs as $m): ?><option value="<?= e($m['email']) ?>"><?= e(trim($m['prenom'] . ' ' . $m['nom']) ?: $m['email']) ?></option><?php endforeach ?></datalist>
    <?= c_champ('niveau', 'Niveau', ['type' => 'select', 'valeur' => 'membre', 'choix' => ESPACE_NIVEAUX]) ?>
    <?= c_champ('libelle', 'Libellé affiché (facultatif)', ['aide' => 'Ex. « Bureau : Président » ; plusieurs rôles séparés par « ; ». Repris tel quel dans les pages qui affichent cette équipe.', 'max' => 100]) ?>
    <button type="submit" class="bouton bouton-secondaire">Enregistrer</button>
  </form>
  <?php if ($equipe): ?>
  <form method="post" action="<?= $action('/equipe/retirer') ?>" class="en-ligne retirer-equipe">
    <?= csrf_field() ?>
    <label for="retirer-membre" class="petit">Retirer de l'équipe</label>
    <select id="retirer-membre" name="membre_id"><?php foreach ($equipe as $personne): ?><option value="<?= (int) $personne['id'] ?>"><?= e($nom_court($personne)) ?></option><?php endforeach ?></select>
    <button type="submit" class="lien-bouton lien-danger" data-confirmer="Retirer cette personne de l'équipe ?">retirer</button>
  </form>
  <?php endif ?>
  </details>
  <?php endif ?>
</section>
<?php endif ?>

<?php if ($enfants): ?>
<section class="sous-espaces">
  <h2>Sous-espaces</h2>
  <ul class="lignes-espaces"><?php foreach ($enfants as $enfant): ?><li><a href="<?= e(url('/espace/' . $enfant['slug'])) ?>"><span class="ligne-espace-corps"><strong><?= e($enfant['titre']) ?></strong></span><?= c_meteo($enfant['meteo']) ?></a></li><?php endforeach ?></ul>
</section>
<?php endif ?>

</aside>
</div>

<?php if ($peut_gerer): ?>
<details class="separe gerer-espace"><summary>Gérer l'espace</summary>
  <p class="petit">Le titre, la description et la visibilité se modifient d'un clic, directement en haut de la page.</p>
  <h3>Type et emplacement</h3>
  <form method="post" action="<?= $action('/modifier') ?>" class="formulaire">
    <?= $garder(['type']) ?>
    <?= c_champ('type', 'Type', ['type' => 'select', 'valeur' => $espace['type'], 'choix' => ESPACE_TYPES]) ?>
    <?php if (is_admin($membre)): ?>
    <?= c_champ('parent', 'Dans l\'espace', ['type' => 'select', 'valeur' => $parent['slug'] ?? '', 'choix' => ['' => '— Au premier niveau'] + $parents_possibles]) ?>
    <?php endif ?>
    <button type="submit" class="bouton bouton-secondaire">Enregistrer</button>
  </form>
  <p class="en-ligne">
    <?= c_bouton('Créer un sous-espace', '/espaces/nouveau?parent=' . $espace['slug'], 'secondaire') ?>
    <?php if (!$espace['archive']): ?>
    <form method="post" action="<?= $action('/archiver') ?>" class="en-ligne"><?= csrf_field() ?><button type="submit" class="bouton bouton-secondaire" data-confirmer="Archiver « <?= e($espace['titre']) ?> » ? Il sortira des listes et de l'agenda, sans rien perdre.">Archiver l'espace</button></form>
    <?php endif ?>
  </p>
  <?php if (is_admin($membre)): ?>
  <div class="zone-sensible">
    <h3>Zone sensible</h3>
    <form method="post" action="<?= $action('/supprimer') ?>" class="formulaire">
      <?= csrf_field() ?>
      <?= c_champ('confirmation', 'Pour supprimer définitivement l\'espace, ses dates, ses fichiers et ses inscriptions, tapez son nom : ' . $espace['titre'], ['requis' => true, 'max' => 200]) ?>
      <button type="submit" class="lien-bouton lien-danger">Supprimer définitivement</button>
    </form>
  </div>
  <?php endif ?>
</details>
<?php endif ?>

<?php if ($journal): ?>
<details class="separe"><summary>Journal</summary>
  <ul class="liste journal">
    <?php foreach ($journal as $ligne): ?>
    <li><time datetime="<?= e($ligne['le']) ?>"><?= e(date('d/m/Y H:i', strtotime($ligne['le']))) ?></time> · <?= e($ligne['email'] ? membre_nom($ligne) : 'Système') ?> · <?= e($ligne['action']) ?><?= $ligne['via'] === 'ia' ? ' <span class="badge">via IA</span>' : '' ?></li>
    <?php endforeach ?>
  </ul>
</details>
<?php endif ?>
