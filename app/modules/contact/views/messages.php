<h1>Messages reçus</h1>
<p class="petit">Messages envoyés par le formulaire de contact. Ils sont effacés automatiquement après <?= (int) setting('contact_conservation_mois', '12') ?> mois (Réglages → Conservation).</p>
<?php if (!$messages): ?><p>Aucun message.</p><?php endif ?>
<?php foreach ($messages as $m): ?>
<article class="carte message-contact<?= $m['lu'] ? '' : ' message-nouveau' ?>">
  <p class="petit discret"><?= e(date('d/m/Y à H\hi', strtotime($m['recu_le']))) ?><?= $m['lu'] ? '' : ' · <strong>nouveau</strong>' ?><?= $m['transmis'] ? ' · transmis par e-mail' : ' · non transmis par e-mail' ?></p>
  <h2><?= e($m['objet']) ?></h2>
  <p><strong><?= e($m['nom']) ?></strong> · <a href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $m['objet'])) ?>"><?= e($m['email']) ?></a></p>
  <p class="message-texte"><?= nl2br(e($m['message']), false) ?></p>
  <form method="post" action="<?= e(url('/admin/messages/' . (int) $m['id'] . '/supprimer')) ?>"><?= csrf_field() ?><button type="submit" class="lien-bouton lien-danger" data-confirmer="Supprimer ce message ?">Supprimer</button></form>
</article>
<?php endforeach ?>
