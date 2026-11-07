<h1>Clés d'API</h1>
<p>Une clé permet à un outil ou à une IA (connexion MCP) d'agir avec <strong>vos</strong> droits, jamais plus. Les actions faites avec une clé sont marquées « via IA » dans les journaux.</p>
<?php if ($nouvelle): ?>
<div class="alerte alerte-succes" role="status">
  <p><strong>Votre nouvelle clé</strong> : copiez-la maintenant, elle ne sera plus affichée.</p>
  <p><code class="cle"><?= e($nouvelle) ?></code></p>
  <p class="petit">Adresse du serveur MCP : <code><?= e(absolute_url('/mcp')) ?></code> · en-tête <code>Authorization: Bearer …</code></p>
</div>
<?php endif ?>
<form method="post" action="<?= e(url('/compte/cles')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= c_champ('libelle', 'Nom de la clé', ['requis' => true, 'max' => 100, 'aide' => 'Ex. « Assistant IA, rédaction des pages ».']) ?>
  <?= c_champ('portee', 'Portée', ['type' => 'select', 'valeur' => 'lecture', 'choix' => ['lecture' => 'Lecture seule', 'ecriture' => 'Lecture et écriture']]) ?>
  <?= c_champ('duree', 'Durée de validité', ['type' => 'select', 'valeur' => '30', 'choix' => CLE_DUREES]) ?>
  <button type="submit" class="bouton bouton-principal">Créer la clé</button>
</form>
<?php if ($cles): ?>
<h2>Mes clés</h2>
<ul class="liste">
  <?php foreach ($cles as $cle): $active = !$cle['revoquee'] && $cle['expire_le'] >= date('Y-m-d'); ?>
  <li><strong><?= e($cle['libelle']) ?></strong> <span class="discret">· bsl_<?= e($cle['prefixe']) ?>_… · <?= $cle['portee'] === 'ecriture' ? 'lecture et écriture' : 'lecture seule' ?>
    · <?= $cle['revoquee'] ? 'révoquée' : ($active ? "jusqu'au " . e(date('d/m/Y', strtotime($cle['expire_le']))) : 'expirée') ?>
    · <?= $cle['derniere_utilisation'] ? 'utilisée le ' . e(date('d/m/Y H:i', strtotime($cle['derniere_utilisation']))) : 'jamais utilisée' ?></span>
    <?php if ($active): ?>
    <form method="post" action="<?= e(url('/compte/cles/' . (int) $cle['id'] . '/revoquer')) ?>" class="en-ligne"><?= csrf_field() ?><button type="submit" class="lien-bouton" data-confirmer="Révoquer la clé « <?= e($cle['libelle']) ?> » ?">révoquer</button></form>
    <?php endif ?>
  </li>
  <?php endforeach ?>
</ul>
<?php endif ?>
