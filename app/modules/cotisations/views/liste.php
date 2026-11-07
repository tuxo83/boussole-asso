<?php
$a_jour = count(array_filter($membres, fn($m) => $m['paye_le'] !== null));
$total = count($membres);
$libelle = cotisation_periode_libelle($periode);
$actuelle = cotisation_periode();
?>
<h1>Cotisations <?= e($libelle) ?></h1>
<nav class="en-ligne" aria-label="Période">
  <a class="bouton bouton-secondaire" href="<?= e(url('/cotisations?periode=' . ($periode - 1))) ?>">← <?= e(cotisation_periode_libelle($periode - 1)) ?></a>
  <?php if ($periode < $actuelle): ?><a class="bouton bouton-secondaire" href="<?= e(url('/cotisations?periode=' . ($periode + 1))) ?>"><?= e(cotisation_periode_libelle($periode + 1)) ?> →</a><?php endif ?>
</nav>

<div class="jauge" role="img" aria-label="<?= $a_jour ?> membres à jour sur <?= $total ?>">
  <div class="jauge-barre"><span style="width: <?= $total ? round(100 * $a_jour / $total) : 0 ?>%"></span></div>
  <p><strong><?= $a_jour ?></strong> à jour sur <?= $total ?> membres actifs</p>
</div>

<?php if ($import): ?>
<div class="alerte alerte-succes" role="status">
  <p><strong>Import HelloAsso :</strong> <?= (int) $import['marques'] ?> cotisation(s) enregistrée(s), <?= (int) $import['deja'] ?> déjà à jour<?= !empty($import['crees']) ? ', ' . (int) $import['crees'] . ' nouvelle(s) adhésion(s) en attente de validation' : '' ?><?= !empty($import['doublons']) ? ', ' . (int) $import['doublons'] . ' paiement(s) en double à vérifier' : '' ?>.</p>
  <?php if ($import['inconnus']): ?><p>Lignes illisibles (date manquante) : <?= e(implode(', ', $import['inconnus'])) ?></p><?php endif ?>
</div>
<?php endif ?>

<div class="grille">
  <article class="carte">
    <h3>Importer HelloAsso</h3>
    <p class="petit">Fichier CSV exporté depuis HelloAsso. Seuls l'e-mail et la date sont lus.</p>
    <form method="post" action="<?= e(url('/cotisations/import')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="periode" value="<?= $periode ?>">
      <input type="file" name="fichier" accept=".csv,text/csv" required aria-label="Fichier CSV HelloAsso">
      <p><button type="submit" class="bouton bouton-secondaire">Importer</button></p>
    </form>
  </article>
  <article class="carte">
    <h3>Relancer</h3>
    <p class="petit"><?= $total - $a_jour ?> membre(s) pas encore à jour. Relance automatique : <?= (int) setting('cotisation_relance_jours', '30') > 0 ? (int) setting('cotisation_relance_jours', '30') . ' jours après le début de la période' : 'désactivée' ?>.</p>
    <form method="post" action="<?= e(url('/cotisations/relancer')) ?>"><?= csrf_field() ?><input type="hidden" name="periode" value="<?= $periode ?>">
      <button type="submit" class="bouton bouton-secondaire" data-confirmer="Envoyer un e-mail de relance aux <?= $total - $a_jour ?> membre(s) pas à jour ?"<?= $a_jour === $total ? ' disabled' : '' ?>>Relancer par e-mail</button></form>
    <p><a href="<?= e(url('/cotisations/export?periode=' . $periode)) ?>">Exporter qui a cotisé (CSV)</a></p>
  </article>
  <article class="carte">
    <h3>Données à effacer</h3>
    <p><?= c_meteo(cotisation_meteo_effacement($effacer)) ?></p>
    <p class="petit"><?= $effacer ? count($effacer) . ' personne(s) sans cotisation depuis plus de ' . (int) setting('cotisation_conservation_ans', '2') . ' ans.' : 'Rien à effacer.' ?></p>
    <?php if ($effacer): ?><p><a class="bouton bouton-secondaire" href="#effacement">Voir et valider</a></p><?php endif ?>
  </article>
</div>

