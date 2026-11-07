<?php
// Les migrations doivent passer sur MySQL comme sur MariaDB : on refuse les syntaxes propres à MariaDB
declare(strict_types=1);

$interdits = [
    '/ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS/i' => 'ADD COLUMN IF NOT EXISTS (MariaDB seulement)',
    '/DROP\s+COLUMN\s+IF\s+EXISTS/i' => 'DROP COLUMN IF EXISTS (MariaDB seulement)',
    '/(ADD|CREATE)\s+(UNIQUE\s+)?INDEX\s+IF\s+NOT\s+EXISTS/i' => 'INDEX IF NOT EXISTS (MariaDB seulement)',
    '/MODIFY\s+(COLUMN\s+)?IF\s+EXISTS/i' => 'MODIFY IF EXISTS (MariaDB seulement)',
    '/\bRETURNING\b/i' => 'RETURNING (MariaDB seulement)',
    '/\bINET[46]\b|\bUUID\b\s*(NOT|NULL|,|\))/i' => 'type INET/UUID (MariaDB seulement)',
];
$ok = 0;
$ko = 0;
foreach (glob(dirname(__DIR__) . '/app/migrations/*.sql') as $fichier) {
    $sql = preg_replace('/--[^\n]*/', '', (string) file_get_contents($fichier));
    $fautes = [];
    foreach ($interdits as $motif => $raison) {
        if (preg_match($motif, $sql)) {
            $fautes[] = $raison;
        }
    }
    if ($fautes) {
        $ko++;
        echo 'KO  ' . basename($fichier) . ' : ' . implode(', ', $fautes) . "\n";
    } else {
        $ok++;
    }
}
echo "Bilan : $ok OK, $ko KO\n";
exit($ko > 0 ? 1 : 0);
