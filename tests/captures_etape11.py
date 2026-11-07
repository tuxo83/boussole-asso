"""Captures du mode « site en construction » (page du code d'accès, bandeau). Site déjà installé.
Usage : python tests/captures_etape11.py <adresse> <dossier des captures> <commande mysql>"""
import subprocess, sys
from playwright.sync_api import sync_playwright

SITE, OUT, MYSQL = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
sql = lambda q: subprocess.run(MYSQL.split() + ['-N', '-e', q], capture_output=True, text=True).stdout.strip()
sql("REPLACE INTO parametre (cle, valeur) VALUES ('construction_active', '1'), ('construction_code', '482915')")
with sync_playwright() as p:
    browser = p.chromium.launch()
    for nom, opts in [('ordinateur', {'viewport': {'width': 1280, 'height': 800}}), ('telephone', {'viewport': {'width': 390, 'height': 844}, 'device_scale_factor': 2, 'is_mobile': True})]:
        page = browser.new_context(locale='fr-FR', **opts).new_page()
        page.goto(SITE + '/')
        page.screenshot(path=f'{OUT}/01-code-acces-{nom}.png', full_page=True)
        page.fill('#champ-code', '482915')
        page.click('button[type=submit]')
        page.screenshot(path=f'{OUT}/02-site-avec-bandeau-{nom}.png', full_page=True)
    browser.close()
sql("DELETE FROM parametre WHERE cle IN ('construction_active', 'construction_code')")
print('captures faites')
