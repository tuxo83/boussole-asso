"""Captures des espaces et de la gestion des membres pour la relecture du panel (étape 3).
Usage : SQL_DEMO="mysql …" python tests/captures_etape3.py <adresse du site> <dossier des captures>"""
import base64, hashlib, hmac, os, struct, subprocess, sys, time
from playwright.sync_api import sync_playwright

SITE, OUT = sys.argv[1].rstrip('/'), sys.argv[2]
MDP = 'mot-de-passe-de-demo-1'
SEED = """
INSERT INTO membre (email, prenom, nom, type_membre_id) VALUES
 ('claire@example.org','Claire','Martin',1), ('hugo@example.org','Hugo','Bernard',1),
 ('monique@example.org','Monique','Petit',1), ('karim@example.org','Karim','Diallo',2);
INSERT INTO espace (type, titre, slug, description, visibilite, meteo) VALUES
 ('comite','Comité de direction','comite-de-direction','Pilotage de l''association : budget, programme, représentation.','equipe','soleil'),
 ('commission','Commission patrimoine','commission-patrimoine','Inventaire et restauration des biens de l''association.','adherents','eclaircie'),
 ('projet','Restauration de la lanterne','restauration-lanterne','Chantier bénévole de printemps : décapage, peinture, vitrage.','adherents','pluie'),
 ('evenement','Journées du patrimoine','journees-du-patrimoine','Visites guidées du phare, samedi et dimanche.','public','eclaircie');
UPDATE espace SET parent_id = (SELECT id FROM (SELECT id FROM espace WHERE slug='commission-patrimoine') t) WHERE slug='restauration-lanterne';
INSERT INTO espace_membre (espace_id, membre_id, niveau, libelle)
 SELECT e.id, m.id, x.niveau, x.libelle FROM (
  SELECT 'comite-de-direction' s, 'claire@example.org' em, 'n1' niveau, 'Présidente' libelle UNION ALL
  SELECT 'comite-de-direction', 'hugo@example.org', 'n2', 'Secrétaire' UNION ALL
  SELECT 'comite-de-direction', 'admin@example.org', 'membre', 'Trésorier' UNION ALL
  SELECT 'comite-de-direction', 'monique@example.org', 'associe', 'Sage' UNION ALL
  SELECT 'restauration-lanterne', 'karim@example.org', 'n1', '') x
 JOIN espace e ON e.slug = x.s JOIN membre m ON m.email = x.em;
INSERT INTO jalon (espace_id, date_jalon, libelle, fait)
 SELECT id, '2026-10-15', 'Budget prévisionnel voté', 1 FROM espace WHERE slug='comite-de-direction' UNION ALL
 SELECT id, '2026-11-02', 'Convocation de l''assemblée générale', 0 FROM espace WHERE slug='comite-de-direction' UNION ALL
 SELECT id, '2026-12-12', 'Assemblée générale', 0 FROM espace WHERE slug='comite-de-direction';
INSERT INTO journal (espace_id, membre_id, action)
 SELECT e.id, m.id, 'Météo : ☀️ À jour' FROM espace e, membre m WHERE e.slug='comite-de-direction' AND m.email='claire@example.org';
"""

def totp(secret):
    key = base64.b32decode(secret + '=' * (-len(secret) % 8))
    digest = hmac.new(key, struct.pack('>Q', int(time.time()) // 30), hashlib.sha1).digest()
    offset = digest[19] & 15
    return '%06d' % ((struct.unpack('>I', digest[offset:offset + 4])[0] & 0x7fffffff) % 1000000)

def shot(page, name):
    page.screenshot(path=f'{OUT}/{name}.png', full_page=True)

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_context(viewport={'width': 1280, 'height': 860}, locale='fr-FR').new_page()
    page.goto(SITE + '/installation')
    page.fill('#champ-nom_association', 'Les Amis du Phare')
    page.fill('#champ-email_admin', 'admin@example.org')
    page.fill('#champ-mot_de_passe', MDP)
    page.fill('#champ-confirmation', MDP)
    page.click('button[type=submit]')
    page.wait_for_url(SITE + '/')
    subprocess.run(os.environ['SQL_DEMO'], shell=True, check=True, input=SEED.encode())
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
    # Un fichier déposé par le formulaire réel
    page.goto(SITE + '/espace/comite-de-direction')
    page.set_input_files('input[name=fichier]', files=[{'name': 'ordre-du-jour-AG.pdf', 'mimeType': 'application/pdf', 'buffer': b'%PDF-1.4 demo'}])
    page.click('form[enctype] button[type=submit]')
    page.wait_for_url(SITE + '/espace/comite-de-direction')
    for path, name in [('/espaces', '01-espaces'), ('/espace/comite-de-direction', '02-espace-comite'),
                       ('/espace/restauration-lanterne', '03-espace-projet-alertes'), ('/admin/membres', '04-membres')]:
        page.goto(SITE + path)
        shot(page, name + '-ordinateur')
    mobile = browser.new_context(viewport={'width': 390, 'height': 844}, device_scale_factor=2, is_mobile=True, has_touch=True, locale='fr-FR')
    m = mobile.new_page()
    m.context.add_cookies(page.context.cookies())
    for path, name in [('/espaces', '01-espaces'), ('/espace/comite-de-direction', '02-espace-comite')]:
        m.goto(SITE + path)
        shot(m, name + '-telephone')
    browser.close()
print('captures faites')
