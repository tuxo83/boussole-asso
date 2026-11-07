"""Captures d'écran des pages, sur ordinateur et téléphone, pour la relecture du panel.
Parcours réel dans un navigateur : installation, connexion, double authentification, réglages.
Usage : python tests/captures.py <adresse du site> <dossier des captures>"""
import base64, hashlib, hmac, os, struct, subprocess, sys, time
from playwright.sync_api import sync_playwright

SITE, OUT = sys.argv[1].rstrip('/'), sys.argv[2]
MDP = 'mot-de-passe-de-demo-1'

def totp(secret):
    key = base64.b32decode(secret + '=' * (-len(secret) % 8))
    digest = hmac.new(key, struct.pack('>Q', int(time.time()) // 30), hashlib.sha1).digest()
    offset = digest[19] & 15
    return '%06d' % ((struct.unpack('>I', digest[offset:offset + 4])[0] & 0x7fffffff) % 1000000)

def shot(page, name):
    page.screenshot(path=f'{OUT}/{name}.png', full_page=True)

with sync_playwright() as p:
    browser = p.chromium.launch()
    desk = browser.new_context(viewport={'width': 1280, 'height': 860}, locale='fr-FR')
    page = desk.new_page()

    page.goto(SITE + '/')
    shot(page, '01-installation-ordinateur')
    page.fill('#champ-nom_association', 'Les Amis du Phare')
    page.fill('#champ-email_admin', 'admin@example.org')
    page.fill('#champ-mot_de_passe', MDP)
    page.fill('#champ-confirmation', MDP)
    page.click('button[type=submit]')
    page.wait_for_url(SITE + '/')
    # Une page libre de démonstration (commande SQL fournie par l'environnement de test)
    if os.environ.get('SQL_DEMO'):
        subprocess.run(os.environ['SQL_DEMO'], shell=True, check=True, input=(
            "INSERT INTO page (slug, titre, contenu, publiee, dans_menu, ordre) VALUES ('statuts', 'Statuts', "
            "'Article 1 : Il est fondé entre les adhérents aux présents statuts une association régie par la loi du 1er juillet 1901.\n\n"
            "Article 2 : Cette association a pour objet la sauvegarde et la mise en valeur du patrimoine maritime.', 1, 1, 10);").encode())

    page.goto(SITE + '/connexion')
    shot(page, '03-connexion-ordinateur')
    page.fill('#champ-email', 'admin@example.org')
    page.fill('#champ-mot_de_passe', MDP)
    time.sleep(2.2)  # délai minimal du captcha invisible ; la preuve de travail est calculée par le navigateur
    page.click('button[type=submit]')
    page.wait_for_url(SITE + '/compte')

    page.goto(SITE + '/compte/double-authentification')
    shot(page, '04-double-authentification-ordinateur')
    secret = page.inner_text('code.cle').replace(' ', '')
    page.fill('#champ-code', totp(secret))
    page.click('form.formulaire button[type=submit]')
    page.wait_for_url(SITE + '/compte')
    shot(page, '05-compte-ordinateur')

    page.goto(SITE + '/admin/parametres')
    page.fill('#champ-slogan', 'Éclairer, rassembler, transmettre')
    page.fill('#champ-presentation', "Les Amis du Phare réunissent depuis 1987 les passionnés du patrimoine maritime de la côte.\n\nNous organisons des visites, des conférences et des chantiers de restauration ouverts à tous.\n\nRejoignez-nous : l'adhésion est ouverte toute l'année.")
    page.fill('#champ-pied_de_page', 'Les Amis du Phare · Association loi 1901')
    page.fill('#champ-mail_expediteur', 'contact@example.org')
    page.click('form.formulaire button[type=submit]')
    page.wait_for_url(SITE + '/admin/parametres')
    shot(page, '06-reglages-ordinateur')

    for path, name in [('/', '02-accueil'), ('/page/statuts', '07-page-libre'), ('/introuvable', '08-page-introuvable')]:
        page.goto(SITE + path)
        shot(page, name + '-ordinateur')

    mobile = browser.new_context(viewport={'width': 390, 'height': 844}, device_scale_factor=2, is_mobile=True, has_touch=True, locale='fr-FR')
    m = mobile.new_page()
    for path, name in [('/', '02-accueil'), ('/connexion', '03-connexion'), ('/page/statuts', '07-page-libre')]:
        m.goto(SITE + path)
        shot(m, name + '-telephone')
    browser.close()
print('captures faites')
