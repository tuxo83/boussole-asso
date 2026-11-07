<?php
// Images publiques (pages du site) : ré-encodées à l'envoi, ce qui supprime toutes les métadonnées (EXIF, GPS…)
declare(strict_types=1);

const IMAGE_TAILLE_MAX = 8 * 1024 * 1024;
const IMAGE_PIXELS_MAX = 40000000;   // protège la mémoire contre les « bombes » de décompression
const IMAGE_COTE_MAX = 2000;         // au-delà, l'image est réduite

// Renvoie le chemin de l'image enregistrée (uploads/img-….webp) ou un message d'erreur
// $televerse = false : fichier temporaire écrit par Boussole (API), et non envoyé par un formulaire
function image_enregistrer(array $file, ?array $member, bool $televerse = true): array
{
    if (!extension_loaded('gd')) {
        return [null, "L'hébergement ne permet pas de traiter les images (extension GD absente)."];
    }
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || $file['size'] > IMAGE_TAILLE_MAX || ($televerse && !is_uploaded_file($file['tmp_name']))) {
        return [null, 'Image absente ou trop lourde (8 Mo maximum).'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        return [null, 'Format accepté : JPEG, PNG, WebP ou GIF.'];
    }
    if ($info[0] * $info[1] > IMAGE_PIXELS_MAX) {
        return [null, 'Image trop grande.'];
    }
    $source = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if ($source === false) {
        return [null, 'Image illisible.'];
    }
    $source = image_orientation($source, $file['tmp_name'], $info[2]);
    [$width, $height] = [imagesx($source), imagesy($source)];
    $ratio = min(1, IMAGE_COTE_MAX / max($width, $height));
    [$w, $h] = [max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio))];
    $image = imagecreatetruecolor($w, $h);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagecopyresampled($image, $source, 0, 0, 0, 0, $w, $h, $width, $height);

    $name = 'img-' . bin2hex(random_bytes(12));
    $extension = function_exists('imagewebp') ? 'webp' : 'png';
    $path = BOUSSOLE_ROOT . "/uploads/$name.$extension";
    $saved = $extension === 'webp' ? imagewebp($image, $path, 82) : imagepng($image, $path, 6);
    if (!$saved) {
        return [null, "L'image n'a pas pu être enregistrée (droits du dossier uploads)."];
    }
    $chemin = "uploads/$name.$extension";
    db_query('INSERT INTO image (chemin, largeur, hauteur, depose_par) VALUES (?, ?, ?, ?)', [$chemin, $w, $h, $member['id'] ?? null]);
    return [$chemin, null];
}

// Photo de téléphone : on applique la rotation EXIF avant de supprimer les métadonnées
function image_orientation(GdImage $image, string $file, int $type): GdImage
{
    if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
        return $image;
    }
    $exif = @exif_read_data($file);
    $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
    return $angle !== 0 ? (imagerotate($image, $angle, 0) ?: $image) : $image;
}
