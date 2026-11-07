"""Captures de la vitrine (accueil, page, espaces) d'un site déjà rempli. Usage : python tests/captures_vitrine.py <adresse> <dossier> [mot de passe admin]"""
import sys, time
from playwright.sync_api import sync_playwright
SITE, OUT = sys.argv[1].rstrip('/'), sys.argv[2]
with sync_playwright() as p:
    b = p.chromium.launch()
    for nom, opts in [('ordinateur', {'viewport': {'width': 1280, 'height': 860}}), ('telephone', {'viewport': {'width': 390, 'height': 844}, 'device_scale_factor': 2, 'is_mobile': True})]:
        page = b.new_context(locale='fr-FR', **opts).new_page()
        page.goto(SITE + '/'); page.screenshot(path=f'{OUT}/01-accueil-{nom}.png', full_page=True)
        page.goto(SITE + '/page/nos-actions'); page.screenshot(path=f'{OUT}/02-nos-actions-{nom}.png', full_page=True)
        if len(sys.argv) > 3 and nom == 'ordinateur':
            page.goto(SITE + '/connexion'); page.fill('#champ-email', 'admin@example.org'); page.fill('#champ-mot_de_passe', sys.argv[3]); time.sleep(2.2)
            page.click('button[type=submit]'); page.wait_for_load_state('networkidle')
            page.goto(SITE + '/espaces'); page.screenshot(path=f'{OUT}/03-espaces-{nom}.png', full_page=True)
    b.close()
print('captures faites')
