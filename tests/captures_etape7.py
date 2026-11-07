"""Captures de l'étape 7 (cotisations) pour le panel, ordinateur et téléphone.
Usage : python tests/captures_etape7.py <adresse> <dossier des captures> <commande mysql>"""
import base64, hashlib, hmac, struct, subprocess, sys, time
from datetime import date
from playwright.sync_api import sync_playwright

SITE, OUT, MYSQL = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
MDP = 'mot-de-passe-de-demo-1'
sql = lambda q: subprocess.run(MYSQL.split() + ['-N', '-e', q], capture_output=True, text=True).stdout.strip()

def totp(secret):
    key = base64.b32decode(secret + '=' * (-len(secret) % 8))
    d = hmac.new(key, struct.pack('>Q', int(time.time()) // 30), hashlib.sha1).digest()
    o = d[19] & 15
    return '%06d' % ((struct.unpack('>I', d[o:o + 4])[0] & 0x7fffffff) % 1000000)

with sync_playwright() as p:
    browser = p.chromium.launch()
    ctx = browser.new_context(viewport={'width': 1280, 'height': 900}, locale='fr-FR')
    page = ctx.new_page()
    page.goto(SITE + '/installation')
    for f, v in [('nom_association', 'Les Amis du Phare'), ('email_admin', 'admin@example.org'), ('mot_de_passe', MDP), ('confirmation', MDP)]:
        page.fill('#champ-' + f, v)
    page.click('button[type=submit]')
    page.goto(SITE + '/connexion')
    page.fill('#champ-email', 'admin@example.org'); page.fill('#champ-mot_de_passe', MDP); time.sleep(2.2)
    page.click('button[type=submit]'); page.wait_for_url(SITE + '/compte')
    page.goto(SITE + '/compte/double-authentification')
    page.fill('#champ-code', totp(page.inner_text('code.cle').replace(' ', ''))); page.click('form.formulaire button[type=submit]')
    sql("UPDATE membre SET prenom = 'Hélène', nom = 'Marin' WHERE email = 'admin@example.org'")
    gens = [('Jeanne', 'Durand'), ('Paul', 'Lefèvre'), ('Amina', 'Benali'), ('Louis', 'Martin'), ('Claire', 'Petit'), ('Hugo', 'Roux'), ('Léa', 'Fontaine'), ('Marc', 'Girard')]
    for prenom, nom in gens:
        sql(f"INSERT INTO membre (email, prenom, nom, actif) VALUES ('{prenom.lower()}@example.org', '{prenom}', '{nom}', 1)")
    an = date.today().year
    for email, moyen, jour in [('jeanne', 'helloasso', '02-03'), ('paul', 'cheque', '02-10'), ('amina', 'helloasso', '03-15'), ('louis', 'virement', '04-02'), ('admin', 'cheque', '01-20')]:
        sql(f"INSERT INTO cotisation (membre_id, periode, paye_le, moyen, saisi_par) SELECT id, {an}, '{an}-{jour}', '{moyen}', 1 FROM membre WHERE email LIKE '{email}@%'")
    sql("INSERT INTO membre (email, prenom, nom, actif, cree_le) VALUES ('ancien@example.org', 'Bernard', 'Lemoine', 0, NOW() - INTERVAL 5 YEAR)")
    sql(f"INSERT INTO cotisation (membre_id, periode, paye_le, moyen) SELECT id, {an - 4}, '{an - 4}-02-01', 'cheque' FROM membre WHERE email = 'ancien@example.org'")
    page.goto(SITE + '/cotisations')
    page.screenshot(path=f'{OUT}/01-cotisations-ordinateur.png', full_page=True)
    mobile = browser.new_context(viewport={'width': 390, 'height': 844}, device_scale_factor=2, is_mobile=True, locale='fr-FR', storage_state=ctx.storage_state()).new_page()
    mobile.goto(SITE + '/cotisations')
    mobile.screenshot(path=f'{OUT}/02-cotisations-telephone.png', full_page=True)
    mobile.locator('#helloasso summary').click()
    mobile.locator('#helloasso').screenshot(path=f'{OUT}/04-helloasso-telephone.png')
    mobile.goto(SITE + '/compte')
    mobile.screenshot(path=f'{OUT}/03-compte-telephone.png', full_page=True)
    browser.close()
print('captures faites')
