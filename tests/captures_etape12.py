"""Captures de l'étape 12 (événement payant en phases : frise, place réservée à payer) pour le panel.
Usage : python tests/captures_etape12.py <adresse> <dossier des captures> <commande mysql>"""
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
    page.fill('#champ-titre', 'Sortie en mer aux îles de Lérins')
    page.fill('#champ-description', "Traversée commentée, déjeuner sur l'île Sainte-Marguerite et visite du fort.")
    page.select_option('select[name=visibilite]', 'adherents')
    page.click('form.formulaire button[type=submit]')
    slug = page.url.rsplit('/', 1)[1]
    eid = sql(f"SELECT id FROM espace WHERE slug = '{slug}'")
    page.fill('#champ-debut', (datetime.now() + timedelta(days=21)).strftime('%Y-%m-%dT09:00'))
    page.fill('#champ-lieu', 'Port de Cannes, quai Laubeuf')
    page.select_option('select[name=mode]', 'auto'); page.fill('#champ-places', '3')
    page.fill('#champ-cloture', (datetime.now() + timedelta(days=2)).strftime('%Y-%m-%dT20:00'))
    page.select_option('select[name=reglement]', 'paiement')
    page.fill('#champ-lien_paiement', 'https://www.helloasso.com/associations/les-amis-du-phare/evenements/sortie-en-mer')
    page.fill('#champ-delai_jours', '4'); page.fill('#champ-dernieres_jours', '5')
    page.click('details[open] form button[type=submit]')
    sql(f"INSERT INTO inscription (evenement_id, membre_id, statut) SELECT {eid}, id, 'voeu' FROM membre WHERE email IN ('jeanne@example.org', 'paul@example.org', 'amina@example.org', 'louis@example.org', 'claire@example.org')")
    page.click('button:has-text("Je souhaite venir")')
    page.screenshot(path=f'{OUT}/01-phase-voeux-ordinateur.png', full_page=True)
    page.on('dialog', lambda d: d.accept())
    page.click('button:has-text("Clôturer les vœux maintenant")')
    # Vue d'une personne retenue (l'organisatrice ici) : place réservée à payer
    sql(f"UPDATE inscription SET statut = 'retenu', rang = NULL, echeance = NOW() + INTERVAL 4 DAY WHERE evenement_id = {eid} AND membre_id = 1")
    sql(f"UPDATE inscription SET statut = 'attente', rang = 1, echeance = NULL WHERE evenement_id = {eid} AND membre_id = (SELECT id FROM membre WHERE email = 'claire@example.org')")
    page.goto(page.url)
    page.screenshot(path=f'{OUT}/02-place-a-payer-ordinateur.png', full_page=True)
    mobile = browser.new_context(viewport={'width': 390, 'height': 844}, device_scale_factor=2, is_mobile=True, locale='fr-FR', storage_state=page.context.storage_state()).new_page()
    mobile.goto(page.url)
    mobile.screenshot(path=f'{OUT}/02-place-a-payer-telephone.png', full_page=True)
    browser.close()
print('captures faites')
