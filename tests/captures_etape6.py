"""Captures de l'étape 6 (événement à places limitées, vœux, liste à valider) pour le panel.
Usage : python tests/captures_etape6.py <adresse> <dossier des captures> <commande mysql>"""
import base64, hashlib, hmac, struct, subprocess, sys, time
from datetime import datetime, timedelta
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
    page = browser.new_context(viewport={'width': 1280, 'height': 900}, locale='fr-FR').new_page()
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
    for prenom, nom in [('Jeanne', 'Durand'), ('Paul', 'Lefèvre'), ('Amina', 'Benali'), ('Louis', 'Martin'), ('Claire', 'Petit')]:
        sql(f"INSERT INTO membre (email, prenom, nom, actif) VALUES ('{prenom.lower()}@example.org', '{prenom}', '{nom}', 1)")
    page.goto(SITE + '/espaces/nouveau')
    page.select_option('select[name=type]', 'evenement')
    page.fill('#champ-titre', 'Visite du phare de la Garoupe')
    page.fill('#champ-description', "Visite commentée de la lanterne et de la salle des machines, suivie d'un pot au pied du phare.")
    page.select_option('select[name=visibilite]', 'adherents')
    page.click('form.formulaire button[type=submit]')
    slug = page.url.rsplit('/', 1)[1]
    debut = (datetime.now() + timedelta(days=24)).strftime('%Y-%m-%dT14:30')
    cloture = (datetime.now() + timedelta(days=10)).strftime('%Y-%m-%dT20:00')
    page.fill('#champ-debut', debut); page.fill('#champ-lieu', 'Phare de la Garoupe, Antibes')
    page.select_option('select[name=mode]', 'valide'); page.fill('#champ-places', '3'); page.fill('#champ-cloture', cloture)
    page.click('details[open] form button[type=submit]')
    eid = sql(f"SELECT id FROM espace WHERE slug = '{slug}'")
    # Historique : Jeanne vient souvent ; vœux de 5 personnes
    sql("INSERT INTO espace (type, titre, slug, description) VALUES ('evenement', 'AG', 'ag-passee', '')")
    sql("INSERT INTO evenement (espace_id, debut, etat) SELECT id, NOW() - INTERVAL 2 MONTH, 'attribuee' FROM espace WHERE slug = 'ag-passee'")
    sql("INSERT INTO inscription (evenement_id, membre_id, statut) SELECT s.id, m.id, 'retenu' FROM espace s, membre m WHERE s.slug = 'ag-passee' AND m.email IN ('jeanne@example.org', 'paul@example.org')")
    sql(f"INSERT INTO inscription (evenement_id, membre_id, statut) SELECT {eid}, id, 'voeu' FROM membre WHERE email IN ('jeanne@example.org', 'paul@example.org', 'amina@example.org', 'louis@example.org', 'claire@example.org')")
    page.click('button:has-text("Je souhaite venir")')
    page.screenshot(path=f'{OUT}/01-voeu-depose-ordinateur.png', full_page=True)
    mobile = browser.new_context(viewport={'width': 390, 'height': 844}, device_scale_factor=2, is_mobile=True, locale='fr-FR', storage_state=page.context.storage_state()).new_page()
    mobile.goto(page.url); mobile.screenshot(path=f'{OUT}/01-voeu-depose-telephone.png', full_page=True)
    page.on('dialog', lambda d: d.accept())
    page.click('button:has-text("Clôturer les vœux maintenant")')
    page.locator('#validation').scroll_into_view_if_needed()
    page.locator('#validation input[type=checkbox]').nth(4).check()
    page.screenshot(path=f'{OUT}/02-liste-a-valider-ordinateur.png', full_page=True)
    page.click('#validation button[type=submit]')
    page.screenshot(path=f'{OUT}/03-liste-validee-ordinateur.png', full_page=True)
    browser.close()
print('captures faites')
