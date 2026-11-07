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
    // Lien externe (ex. HelloAsso) gardé tel quel, lien interne préfixé par l'adresse du site
    $externe = preg_match('#^https://#', $lien) === 1;
    return '<a class="bouton bouton-' . e($style) . '" href="' . e($externe ? $lien : url($lien)) . '"' . ($externe ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . e($libelle) . '</a>';
}

function c_alerte(string $type, string $message): string
{
    $type = in_array($type, ['info', 'succes', 'erreur'], true) ? $type : 'info';
    return '<p class="alerte alerte-' . $type . '" role="' . ($type === 'erreur' ? 'alert' : 'status') . '">' . e($message) . '</p>';
}

// Icônes dessinées (traits simples, couleur du texte) : pas d'émojis, rendu identique partout
const ICONES = [
    'soleil' => '<circle cx="12" cy="12" r="4.5"/><path d="M12 2.5v2.2M12 19.3v2.2M2.5 12h2.2M19.3 12h2.2M5.3 5.3l1.6 1.6M17.1 17.1l1.6 1.6M5.3 18.7l1.6-1.6M17.1 6.9l1.6-1.6"/>',
    'eclaircie' => '<circle cx="9" cy="8.5" r="3.5"/><path d="M9 2.5v1.3M3 8.5h1.3M4.8 4.3l.9.9M13.2 4.3l-.9.9"/><path d="M8.5 20h9a3.5 3.5 0 0 0 .3-7 5 5 0 0 0-9.5 1.2A3 3 0 0 0 8.5 20z" fill="currentColor" fill-opacity=".12"/>',
    'pluie' => '<path d="M7.5 15h10a3.8 3.8 0 0 0 .3-7.6 5.5 5.5 0 0 0-10.5 1.4A3.2 3.2 0 0 0 7.5 15z" fill="currentColor" fill-opacity=".12"/><path d="M8.5 18l-1 2.5M12.5 18l-1 2.5M16.5 18l-1 2.5"/>',
    'orage' => '<path d="M7.5 14.5h10a3.8 3.8 0 0 0 .3-7.6 5.5 5.5 0 0 0-10.5 1.4A3.2 3.2 0 0 0 7.5 14.5z" fill="currentColor" fill-opacity=".12"/><path d="M13 13l-2.5 4h3l-2 4" stroke-linejoin="round"/>',
    'calendrier' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
    'lieu' => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
    'personnes' => '<circle cx="9" cy="8.5" r="3.2"/><path d="M3 19.5a6 6 0 0 1 12 0"/><circle cx="17" cy="9.5" r="2.5"/><path d="M15.5 14.2a5 5 0 0 1 5.5 5.3"/>',
    'attention' => '<path d="M12 3.5 2.8 19.5h18.4z" stroke-linejoin="round"/><path d="M12 10v4.2"/><circle cx="12" cy="17" r=".6" fill="currentColor"/>',
    'ecole' => '<path d="M3 9.5 12 5l9 4.5-9 4.5z" stroke-linejoin="round"/><path d="M7 11.5v4.2c0 1.3 2.2 2.8 5 2.8s5-1.5 5-2.8v-4.2M21 9.5v5"/>',
    'bouclier' => '<path d="M12 3.2 19 6v5.5c0 4.4-3 8-7 9.3-4-1.3-7-4.9-7-9.3V6z" stroke-linejoin="round"/><path d="M9 12l2.2 2.2L15.5 10"/>',
    'ancre' => '<circle cx="12" cy="5.5" r="2"/><path d="M12 7.5V20M8 11h8M5 14a7 7 0 0 0 14 0"/>',
    'medaille' => '<circle cx="12" cy="14.5" r="5"/><path d="M8.5 10.5 6 3h4l2 4 2-4h4l-2.5 7.5"/><path d="m12 12.3 0.8 1.6 1.7 0.2 -1.2 1.2 0.3 1.7 -1.6 -0.8 -1.6 0.8 0.3 -1.7 -1.2 -1.2 1.7 -0.2z"/>',
    'reseau' => '<circle cx="12" cy="5" r="2.2"/><circle cx="5" cy="18" r="2.2"/><circle cx="19" cy="18" r="2.2"/><path d="M11 7 6 16M13 7l5 9M7.2 18h9.6"/>',
    'ecran' => '<rect x="3" y="4.5" width="18" height="12" rx="1.8"/><path d="M8.5 20h7M12 16.5V20"/><path d="m10 9 2 1.5L10 12" stroke-linejoin="round"/>',
    'horloge' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
];

function c_icone(string $nom): string
{
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true" focusable="false">' . (ICONES[$nom] ?? '') . '</svg>';
}

// Météo d'un sujet : l'icône est toujours accompagnée de son libellé
function c_meteo(string $etat): string
{
    $libelles = ['soleil' => 'À jour', 'eclaircie' => 'En cours', 'pluie' => 'En retard', 'orage' => 'Bloqué'];
    if (!isset($libelles[$etat])) {
        return '<span class="meteo">Inconnu</span>';
    }
    return '<span class="meteo meteo-' . $etat . '">' . c_icone($etat) . e($libelles[$etat]) . '</span>';
}

// Aide repliable : un « ? » qui s'ouvre au clic ou au toucher, lisible sans JavaScript
function c_aide(string $titre, string $contenu_html): string
{
    return '<details class="aide"><summary aria-label="Aide : ' . e($titre) . '"><span aria-hidden="true">?</span></summary><div class="aide-contenu">' . $contenu_html . '</div></details>';
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
    if ($type === 'select') {
        $html .= '<select' . $attributs . '>';
        foreach ($options['choix'] ?? [] as $cle => $libelle_choix) {
            $html .= '<option value="' . e((string) $cle) . '"' . ((string) $cle === $valeur ? ' selected' : '') . '>' . e($libelle_choix) . '</option>';
        }
        $html .= '</select>';
    } elseif ($type === 'color') {
        $html .= '<span class="couleur"><input type="color"' . $attributs . ' value="' . e($valeur) . '"><output>' . e($valeur) . '</output></span>';
    } elseif ($type === 'textarea') {
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
    $html = '<div class="tableau"><table><thead><tr>';
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
    return $html . '</tbody></table></div>';
}

function taille_lisible(int $octets): string
{
    if ($octets < 1024) {
        return $octets . ' octets';
    }
    return $octets < 1048576 ? round($octets / 1024) . ' Ko' : number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
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
