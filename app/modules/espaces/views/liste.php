<?php
// Espaces : « Mes espaces » en cartes, les autres en lignes compactes (météo, responsable) ; les événements sont dans l'agenda.
// Pour qui gère au moins un espace (ou l'administrateur) : vue d'ensemble par météo, du plus bloqué au plus à jour.
$espaces = array_values(array_filter($espaces, fn($e) => $e['type'] !== 'evenement'));
$ids = array_map(fn($e) => (int) $e['id'], $espaces);
$responsables = [];
$prochaines = [];
if ($ids) {
    $place = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all("SELECT em.espace_id, m.prenom, m.nom, m.email FROM espace_membre em JOIN membre m ON m.id = em.membre_id WHERE em.niveau = 'n1' AND em.espace_id IN ($place)", $ids) as $r) {
        $responsables[(int) $r['espace_id']] ??= membre_nom_affiche($r);
    }
    foreach (db_all("SELECT espace_id, MIN(date_jalon) AS d FROM jalon WHERE fait = 0 AND date_jalon >= CURDATE() AND espace_id IN ($place) GROUP BY espace_id", $ids) as $r) {
        $prochaines[(int) $r['espace_id']] = db_one('SELECT * FROM jalon WHERE espace_id = ? AND fait = 0 AND date_jalon = ? LIMIT 1', [$r['espace_id'], $r['d']]);
    }
}
$mes_ids = $membre ? array_map('intval', array_column(db_all('SELECT espace_id FROM espace_membre WHERE membre_id = ?', [$membre['id']]), 'espace_id')) : [];
$gere_quelque_chose = $membre !== null && (is_admin($membre) || db_value('SELECT COUNT(*) FROM espace_membre WHERE membre_id = ? AND niveau IN ("n1", "n2")', [$membre['id']]) > 0);
$gravite = ['orage' => 0, 'pluie' => 1, 'eclaircie' => 2, 'soleil' => 3];
$meteo_libelles = ['orage' => 'Bloqué', 'pluie' => 'En retard', 'eclaircie' => 'En cours', 'soleil' => 'À jour'];
$filtre_type = in_array($_GET['type'] ?? '', ['commission', 'projet'], true) ? $_GET['type'] : '';
$filtre_meteo = isset($gravite[$_GET['meteo'] ?? '']) ? $_GET['meteo'] : '';
$compte_meteo = array_count_values(array_column($espaces, 'meteo'));
$visibles = array_values(array_filter($espaces, fn($e) => ($filtre_type === '' || ($filtre_type === 'commission' ? in_array($e['type'], ['comite', 'commission'], true) : $e['type'] === 'projet'))
    && ($filtre_meteo === '' || $e['meteo'] === $filtre_meteo)));
if ($gere_quelque_chose) {
    usort($visibles, fn($a, $b) => [$gravite[$a['meteo']] ?? 9, $a['titre']] <=> [$gravite[$b['meteo']] ?? 9, $b['titre']]);
}
$miens = array_values(array_filter($visibles, fn($e) => in_array((int) $e['id'], $mes_ids, true)));
$autres = array_values(array_filter($visibles, fn($e) => !in_array((int) $e['id'], $mes_ids, true)));
$icones = ['comite' => 'drapeau', 'commission' => 'personnes', 'projet' => 'dossier'];
$lien_filtre = function (array $changer) use ($filtre_type, $filtre_meteo): string {
    $q = array_filter(array_merge(['type' => $filtre_type, 'meteo' => $filtre_meteo], $changer), fn($v) => $v !== '');
    return url('/espaces') . ($q ? '?' . http_build_query($q) : '');
};
?>
<div class="titre-avec-action">
  <div>
    <h1><?= $archives ? 'Espaces archivés' : 'Espaces' ?></h1>
    <?php if (!$archives): ?><p class="discret chapeau">Les comités, commissions et projets de l'association.<?php if ($agenda = page_agenda_slug()): ?> Les événements sont dans l'<a href="<?= e(url('/page/' . $agenda)) ?>">agenda</a>.<?php endif ?></p><?php endif ?>
  </div>
  <?php if (is_admin($membre) && !$archives): ?><?= c_bouton('+ Nouvel espace', '/espaces/nouveau') ?><?php endif ?>
</div>
<?php if ($archives): ?><p><a href="<?= e(url('/espaces')) ?>">← Espaces en cours</a></p><?php endif ?>