<?php if ($nb_attente): ?><p class="alerte alerte-info"><?= $nb_attente ?> adhésion(s) payée(s) en attente de validation : <a href="<?= e(url('/admin/membres')) ?>">Membres › En attente</a>.</p><?php endif ?>
<?php if ($doublons): ?>
<section class="statut-inscription statut-a-regler">
  <h2>Paiements en double à vérifier (<?= count($doublons) ?>)</h2>
  <p class="petit">Ces personnes étaient déjà à jour quand un nouveau paiement est arrivé. Rien n'a été écrasé : vérifiez, remboursez si besoin sur HelloAsso, puis marquez « traité ».</p>
  <ul class="liste">
    <?php foreach ($doublons as $d): ?>
    <li class="candidat"><span class="qui"><strong><?= e(membre_nom($d)) ?></strong><span class="discret"><?= e(cotisation_periode_libelle((int) $d['periode'])) ?> : déjà payé le <?= e($d['premier_le'] ? date('d/m/Y', strtotime($d['premier_le'])) : '?') ?> (<?= e(COTISATION_MOYENS[$d['premier_moyen']] ?? '?') ?>), puis le <?= e(date('d/m/Y', strtotime($d['paye_le']))) ?> (<?= e(COTISATION_MOYENS[$d['moyen']]) ?>)</span></span>
      <form method="post" action="<?= e(url('/cotisations/doublon')) ?>"><?= csrf_field() ?><input type="hidden" name="membre_id" value="<?= (int) $d['membre_id'] ?>"><input type="hidden" name="periode" value="<?= (int) $d['periode'] ?>"><input type="hidden" name="paye_le" value="<?= e($d['paye_le']) ?>"><button type="submit" class="bouton bouton-secondaire">Traité</button></form></li>
    <?php endforeach ?>
  </ul>
</section>
<?php endif ?>

<h2>Membres</h2>
<p><input type="search" class="filtre" data-filtre="#liste-cotisations" placeholder="Rechercher un nom ou un e-mail" aria-label="Rechercher un membre"></p>
<ul class="liste" id="liste-cotisations">
  <?php foreach ($membres as $m): ?>
  <li class="candidat ligne-cotisation" id="m<?= (int) $m['id'] ?>" data-texte="<?= e(mb_strtolower(membre_nom($m) . ' ' . $m['email'])) ?>">
    <span class="qui"><strong><?= e(membre_nom($m)) ?></strong><span class="discret"><?= e($m['email']) ?></span></span>
    <?php if ($m['paye_le'] !== null): ?>
    <span class="en-ligne"><span class="badge badge-ok">Payé le <?= e(date('d/m/Y', strtotime($m['paye_le']))) ?> · <?= e(COTISATION_MOYENS[$m['moyen']]) ?></span>
      <?php if ($m['saisi_email']): ?><span class="discret">noté par <?= e(membre_nom(['prenom' => $m['saisi_prenom'], 'nom' => $m['saisi_nom'], 'email' => $m['saisi_email']])) ?></span><?php endif ?>
      <form method="post" action="<?= e(url('/cotisations/annuler')) ?>"><?= csrf_field() ?><input type="hidden" name="periode" value="<?= $periode ?>"><input type="hidden" name="membre_id" value="<?= (int) $m['id'] ?>"><button type="submit" class="lien-bouton" data-confirmer="Annuler la cotisation de <?= e(membre_nom($m)) ?> ?">annuler</button></form></span>
    <?php else: ?>
    <form method="post" action="<?= e(url('/cotisations/marquer')) ?>" class="en-ligne">
      <?= csrf_field() ?><input type="hidden" name="periode" value="<?= $periode ?>"><input type="hidden" name="membre_id" value="<?= (int) $m['id'] ?>">
      <?php if ($m['envoyee_le']): ?><span class="discret">relancé le <?= e(date('d/m', strtotime($m['envoyee_le']))) ?></span><?php endif ?>
      <select name="moyen" aria-label="Moyen de paiement de <?= e(membre_nom($m)) ?>"><?php foreach (COTISATION_MOYENS as $cle => $nom): ?><option value="<?= $cle ?>"<?= $cle === 'cheque' ? ' selected' : '' ?>><?= $nom ?></option><?php endforeach ?></select>
      <input type="date" name="paye_le" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" aria-label="Date du paiement de <?= e(membre_nom($m)) ?>">
      <button type="submit" class="bouton bouton-principal">Payé</button>
    </form>
    <?php endif ?>
  </li>
  <?php endforeach ?>
</ul>

<?php if ($effacer): ?>
<section id="effacement" class="separe">
  <h2>Données à effacer <?= c_meteo(cotisation_meteo_effacement($effacer)) ?></h2>
  <p>Ces personnes n'ont pas cotisé depuis plus de <?= (int) setting('cotisation_conservation_ans', '2') ?> ans. Leur compte et tout ce qui s'y rattache (inscriptions, cotisations, clés) seront supprimés, et leur nom retiré du journal. <strong>C'est définitif.</strong></p>
  <p><a class="bouton bouton-secondaire" href="<?= e(url('/cotisations/export-effacement')) ?>">Exporter d'abord la liste (CSV)</a></p>
  <form method="post" action="<?= e(url('/cotisations/effacer')) ?>">
    <?= csrf_field() ?>
    <?php foreach ($effacer as $m): ?>
    <label class="case candidat"><span class="qui"><input type="checkbox" name="membres[]" value="<?= (int) $m['id'] ?>" checked> <?= e(membre_nom($m)) ?></span>
      <span class="discret"><?= $m['derniere'] !== null ? 'dernière cotisation ' . e(cotisation_periode_libelle((int) $m['derniere'])) : 'jamais cotisé' ?><?= $m['actif'] ? '' : ' · compte désactivé' ?></span></label>
    <?php endforeach ?>
    <p><button type="submit" class="bouton bouton-danger" data-confirmer="Effacer définitivement les personnes cochées ?">Effacer les personnes cochées</button></p>
  </form>
