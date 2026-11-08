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
<?php if ($peut_gerer): ?><p class="bouton-gerer"><?= c_bouton('⚙ Gérer', '/espace/' . $espace['slug'] . '/gerer', 'secondaire') ?></p><?php endif ?>
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
  <?php $auto = !empty($espace['meteo_auto']); $raison = $auto && $espace['meteo_raison'] !== '' ? $espace['meteo_raison'] : ''; ?>
  <details class="meteo-menu">
    <summary aria-label="Changer la météo"><?= c_meteo($espace['meteo']) ?><?php if ($auto): ?><span class="meteo-auto-marque">auto</span><?php endif ?><span class="meteo-fleche" aria-hidden="true">▾</span></summary>
    <div class="meteo-options">
      <form method="post" action="<?= $action('/meteo-auto') ?>" class="meteo-auto-choix"><?= csrf_field() ?>
        <input type="hidden" name="meteo_auto" value="<?= $auto ? '' : '1' ?>">
        <button type="submit" aria-pressed="<?= $auto ? 'true' : 'false' ?>"><span class="case-auto" aria-hidden="true"></span><span><strong>Météo automatique</strong><small>D'après le responsable et les dates clés</small></span></button>
      </form>
      <form method="post" action="<?= $action('/meteo') ?>">
        <?= csrf_field() ?>
        <p class="petit"><?= $auto ? 'Ou choisir à la main (coupe le mode automatique) :' : 'Choisir à la main :' ?></p>
        <?php foreach (['soleil', 'eclaircie', 'pluie', 'orage'] as $cle): ?>
        <button type="submit" name="meteo" value="<?= $cle ?>" aria-pressed="<?= $espace['meteo'] === $cle && !$auto ? 'true' : 'false' ?>"><?= c_meteo($cle) ?></button>
        <?php endforeach ?>
      </form>
    </div>
  </details>
  <?php if ($raison !== ''): ?><span class="meteo-raison discret"><?= e(ucfirst($raison)) ?></span><?php endif ?>
  <?php else: ?>
  <?= c_meteo($espace['meteo']) ?><?php if (!empty($espace['meteo_auto']) && $espace['meteo_raison'] !== ''): ?><span class="meteo-raison discret"><?= e(ucfirst($espace['meteo_raison'])) ?></span><?php endif ?>
  <?php endif ?>
  <?php if ($peut_gerer): ?>
  <span class="en-place en-place-ligne" data-en-place>
    <span class="en-place-vue discret" tabindex="0" role="button" aria-label="Modifier qui peut voir l'espace"><?= c_icone(['public' => 'reseau', 'adherents' => 'personnes', 'equipe' => 'cadenas'][$espace['visibilite']] ?? 'personnes') ?><?= e(ESPACE_VISIBILITES[$espace['visibilite']]) ?><span class="en-place-crayon"><?= c_icone('crayon') ?></span></span>
    <form method="post" action="<?= $action('/modifier') ?>" class="en-place-form"><?= $garder(['visibilite']) ?>
      <?= c_visibilite_choix($espace['visibilite'], ESPACE_VISIBILITES, $espace['type'] === 'evenement' && $parent ? $parent['titre'] : null, 'sur-place-visibilite') ?><?= $boutons ?></form>
  </span>
  <?php else: ?>
  <span class="discret visibilite-mention"><?= c_icone(['public' => 'reseau', 'adherents' => 'personnes', 'equipe' => 'cadenas'][$espace['visibilite']] ?? 'personnes') ?><?= e(ESPACE_VISIBILITES[$espace['visibilite']]) ?></span>
  <?php endif ?>
</div>

