<p><a href="<?= e(url('/admin/membres')) ?>">← Membres</a></p>
<h1>Historique du compte</h1>
<p><strong><?= e(membre_nom($cible)) ?></strong> · <?= e($cible['email']) ?></p>
<p class="petit">Connexions, échecs de connexion, mot de passe, clés de sécurité et d'API, modifications de la fiche. Effacé avec le journal (Réglages → Conservation).</p>
<?= partial('historique', ['historique' => $historique, 'cible_id' => $cible['id']]) ?>
