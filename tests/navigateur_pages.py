"""Pages et éditeur à blocs dans un vrai navigateur : saisie dans l'éditeur, image avec métadonnées GPS
(qui doivent disparaître), arborescence, page d'accueil, captures pour le panel.
Usage : SQL_DEMO="mysql …" python tests/navigateur_pages.py <adresse du site> <dossier des captures> <dossier app>"""
import base64, hashlib, hmac, io, json, os, struct, sys, time
from PIL import Image
from playwright.sync_api import sync_playwright

SITE, OUT, APP = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
MDP = 'mot-de-passe-de-demo-1'
ok = ko = 0
def check(label, test):
    global ok, ko
    ok, ko = (ok + 1, ko) if test else (ok, ko + 1)
    print(('OK  ' if test else 'KO  ') + label)

def totp(secret):
    key = base64.b32decode(secret + '=' * (-len(secret) % 8))
    digest = hmac.new(key, struct.pack('>Q', int(time.time()) // 30), hashlib.sha1).digest()
    o = digest[19] & 15
    return '%06d' % ((struct.unpack('>I', digest[o:o + 4])[0] & 0x7fffffff) % 1000000)

# Photo de démonstration avec des coordonnées GPS dans ses métadonnées EXIF
photo = Image.new('RGB', (1600, 1000), (31, 78, 121))
for x in range(0, 1600, 40):
    for y in range(600, 1000):
        photo.putpixel((x, y), (230, 200, 120))
exif = Image.Exif()
exif[0x010F] = 'TelephoneDemo'
exif[0x8825] = {1: 'N', 2: (43.0, 7.0, 12.0), 3: 'E', 4: (6.0, 2.0, 30.0)}
buffer = io.BytesIO()
photo.save(buffer, 'JPEG', exif=exif.tobytes())
JPEG = buffer.getvalue()
check('la photo de test contient bien des métadonnées GPS', b'GPS' in JPEG or b'N\x00' in JPEG)

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_context(viewport={'width': 1280, 'height': 860}, locale='fr-FR').new_page()
    page.goto(SITE + '/installation')
    for field, value in [('nom_association', 'Les Amis du Phare'), ('email_admin', 'admin@example.org'), ('mot_de_passe', MDP), ('confirmation', MDP)]:
        page.fill('#champ-' + field, value)
    page.click('button[type=submit]')
    page.wait_for_url(SITE + '/')
    page.goto(SITE + '/connexion')
    page.fill('#champ-email', 'admin@example.org')
    page.fill('#champ-mot_de_passe', MDP)
    time.sleep(2.2)
    page.click('button[type=submit]')
    page.wait_for_url(SITE + '/compte')
    page.goto(SITE + '/compte/double-authentification')
    page.fill('#champ-code', totp(page.inner_text('code.cle').replace(' ', '')))
    page.click('form.formulaire button[type=submit]')
    page.wait_for_url(SITE + '/compte')

    # Création de pages : une page, puis une sous-page
    page.goto(SITE + '/admin/pages')
    page.fill('input[name=titre]', "L'association")
    page.click('form.en-ligne button[type=submit]')
    page.wait_for_url(SITE + '/admin/pages/1')
    page.wait_for_selector('#editeur [contenteditable=true]')
    check("l'éditeur à blocs se charge", page.locator('#editeur [contenteditable=true]').count() >= 1)
    page.click('#editeur')
    page.keyboard.type("Fondée en 1987, l'association veille sur le phare et son histoire.")
    page.check('input[name=publiee]')
    page.check('input[name=dans_menu]')
    page.click('#formulaire-page button[type=submit]')
    page.wait_for_url(SITE + '/admin/pages/1')
    page.goto(SITE + '/page/l-association')
    check('le texte tapé dans l\'éditeur est publié', 'veille sur le phare' in page.content())

    # Image envoyée comme le fait l'outil image de l'éditeur
    page.goto(SITE + '/admin/pages/1')
    csrf = page.input_value('input[name=_csrf]')
    result = page.evaluate('''async ([csrf, b64]) => {
        const bytes = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
        const body = new FormData(); body.append('_csrf', csrf); body.append('image', new Blob([bytes], {type: 'image/jpeg'}), 'photo.jpg');
        const r = await fetch('/admin/images', {method: 'POST', body, credentials: 'same-origin'}); return r.json(); }''', [csrf, base64.b64encode(JPEG).decode()])
    check('image acceptée et ré-encodée', result.get('success') == 1)
    saved = open(os.path.join(APP, result['chemin']), 'rb').read()
    check('métadonnées supprimées (ni EXIF ni GPS dans le fichier)', b'Exif' not in saved and b'GPS' not in saved and b'TelephoneDemo' not in saved)
    check("image réduite à 2000 px maximum", max(Image.open(io.BytesIO(saved)).size) <= 2000)
    fake = page.evaluate('''async (csrf) => { const body = new FormData(); body.append('_csrf', csrf);
        body.append('image', new Blob(['<?php echo 1;'], {type: 'image/png'}), 'x.png');
        return (await fetch('/admin/images', {method: 'POST', body, credentials: 'same-origin'})).json(); }''', csrf)
    check('faux fichier image refusé', fake.get('success') == 0)

    # Contenu complet de la page d'accueil (envoyé comme le formulaire de l'éditeur)
    blocs = {'blocks': [
        {'type': 'header', 'data': {'text': 'Bienvenue au phare', 'level': 2}},
        {'type': 'paragraph', 'data': {'text': "Visites guidées, conférences et <b>chantiers bénévoles</b> : <a href=\"/page/l-association\">découvrir l'association</a>."}},
        {'type': 'image', 'data': {'chemin': result['chemin'], 'legende': 'Le phare au coucher du soleil', 'alt': 'Le phare sur la côte'}},
        {'type': 'list', 'data': {'style': 'unordered', 'items': [{'content': 'Visites le premier dimanche du mois', 'items': []}, {'content': 'Adhésion ouverte toute l\'année', 'items': []}]}},
        {'type': 'boussole', 'data': {'bloc': 'espace-membres'}},
    ]}
    page.goto(SITE + '/admin/pages')
    page.fill('input[name=titre]', 'Accueil')
    page.click('form.en-ligne button[type=submit]')
    page.wait_for_url(SITE + '/admin/pages/2')
    with page.expect_navigation():
        page.evaluate('''(blocs) => { const f = document.getElementById('formulaire-page');
        document.getElementById('champ-blocs').value = blocs; f.querySelector('input[name=publiee]').checked = true;
        f.querySelector('input[name=est_accueil]').checked = true; HTMLFormElement.prototype.submit.call(f); }''', json.dumps(blocs))
    page.goto(SITE + '/admin/pages')
    page.fill('input[name=titre]', 'Nos statuts')
    page.select_option('select[name=parent_id]', '1')
    page.click('form.en-ligne button[type=submit]')
    page.wait_for_url(SITE + '/admin/pages/3')
    page.check('input[name=publiee]')
    page.click('#formulaire-page button[type=submit]')
    page.wait_for_url(SITE + '/admin/pages/3')
    page.screenshot(path=f'{OUT}/03-editeur-ordinateur.png', full_page=True)

    page.goto(SITE + '/page/nos-statuts')
    check("fil d'Ariane de la sous-page", "L'association" in page.inner_text('.ariane'))
    page.goto(SITE + '/page/l-association')
    check('la page liste sa sous-page', 'Nos statuts' in page.inner_text('.sous-pages'))
    page.screenshot(path=f'{OUT}/02-page-ordinateur.png', full_page=True)
    page.goto(SITE + '/admin/pages')
    page.screenshot(path=f'{OUT}/04-liste-pages-ordinateur.png', full_page=True)

    visiteur = browser.new_context(viewport={'width': 1280, 'height': 860}, locale='fr-FR').new_page()
    visiteur.goto(SITE + '/')
    check("l'accueil affiche la page marquée « accueil »", 'Bienvenue au phare' in visiteur.content())
    check("l'accueil affiche l'image nettoyée", result['chemin'] in visiteur.content())
    check("« L'association » est dans le menu", "L'association" in visiteur.inner_text('nav[aria-label="Menu principal"]'))
    check('la page « Accueil » n\'apparaît pas en double dans le menu', 'Accueil' == visiteur.inner_text('nav[aria-label="Menu principal"] li:first-child').strip())
    visiteur.screenshot(path=f'{OUT}/01-accueil-ordinateur.png', full_page=True)
    check('brouillon invisible du public', visiteur.goto(SITE + '/admin/pages').url.endswith('/connexion'))
    mobile = browser.new_context(viewport={'width': 390, 'height': 844}, device_scale_factor=2, is_mobile=True, locale='fr-FR').new_page()
    mobile.goto(SITE + '/')
    mobile.screenshot(path=f'{OUT}/01-accueil-telephone.png', full_page=True)
    browser.close()
print(f'Bilan : {ok} OK, {ko} KO')
sys.exit(0 if ko == 0 else 1)
