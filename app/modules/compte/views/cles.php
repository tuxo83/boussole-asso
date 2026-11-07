<h1>Clés d'API</h1>
<p>Une clé permet à un outil ou à une IA (connexion MCP) d'agir avec <strong>vos</strong> droits, jamais plus. Les actions faites avec une clé sont marquées « via IA » dans les journaux.</p>
<?php if ($nouvelle): ?>
<div class="alerte alerte-succes" role="status">
  <p><strong>Clé créée.</strong> Elle ne sera plus affichée : copiez-la maintenant.</p>
  <h3>Le plus simple : copier la configuration</h3>
  <p class="petit">À coller dans le réglage « serveurs MCP » de votre outil d'IA (son fichier de configuration). Elle contient déjà l'adresse et la clé.</p>
  <pre class="config" id="config-mcp"><?= e(mcp_configuration($nouvelle)) ?></pre>
  <p><button type="button" class="bouton bouton-principal" data-copier="config-mcp">Copier la configuration</button></p>
  <h3>Ou les deux informations séparément</h3>
  <?= c_tableau(['', ''], [['Adresse du serveur', absolute_url('/mcp')], ['Clé', $nouvelle]]) ?>
  <p class="petit">Pour un outil technique : la clé s'envoie dans l'en-tête HTTP <code>Authorization</code>, précédée du mot <code>Bearer</code> et d'un espace.</p>
</div>
<?php endif ?>
<form method="post" action="<?= e(url('/compte/cles')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= c_champ('libelle', 'Nom de la clé', ['requis' => true, 'max' => 100, 'aide' => 'Ex. « Assistant IA, rédaction des pages ».']) ?>
  <?= c_champ('portee', 'Portée', ['type' => 'select', 'valeur' => 'lecture', 'choix' => ['lecture' => 'Lecture seule', 'ecriture' => 'Lecture et écriture'] + (is_admin(current_member()) ? ['admin' => 'Administration (réglages du site, membres)'] : []), 'aide' => 'Toujours dans la limite de vos propres droits.']) ?>
  <?= c_champ('duree', 'Durée de validité', ['type' => 'select', 'valeur' => '30', 'choix' => CLE_DUREES]) ?>
  <button type="submit" class="bouton bouton-principal">Créer la clé</button>
</form>
<?php if ($cles): ?>
<h2>Mes clés</h2>
<ul class="liste">
  <?php foreach ($cles as $cle): $active = cle_active($cle); ?>
  <li><strong><?= e($cle['libelle']) ?></strong> <span class="discret">· bsl_<?= e($cle['prefixe']) ?>_… · <?= ['lecture' => 'lecture seule', 'ecriture' => 'lecture et écriture', 'admin' => 'administration'][$cle['portee']] ?>
    · <?= e(cle_validite($cle)) ?>
    · <?= $cle['derniere_utilisation'] ? 'utilisée le ' . e(date('d/m/Y H:i', strtotime($cle['derniere_utilisation']))) : 'jamais utilisée' ?></span>
    <?php if ($active): ?>
    <form method="post" action="<?= e(url('/compte/cles/' . (int) $cle['id'] . '/revoquer')) ?>" class="en-ligne"><?= csrf_field() ?><button type="submit" class="lien-bouton" data-confirmer="Révoquer la clé « <?= e($cle['libelle']) ?> » ?">révoquer</button></form>
    <?php endif ?>
  </li>
  <?php endforeach ?>
</ul>
<?php endif ?>