<?php if ($gere_quelque_chose && !$archives && $espaces): ?>
<p class="surtitre">Vue d'ensemble · touchez pour filtrer</p>
<ul class="ensemble-meteo">
  <?php foreach ($meteo_libelles as $cle => $libelle): $actif = $filtre_meteo === $cle; ?>
  <li><a class="meteo-compteur meteo-<?= $cle ?><?= $actif ? ' actif' : '' ?>" href="<?= e($lien_filtre(['meteo' => $actif ? '' : $cle])) ?>"<?= $actif ? ' aria-current="true"' : '' ?>>
    <strong><?= (int) ($compte_meteo[$cle] ?? 0) ?></strong><?= c_icone($cle) ?><span><?= e($libelle) ?></span></a></li>
  <?php endforeach ?>
</ul>
<?php endif ?>

<?php if (!$archives && $espaces): ?>
<p class="segments segments-filtre" role="group" aria-label="Type d'espace">
  <?php foreach (['' => 'Tous', 'commission' => 'Commissions', 'projet' => 'Projets'] as $cle => $libelle): ?>
  <a href="<?= e($lien_filtre(['type' => $cle])) ?>"<?= $filtre_type === $cle ? ' aria-current="true"' : '' ?>><?= e($libelle) ?></a>
  <?php endforeach ?>
</p>
<?php endif ?>

<?php if (!$espaces): ?><p>Aucun espace pour l'instant.</p>
<?php elseif (!$visibles): ?><p class="vide"><?= c_icone('dossier') ?><span>Aucun espace ne correspond. <a href="<?= e(url('/espaces')) ?>">Tout afficher</a></span></p><?php endif ?>

<?php if ($miens): ?>
<section class="espaces-miens">
  <h2>Mes espaces</h2>
  <ul class="cartes-espaces">
    <?php foreach ($miens as $es): $alertes = espace_peut_contribuer($membre, $es) ? espace_alertes($es) : []; $j = $prochaines[(int) $es['id']] ?? null; ?>
    <li><a class="carte-espace" href="<?= e(url('/espace/' . $es['slug'])) ?>">
      <span class="tuile-type type-<?= e($es['type']) ?>"><?= c_icone($icones[$es['type']] ?? 'dossier') ?></span>
      <span class="carte-espace-corps"><strong><?= e($es['titre']) ?></strong><?= c_meteo($es['meteo']) ?>
        <?php if ($j): ?><span class="petit"><?= c_icone('calendrier') ?><?= e(date('d/m', strtotime($j['date_jalon']))) ?> · <?= e($j['libelle']) ?></span><?php endif ?>
        <?php if ($alertes): ?><span class="alertes-mini"><?= c_icone('attention') ?><?= e(count($alertes) === 1 ? $alertes[0] : count($alertes) . ' points à traiter') ?></span><?php endif ?>
      </span></a></li>
    <?php endforeach ?>
  </ul>
</section>
<?php endif ?>

<?php if ($autres): $groupes = ['commission' => ['Commissions', fn($e) => in_array($e['type'], ['comite', 'commission'], true)], 'projet' => ['Projets', fn($e) => $e['type'] === 'projet']]; ?>
<div class="espaces-groupes">
  <?php foreach ($groupes as [$intitule, $garde]): $groupe = array_values(array_filter($autres, $garde)); if (!$groupe) { continue; } ?>
  <section>
    <h2><?= e($intitule) ?></h2>
    <ul class="lignes-espaces">
      <?php foreach ($groupe as $es): $resp = $responsables[(int) $es['id']] ?? null; ?>
      <li><a href="<?= e(url('/espace/' . $es['slug'])) ?>">
        <span class="tuile-type type-<?= e($es['type']) ?>"><?= c_icone($icones[$es['type']] ?? 'dossier') ?></span>
        <span class="ligne-espace-corps"><strong><?= e($es['titre']) ?></strong><?= c_meteo($es['meteo']) ?>
          <span class="<?= $resp ? 'discret' : 'sans-responsable' ?>"><?= $resp ? 'Responsable : ' . e($resp) : 'Pas encore de responsable' ?></span></span>
        <span class="chevron" aria-hidden="true">›</span></a></li>
      <?php endforeach ?>
    </ul>
  </section>
  <?php endforeach ?>
</div>
<?php endif ?>

<?php if (!$archives && $nb_archives > 0): ?><p class="lien-archives"><a href="<?= e(url('/espaces?archives=1')) ?>"><?= c_icone('archive') ?>Espaces archivés (<?= $nb_archives ?>)</a></p><?php endif ?>
