<h1>Paramètres</h1>
<?php if ($erreur ?? null): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>
<?php $historique = $historique ?? []; ?>
<section class="compte-section" id="securite">
<h2>Mon compte : connexion et sécurité</h2>
<?php $nb_cles = (int) db_value('SELECT COUNT(*) FROM cle_securite WHERE membre_id = ?', [$membre['id']]); $demo = !empty($membre['compte_demo']); ?>
<ul class="reglages">
  <li>
    <span class="tuile-icone"><?= c_icone('cadenas') ?></span><div><strong>Mot de passe</strong><span class="discret"><?= $demo ? 'Compte de démonstration : fixé par un administrateur' : ($membre['mot_de_passe'] !== null ? 'Défini' : 'Aucun : connexion par lien envoyé par e-mail') ?></span></div>
    <?php if (!$demo): ?>
    <details class="reglage-action"<?= ($erreur ?? null) ? ' open' : '' ?>><summary class="bouton bouton-secondaire"><?= $membre['mot_de_passe'] !== null ? 'Changer' : 'Choisir' ?></summary>
      <form method="post" action="<?= e(url('/compte/mot-de-passe')) ?>" class="formulaire">
        <?= csrf_field() ?>
        <?= c_champ('mot_de_passe', 'Nouveau mot de passe', ['type' => 'password', 'aide' => 'Au moins ' . MOT_DE_PASSE_MIN . ' caractères. Laisser vide pour se connecter seulement par lien envoyé par e-mail.']) ?>
        <?= c_champ('confirmation', 'Confirmation', ['type' => 'password']) ?>
        <button type="submit" class="bouton bouton-principal">Enregistrer</button>
      </form>
    </details>
    <?php endif ?>
  </li>
  <li>
    <span class="tuile-icone"><?= c_icone('bouclier') ?></span><div><strong>Double authentification</strong><span class="discret"><?= ($nb_cles ? $nb_cles . ' clé(s) de sécurité' : 'Aucune clé de sécurité') . ((int) $membre['totp_actif'] === 1 ? ' · application active' : '') ?></span>
      <?php if (member_rights($membre['id']) && !$nb_cles && !(int) $membre['totp_actif']): ?><span class="petit">Conseillé pour la gestion : sinon un code par e-mail vous sera demandé.</span><?php endif ?></div>
    <?php if (!$demo): ?><?= c_bouton('Gérer', '/compte/double-authentification', 'secondaire') ?><?php endif ?>
  </li>
  <?php if (!$demo): ?>
  <li>
    <span class="tuile-icone"><?= c_icone('cle') ?></span><div><strong>Clés d'API</strong><span class="discret">Donner à un outil ou à une IA un accès avec vos droits</span></div>
    <?= c_bouton('Gérer', '/compte/cles', 'secondaire') ?>
  </li>
  <?php endif ?>
  <li>
    <span class="tuile-icone"><?= c_icone('horloge') ?></span><div><strong>Activité du compte</strong><span class="discret"><?= $membre['derniere_connexion'] ? 'Dernière connexion : ' . e(date('d/m/Y à H\\hi', strtotime($membre['derniere_connexion']))) : 'Connexions et modifications du compte' ?></span></div>
    <details class="reglage-action"><summary class="bouton bouton-secondaire">Voir</summary>
      <div class="activite">
        <?= partial('historique', ['historique' => $historique ?? [], 'cible_id' => $membre['id']]) ?>
        <p class="petit">Une ligne que vous ne reconnaissez pas ? Changez votre mot de passe et prévenez l'association.</p>
      </div>
    </details>
  </li>
</ul>
</section>
<?php if ($tuiles): ?>
<section class="compte-section" id="administration">
<h2>Administration du site</h2>
<ul class="tuiles">
  <?php foreach ($tuiles as [$icone, $titre, $aide, $lien, $pastille]): ?>
  <li><a href="<?= e(url($lien)) ?>"><span class="tuile-icone"><?= c_icone($icone) ?></span><span class="tuile-texte"><strong><?= e($titre) ?></strong><span class="discret"><?= e($aide) ?></span></span><?php if ($pastille !== ''): ?><span class="pastille"><?= e($pastille) ?></span><?php endif ?></a></li>
  <?php endforeach ?>
</ul>
</section>
<?php endif ?>
