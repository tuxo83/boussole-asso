<h1><?= $archives ? 'Espaces archivés' : 'Espaces' ?></h1>
<?php if ($archives): ?><p><a href="<?= e(url('/espaces')) ?>">← Espaces en cours</a></p><?php elseif ($nb_archives > 0): ?><p class="petit"><a href="<?= e(url('/espaces?archives=1')) ?>">Espaces archivés (<?= $nb_archives ?>)</a></p><?php endif ?>
<?php if (is_admin($membre)): ?><p><?= c_bouton('Nouvel espace', '/espaces/nouveau') ?></p><?php endif ?>
<?php if (!$espaces): ?><p>Aucun espace pour l'instant.</p><?php endif ?>
<?php
// Mes espaces d'abord (ceux dont je fais partie de l'équipe), puis les autres rangés par type
$carte = function (array $espace, bool $avec_type = false): string {
    // Ce qui est à traiter : seulement sur mes espaces, là où je peux agir
    $alertes = $avec_type ? espace_alertes($espace) : [];
    $n1 = db_one('SELECT m.* FROM espace_membre em JOIN membre m ON m.id = em.membre_id WHERE em.espace_id = ? AND em.niveau = "n1"', [$espace['id']]);
    $prochaine = db_one('SELECT * FROM jalon WHERE espace_id = ? AND fait = 0 AND date_jalon >= CURDATE() ORDER BY date_jalon LIMIT 1', [$espace['id']]);
    return c_carte($espace['titre'],
        '<p class="carte-espace-ligne">' . ($avec_type ? '<span class="discret">' . e(ESPACE_TYPES[$espace['type']]) . '</span>' : '') . c_meteo($espace['meteo']) . '</p>'
        . ($n1 ? '<p class="petit">N°1 : ' . e(membre_nom_affiche($n1)) . '</p>' : '')
        . ($prochaine ? '<p class="petit">' . c_icone('calendrier') . ' ' . e(date('d/m/Y', strtotime($prochaine['date_jalon']))) . ' · ' . e($prochaine['libelle']) . '</p>' : '')
        . ($alertes ? '<p class="alertes-mini">' . c_icone('attention') . e(count($alertes) === 1 ? $alertes[0] : count($alertes) . ' points à traiter') . '</p>' : ''),
        '/espace/' . $espace['slug']);
};
$mes_ids = $membre ? array_map('intval', array_column(db_all('SELECT espace_id FROM espace_membre WHERE membre_id = ?', [$membre['id']]), 'espace_id')) : [];
$miens = array_filter($espaces, fn($e) => in_array((int) $e['id'], $mes_ids, true));
$autres = array_filter($espaces, fn($e) => !in_array((int) $e['id'], $mes_ids, true));
// Les événements ont leur place dans l'agenda (et dans leur espace s'ils y sont rattachés)
$pluriels = ['comite' => 'Comité', 'commission' => 'Commissions', 'projet' => 'Projets'];
?>
<?php if ($miens): ?>
<section class="espaces-miens"><h2>Mes espaces</h2>
<div class="grille"><?php foreach ($miens as $espace): ?><?= $carte($espace, true) ?><?php endforeach ?></div></section>
<?php endif ?>
<?php foreach ($pluriels as $type => $intitule): $groupe = array_filter($autres, fn($e) => $e['type'] === $type); if (!$groupe) { continue; } ?>

<h2><?= e($intitule) ?></h2>
<div class="grille"><?php foreach ($groupe as $espace): ?><?= $carte($espace) ?><?php endforeach ?></div>
<?php endforeach ?>