<?php if ($alertes && $peut_contribuer): ?>
<div class="a-faire" role="status"><strong><?= c_icone('attention') ?>À faire</strong>
  <ul><?php foreach ($alertes as $alerte): $cible = $cible_alerte($alerte); $verbe_alerte = match ($cible) { '#ajout-equipe' => 'Choisir', '#fichiers' => 'Déposer', default => 'Voir' }; ?>
    <li><span><?= e($alerte) ?></span><?php if ($cible): ?><a class="bouton bouton-secondaire" href="<?= $cible ?>"<?= $cible === '#ajout-equipe' ? ' data-dialogue-equipe' : '' ?>><?= $verbe_alerte ?></a><?php endif ?></li><?php endforeach ?></ul>
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
  <?php // Rangée par groupes comme dans les pages (« Bureau : Président ») ; le niveau (n°1, n°2) reste indiqué, il donne les droits
  $groupes_equipe = espace_equipe_groupes($equipe); $plusieurs = count($groupes_equipe) > 1; ?>
  <?php foreach ($groupes_equipe as $groupe => $liste): ?>
  <?php if ($plusieurs): ?><h3 class="groupe-equipe"><?= e($groupe) ?></h3><?php endif ?>
  <ul class="equipe-liste">
    <?php foreach ($liste as ['personne' => $personne, 'role' => $role]): $niv = ['n1' => 'N°1', 'n2' => 'N°2', 'associe' => 'Associé'][$personne['niveau']] ?? ''; ?>
    <li><span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($nom_court($personne), 0, 1))) ?></span><span class="qui"><strong title="<?= e($personne['email']) ?>"><?= e($nom_court($personne)) ?></strong>
      <span class="discret"><?= e(implode(' · ', array_filter([$niv, $role !== '' ? $role : ($niv === '' ? 'Membre' : '')]))) ?></span></span>
      <?php if ($peut_gerer): ?><button type="button" class="equipe-modifier" data-dialogue-equipe data-id="<?= (int) $personne['id'] ?>" data-email="<?= e($personne['email']) ?>" data-nom="<?= e($nom_court($personne)) ?>" data-niveau="<?= e($personne['niveau']) ?>" data-groupe="<?= e(str_contains($personne['libelle'], ':') && !str_contains($personne['libelle'], ';') ? trim(explode(':', $personne['libelle'], 2)[0]) : '') ?>" data-role="<?= e(str_contains($personne['libelle'], ':') && !str_contains($personne['libelle'], ';') ? trim(explode(':', $personne['libelle'], 2)[1]) : $personne['libelle']) ?>" aria-label="Modifier <?= e($nom_court($personne)) ?>" title="Modifier"><?= c_icone('crayon') ?></button><?php endif ?></li>
    <?php endforeach ?>
  </ul>
  <?php endforeach ?>
  <?php if ($peut_gerer): ?>
  <p><button type="button" class="bouton bouton-secondaire" id="ajout-equipe" data-dialogue-equipe>+ Ajouter une personne</button></p>
  <?php // Ajouter ou modifier une personne : une petite fenêtre (sans JavaScript, elle s'affiche dépliée sous la liste) ?>
  <dialog class="dialogue" id="dialogue-equipe" aria-labelledby="dialogue-equipe-titre">
  <form method="post" action="<?= $action('/equipe') ?>" class="formulaire">
    <?= csrf_field() ?>
    <h3 id="dialogue-equipe-titre" data-titre-ajout="Ajouter une personne">Ajouter une personne</h3>
    <div class="champ" data-champ-email><label for="champ-email">Membre</label>
      <input type="email" id="champ-email" name="email" list="liste-membres" required autocomplete="off" placeholder="Tapez un nom ou une adresse">
      <small>La liste se filtre pendant la saisie.</small></div>
    <datalist id="liste-membres"><?php foreach ($membres_actifs as $m): ?><option value="<?= e($m['email']) ?>"><?= e(trim($m['prenom'] . ' ' . $m['nom']) ?: $m['email']) ?></option><?php endforeach ?></datalist>
    <div class="champ-avec-aide"><?= c_champ('niveau', 'Niveau', ['type' => 'select', 'valeur' => 'membre', 'choix' => ESPACE_NIVEAUX]) ?><?= aide_niveaux_equipe() ?></div>
    <?php $groupes_connus = array_values(array_filter(array_keys($groupes_equipe), fn($g) => !in_array($g, ['Membres', 'Associés'], true))); ?>
    <div class="champ"><label for="champ-groupe">Groupe <span class="discret">(facultatif)</span></label>
      <input id="champ-groupe" name="groupe" list="liste-groupes" maxlength="40" autocomplete="off" placeholder="Ex. Bureau, Chargés de mission">
      <small>Les personnes d'un même groupe sont présentées ensemble, ici et dans les pages. Sans groupe : « Membres ».</small></div>
    <datalist id="liste-groupes"><?php foreach (array_unique(array_merge($groupes_connus, ['Bureau', 'Chargés de mission'])) as $g): ?><option value="<?= e($g) ?>"><?php endforeach ?></datalist>
    <?= c_champ('role', 'Rôle (facultatif)', ['aide' => 'Ex. Président, Trésorière, Trinôme académique.', 'max' => 60]) ?>
    <p class="dialogue-actions"><button type="submit" class="bouton bouton-principal">Enregistrer</button> <button type="button" class="lien-bouton" data-fermer-dialogue>Annuler</button></p>
  </form>
  <?php if ($equipe): ?>
  <form method="post" action="<?= $action('/equipe/retirer') ?>" class="retirer-equipe" data-retirer>
    <?= csrf_field() ?>
    <label for="retirer-membre" class="petit">Retirer de l'équipe</label>
    <select id="retirer-membre" name="membre_id"><?php foreach ($equipe as $personne): ?><option value="<?= (int) $personne['id'] ?>"><?= e($nom_court($personne)) ?></option><?php endforeach ?></select>
    <button type="submit" class="lien-bouton lien-danger" data-confirmer="Retirer cette personne de l'équipe ?">Retirer de l'équipe</button>
  </form>
  <?php endif ?>
  </dialog>
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
