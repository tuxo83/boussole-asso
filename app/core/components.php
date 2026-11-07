<?php
// Composants réutilisables : appelés depuis les vues, ils renvoient du HTML déjà échappé
declare(strict_types=1);

function c_carte(string $titre, string $contenu_html, ?string $lien = null): string
{
    $titre_html = $lien !== null ? '<a href="' . e(url($lien)) . '">' . e($titre) . '</a>' : e($titre);
    return '<article class="carte"><h3>' . $titre_html . '</h3>' . $contenu_html . '</article>';
}

function c_bouton(string $libelle, string $lien, string $style = 'principal'): string
{
    return '<a class="bouton bouton-' . e($style) . '" href="' . e(url($lien)) . '">' . e($libelle) . '</a>';
}

function c_alerte(string $type, string $message): string
{
    $type = in_array($type, ['info', 'succes', 'erreur'], true) ? $type : 'info';
    return '<p class="alerte alerte-' . $type . '" role="' . ($type === 'erreur' ? 'alert' : 'status') . '">' . e($message) . '</p>';
}

// Météo d'un sujet : l'icône est toujours accompagnée de son libellé
function c_meteo(string $etat): string
{
    $etats = [
        'soleil' => ['☀️', 'À jour'],
        'eclaircie' => ['🌤️', 'En cours'],
        'pluie' => ['🌧️', 'En retard'],
        'orage' => ['⛈️', 'Bloqué'],
    ];
    [$icone, $libelle] = $etats[$etat] ?? ['❔', 'Inconnu'];
    return '<span class="meteo meteo-' . e($etat) . '"><span aria-hidden="true">' . $icone . '</span> ' . e($libelle) . '</span>';
}

// Champ de formulaire : libellé, aide, erreur, valeur conservée
function c_champ(string $nom, string $libelle, array $options = []): string
{
    $type = $options['type'] ?? 'text';
    $valeur = (string) ($options['valeur'] ?? '');
    $id = 'champ-' . $nom;
    $attributs = ' id="' . e($id) . '" name="' . e($nom) . '"';
    if (!empty($options['requis'])) {
        $attributs .= ' required';
    }
    if (isset($options['max'])) {
        $attributs .= ' maxlength="' . (int) $options['max'] . '"';
    }
    $html = '<div class="champ"><label for="' . e($id) . '">' . e($libelle) . '</label>';
    if ($type === 'textarea') {
        $html .= '<textarea' . $attributs . ' rows="' . (int) ($options['lignes'] ?? 6) . '">' . e($valeur) . '</textarea>';
    } else {
        $html .= '<input type="' . e($type) . '"' . $attributs . ' value="' . e($valeur) . '">';
    }
    if (!empty($options['aide'])) {
        $html .= '<small>' . e($options['aide']) . '</small>';
    }
    if (!empty($options['erreur'])) {
        $html .= '<small class="erreur">' . e($options['erreur']) . '</small>';
    }
    return $html . '</div>';
}

// Tableau : $colonnes = [clé => en-tête] ; les cellules sont échappées
function c_tableau(array $colonnes, array $lignes): string
{
    $html = '<table><thead><tr>';
    foreach ($colonnes as $entete) {
        $html .= '<th scope="col">' . e($entete) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($lignes as $ligne) {
        $html .= '<tr>';
        foreach (array_keys($colonnes) as $cle) {
            $html .= '<td>' . e(isset($ligne[$cle]) ? (string) $ligne[$cle] : '') . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table>';
}

// Texte saisi dans l'admin : paragraphes et retours à la ligne, sans aucun HTML
function c_texte(string $texte): string
{
    $paragraphes = preg_split('/\R{2,}/', trim($texte));
    $html = '';
    foreach ($paragraphes as $paragraphe) {
        if ($paragraphe !== '') {
            $html .= '<p>' . nl2br(e($paragraphe)) . '</p>';
        }
    }
    return $html;
}
