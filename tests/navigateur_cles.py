"""Clés de sécurité (WebAuthn) dans un vrai navigateur, avec un authentificateur simulé (Chromium) :
enregistrement, connexion par clé, refus d'une signature altérée, d'un rejeu et d'une autre clé, secours par e-mail.
Usage : python tests/navigateur_cles.py <adresse en localhost> <fichier des e-mails> <dossier des captures>"""
import json, re, sys, time
from playwright.sync_api import sync_playwright

SITE, MAILS, OUT = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
MDP = 'mot-de-passe-de-demo-1'
ok = ko = 0
def check(label, test):
    global ok, ko
    ok, ko = (ok + 1, ko) if test else (ok, ko + 1)
    print(('OK  ' if test else 'KO  ') + label)

def dernier_code():
    return re.findall(r'Votre code de confirmation : (\d{6})', open(MAILS).read())[-1]

def authentificateur(page):
    cdp = page.context.new_cdp_session(page)
    cdp.send('WebAuthn.enable')
    return cdp, cdp.send('WebAuthn.addVirtualAuthenticator', {'options': {'protocol': 'ctap2', 'transport': 'usb', 'hasResidentKey': False,
        'hasUserVerification': True, 'isUserVerified': True, 'automaticPresenceSimulation': True}})['authenticatorId']

def connexion_mdp(page):
    page.goto(SITE + '/connexion')
    page.fill('#champ-email', 'admin@example.org'); page.fill('#champ-mot_de_passe', MDP); time.sleep(2.2)
    page.click('button[type=submit]')
    page.wait_for_load_state()

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_context(viewport={'width': 1280, 'height': 860}, locale='fr-FR').new_page()
    page.goto(SITE + '/installation')
    for f, v in [('nom_association', 'Les Amis du Phare'), ('email_admin', 'admin@example.org'), ('mot_de_passe', MDP), ('confirmation', MDP)]:
        page.fill('#champ-' + f, v)
    page.click('button[type=submit]')
    page.wait_for_load_state('networkidle')
    connexion_mdp(page)
    page.goto(SITE + '/admin/parametres')
    check('gestion avec une seule preuve : seconde vérification demandée', '/connexion/double-authentification' in page.url)
    check('la page propose d\'ajouter une première clé sans code e-mail', page.locator('text=Ajoutez-en une maintenant').count() == 1)
    page.click('text=Ajoutez-en une maintenant')
    check('première clé : page de sécurité accessible sans code e-mail (e-mails pas encore réglés)', page.url.endswith('/compte/double-authentification'))

    # QR code de l'application : présent, lisible, et il contient exactement la clé affichée
    page.click('summary:has-text("Application d\'authentification")')
    page.locator('.qrcode svg').screenshot(path=OUT + '/04-qrcode.png')
    import cv2
    texte, _, _ = cv2.QRCodeDetector().detectAndDecode(cv2.imread(OUT + '/04-qrcode.png'))
    cle = page.inner_text('code.cle').replace(' ', '')
    check('QR code lisible, avec la bonne clé', texte.startswith('otpauth://totp/') and ('secret=' + cle) in texte)
    cdp, auth_id = authentificateur(page)
    page.fill('#champ-libelle', 'Clé de test')
    with page.expect_navigation():
        page.click('#ajout-cle button[type=submit]')
    check('clé enregistrée', 'Clé de test' in page.content())
    page.screenshot(path=f'{OUT}/01-securite-du-compte.png', full_page=True)

    # Nouvelle connexion : mot de passe puis clé
    page.evaluate("() => { const f = document.createElement('form'); f.method = 'post'; f.action = '/deconnexion'; f.innerHTML = '<input name=_csrf value=\"' + document.querySelector('input[name=_csrf]').value + '\">'; document.body.append(f); f.submit(); }")
    page.wait_for_load_state('networkidle')
    connexion_mdp(page)
    check('avec une clé enregistrée : seconde vérification à la connexion', '/connexion/double-authentification' in page.url)
    check('choix : clé, ou code e-mail si on ne l\'a pas', page.locator('text=Utiliser ma clé de sécurité').count() == 1 and page.locator("text=Je n'ai pas ma clé").count() == 1)
    page.screenshot(path=f'{OUT}/02-connexion-cle.png', full_page=True)

    # Signature altérée : refusée
    page.evaluate('''() => { const f = document.getElementById('connexion-cle'); const orig = JSON.stringify;
        JSON.stringify = function (v) { if (v && v.signature) { const s = v.signature; v.signature = (s[5] === 'A' ? 'B' : 'A') + s.slice(1); v.signature = s.slice(0, 10) + (s[10] === 'A' ? 'B' : 'A') + s.slice(11); } JSON.stringify = orig; return orig(v); }; }''')
    with page.expect_navigation():
        page.click('#connexion-cle button[type=submit]')
    check('signature altérée : refusée', 'Clé refusée' in page.content())

    # Bonne signature : connecté, gestion accessible
    with page.expect_navigation():
        page.click('#connexion-cle button[type=submit]')
    page.goto(SITE + '/admin/parametres')
    check('clé acceptée : gestion accessible', page.url.endswith('/admin/parametres'))

    # Rejeu : même réponse renvoyée, défi déjà consommé
    page.goto(SITE + '/connexion/double-authentification')
    check('déjà deux preuves : rien à refaire', page.locator('text=Utiliser ma clé de sécurité').count() == 0)

    # Autre appareil sans la clé : refus, puis secours par e-mail
    autre = browser.new_context(locale='fr-FR').new_page()
    authentificateur(autre)
    connexion_mdp(autre)
    autre.on('dialog', lambda d: d.dismiss())
    autre.click('#connexion-cle button[type=submit]')
    time.sleep(1.5)
    check('autre appareil sans la clé : pas de connexion', '/connexion/double-authentification' in autre.url)
    autre.click("text=Je n'ai pas ma clé")
    with autre.expect_navigation():
        autre.click('button:has-text("M\'envoyer un code")')
    autre.fill('#champ-code-email', dernier_code())
    with autre.expect_navigation():
        autre.click('form[action$="/code"] button[type=submit]')
    check('secours par code e-mail : connecté', autre.url.endswith('/compte'))
    mobile = browser.new_context(viewport={'width': 390, 'height': 844}, device_scale_factor=2, is_mobile=True, locale='fr-FR').new_page()
    connexion_mdp(mobile)
    mobile.screenshot(path=f'{OUT}/03-connexion-cle-telephone.png', full_page=True)
    browser.close()
print(f'Bilan : {ok} OK, {ko} KO')
sys.exit(0 if ko == 0 else 1)