</section>
<?php endif ?>

<details class="separe" id="helloasso"><summary>Connexion HelloAsso (facultative)</summary>
  <?php if (helloasso_actif()): ?>
  <p><?= setting('helloasso_erreur', '') !== '' ? '<span class="badge">À vérifier</span> ' . e((string) setting('helloasso_erreur')) : '<span class="badge badge-ok">Active</span> ' . (setting('helloasso_reussie', '') !== '' ? 'dernière lecture le ' . e(date('d/m/Y à H:i', strtotime((string) setting('helloasso_reussie')))) : 'pas encore lue') ?></p>
  <form method="post" action="<?= e(url('/cotisations/helloasso/synchroniser')) ?>"><?= csrf_field() ?><button type="submit" class="bouton bouton-secondaire">Lire HelloAsso maintenant</button></form>
  <p class="petit">Pour une mise à jour immédiate à chaque paiement, collez cette adresse dans HelloAsso, « Mon compte › Intégrations et API › notifications » :<br><code class="cle"><?= e(absolute_url('/helloasso/notification/' . setting('helloasso_notification_cle', ''))) ?></code></p>
  <?php else: ?>
  <p class="petit">Sans connexion, utilisez l'import du fichier CSV. Avec : les paiements HelloAsso cochent « payé » tout seuls, toutes les quelques minutes. Seuls l'e-mail et la date sont lus.</p>
  <?php endif ?>
  <form method="post" action="<?= e(url('/cotisations/helloasso')) ?>" class="formulaire">
    <?= csrf_field() ?>
    <?= c_champ('helloasso_organisation', "Nom de l'association dans HelloAsso", ['valeur' => (string) setting('helloasso_organisation', ''), 'aide' => 'Ce qui suit /associations/ dans l\'adresse de votre page HelloAsso (vous pouvez coller l\'adresse entière).']) ?>
    <?= c_champ('helloasso_adhesion', "Formulaire d'adhésion", ['valeur' => (string) setting('helloasso_adhesion', ''), 'aide' => 'Ce qui suit /adhesions/ dans l\'adresse du formulaire (ou l\'adresse entière).']) ?>
    <?= c_champ('helloasso_client_id', 'Identifiant (client id)', ['valeur' => (string) setting('helloasso_client_id', ''), 'aide' => 'HelloAsso › Mon compte › Intégrations et API.']) ?>
    <?= c_champ('helloasso_secret', 'Secret (client secret)', ['type' => 'password', 'aide' => setting('helloasso_secret', '') !== '' ? 'Enregistré (chiffré). Laisser vide pour le garder.' : 'Gardé chiffré.']) ?>
    <?= c_champ('helloasso_intervalle', 'Lire HelloAsso toutes les (minutes)', ['type' => 'number', 'valeur' => (string) setting('helloasso_intervalle', '10')]) ?>
    <?php if (helloasso_actif()): ?><label class="case"><input type="checkbox" name="desactiver" value="1"> Désactiver la connexion (efface le secret)</label><?php endif ?>
    <button type="submit" class="bouton bouton-secondaire">Enregistrer</button>
  </form>
</details>

<details><summary>Réglages des cotisations</summary>
<form method="post" action="<?= e(url('/cotisations/reglages')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= c_champ('cotisation_mois_debut', 'La période de cotisation commence en', ['type' => 'select', 'valeur' => (string) cotisation_mois_debut(), 'choix' => array_combine(range(1, 12), ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'])]) ?>
  <?= c_champ('cotisation_relance_jours', 'Relance automatique, en jours après le début de la période (0 = jamais)', ['type' => 'number', 'valeur' => (string) setting('cotisation_relance_jours', '30')]) ?>
  <?= c_champ('lien_adhesion', "Lien d'adhésion HelloAsso (formulaire public)", ['type' => 'url', 'valeur' => (string) setting('lien_adhesion', ''), 'aide' => 'Affiché par le bloc « Adhérer » des pages et dans Mon compte. Les paiements lus créent un compte en attente pour les nouveaux.']) ?>
  <?= c_champ('cotisation_conservation_ans', 'Effacer les données après combien d\'années sans cotisation', ['type' => 'number', 'valeur' => (string) setting('cotisation_conservation_ans', '2')]) ?>
  <button type="submit" class="bouton bouton-secondaire">Enregistrer</button>
</form>
</details>
