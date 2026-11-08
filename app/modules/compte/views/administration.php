<h1>Administration</h1>
<ul class="tuiles">
  <?php foreach ($tuiles as [$icone, $titre, $aide, $lien, $pastille]): ?>
  <li><a href="<?= e(url($lien)) ?>"><span class="tuile-icone"><?= c_icone($icone) ?></span><span class="tuile-texte"><strong><?= e($titre) ?></strong><span class="discret"><?= e($aide) ?></span></span><?php if ($pastille !== ''): ?><span class="pastille"><?= e($pastille) ?></span><?php endif ?></a></li>
  <?php endforeach ?>
</ul>
