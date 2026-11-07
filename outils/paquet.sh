#!/bin/bash
#
# Fabrique une version publiable : boussole-<version>.zip (contenu de app/), son empreinte SHA-256,
# et installer-boussole.php, l'installateur en un seul fichier qui télécharge et vérifie ce zip.
#
# Usage : outils/paquet.sh <version> <adresse de téléchargement des fichiers de la version> [dossier de sortie]
#   ex. outils/paquet.sh v0.2.0 https://github.com/tuxo83/boussole-asso/releases/download/v0.2.0
#

######################### Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RACINE="$(dirname "$SCRIPT_DIR")"
VERSION="${1:-}"
ADRESSE="${2:-}"
SORTIE="${3:-$RACINE/dist}"

######################### Vérifications
if [[ ! "$VERSION" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+([.-][a-z0-9]+)?$ || -z "$ADRESSE" ]]; then
    echo "Usage : $0 <version, ex. v0.2.0> <adresse de téléchargement> [dossier de sortie]"
    exit 1
fi
command -v zip >/dev/null || { echo "ERREUR : zip est nécessaire"; exit 1; }

######################### Paquet
ZIP="boussole-$VERSION.zip"
mkdir -p "$SORTIE"
rm -f "${SORTIE:?}/$ZIP"
# Jamais dans le paquet : une configuration, des images ou des fichiers déposés
( cd "$RACINE/app" && zip -q -r -X "$SORTIE/$ZIP" . -x 'config/config.php' 'config/code-installation.php' 'uploads/[!.]*' 'stockage/[!.]*' ) || exit 1
SHA=$(sha256sum "$SORTIE/$ZIP" | cut -d' ' -f1)
echo "$SHA  $ZIP" > "$SORTIE/$ZIP.sha256"

######################### Installateur (version, adresse et empreinte inscrites dedans)
sed -e "s#__VERSION__#$VERSION#" -e "s#__SHA256__#$SHA#" -e "s#__URL__#$ADRESSE/$ZIP#" \
    "$SCRIPT_DIR/installer-boussole.modele.php" > "$SORTIE/installer-boussole.php" || exit 1
php -l "$SORTIE/installer-boussole.php" >/dev/null || exit 1

echo "Version $VERSION prête dans $SORTIE : $ZIP, $ZIP.sha256, installer-boussole.php"
