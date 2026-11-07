<p><a href="<?= e(url('/admin/pages')) ?>">← Toutes les pages</a></p>
<h1><?= e($page['titre']) ?></h1>
<form method="post" action="<?= e(url('/admin/pages/' . (int) $page['id'])) ?>" class="formulaire large" id="formulaire-page">
  <?= csrf_field() ?>
  <?= c_champ('titre', 'Titre', ['valeur' => $page['titre'], 'requis' => true, 'max' => 200]) ?>
  <div class="champ">
    <span class="libelle" id="libelle-contenu">Contenu</span>
    <div id="editeur" class="editeur" aria-labelledby="libelle-contenu" data-blocs="<?= e($blocs) ?>" data-envoi-image="<?= e(url('/admin/images')) ?>" data-blocs-dynamiques="<?= e(json_encode(BLOCS_DYNAMIQUES, JSON_UNESCAPED_UNICODE)) ?>" data-icones="<?= e(json_encode(['' => 'sans icône', 'ecole' => 'école', 'personnes' => 'personnes', 'bouclier' => 'bouclier', 'ancre' => 'ancre', 'medaille' => 'médaille', 'reseau' => 'réseau', 'ecran' => 'numérique', 'calendrier' => 'calendrier', 'lieu' => 'lieu'], JSON_UNESCAPED_UNICODE)) ?>"></div>
    <small>Tapez « / » ou cliquez sur « + » pour ajouter un titre, une liste, une citation, une image, des cartes, des chiffres clés, un bouton, une séparation ou un bloc dynamique. La page d'accueil a déjà un bouton « Espace membres » en haut.</small>
    <noscript><p class="alerte alerte-erreur">L'éditeur a besoin de JavaScript.</p></noscript>
  </div>
  <input type="hidden" name="blocs" id="champ-blocs" value="<?= e($blocs) ?>">
  <?= c_champ('description', 'Description pour les moteurs de recherche et les partages', ['type' => 'textarea', 'valeur' => (string) ($page['description'] ?? ''), 'max' => 300,
      'aide' => 'Une ou deux phrases (160 caractères conseillés). Vide : le début du premier paragraphe est utilisé.']) ?>
  <fieldset>
    <legend>Publication</legend>
    <?= c_champ('parent_id', 'Page parente', ['type' => 'select', 'valeur' => (string) $page['parent_id'], 'choix' => ['' => 'Aucune (premier niveau)'] + array_column($parents, 'titre', 'id')]) ?>
    <label class="case"><input type="checkbox" name="publiee" value="1"<?= $page['publiee'] ? ' checked' : '' ?>> Publiée (visible par tous)</label>
    <label class="case"><input type="checkbox" name="reservee_membres" value="1"<?= $page['reservee_membres'] ? ' checked' : '' ?>> Réservée aux membres connectés (ex. comité directeur, documents internes)</label>
    <label class="case"><input type="checkbox" name="dans_menu" value="1"<?= $page['dans_menu'] ? ' checked' : '' ?>> Dans le menu (pages de premier niveau)</label>
    <label class="case"><input type="checkbox" name="est_accueil" value="1"<?= $page['est_accueil'] ? ' checked' : '' ?>> Contenu de la page d'accueil</label>
    <?= c_champ('ordre', 'Ordre (plus petit = plus haut)', ['type' => 'number', 'valeur' => (string) $page['ordre']]) ?>
  </fieldset>
  <button type="submit" class="bouton bouton-principal">Enregistrer</button>
</form>
<form method="post" action="<?= e(url('/admin/pages/' . (int) $page['id'] . '/supprimer')) ?>" class="separe">
  <?= csrf_field() ?>
  <button type="submit" class="bouton bouton-secondaire" data-confirmer="Supprimer la page « <?= e($page['titre']) ?> » ?">Supprimer la page</button>
</form>
<?php foreach (['editorjs', 'header', 'list', 'quote', 'delimiter'] as $outil): ?>
<script src="<?= e(asset('/assets/vendor/editorjs/' . $outil . '.js')) ?>"></script>
<?php endforeach ?>
<script src="<?= e(asset('/assets/js/editeur.js')) ?>"></script>
