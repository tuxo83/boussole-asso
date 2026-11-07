"""Faux serveur SMTP (tests) : accepte un message, ou refuse l'authentification si le mot de passe vaut « mauvais »."""
import base64, socketserver, sys

class Session(socketserver.StreamRequestHandler):
    def ecrire(self, ligne):
        self.wfile.write((ligne + '\r\n').encode())

    def handle(self):
        self.ecrire('220 simule')
        etape = None
        while True:
            ligne = self.rfile.readline().decode(errors='replace').rstrip('\r\n')
            if not ligne:
                return
            if etape == 'utilisateur':
                etape = 'mdp'; self.ecrire('334 UGFzc3dvcmQ6'); continue
            if etape == 'mdp':
                etape = None
                self.ecrire('535 5.7.8 Authentication failed' if base64.b64decode(ligne) == b'mauvais' else '235 ok'); continue
            commande = ligne.split(' ')[0].upper()
            if commande == 'EHLO': self.ecrire('250-simule'); self.ecrire('250 AUTH LOGIN')
            elif commande == 'AUTH': etape = 'utilisateur'; self.ecrire('334 VXNlcm5hbWU6')
            elif commande in ('MAIL', 'RCPT'): self.ecrire('250 ok')
            elif commande == 'DATA':
                self.ecrire('354 go')
                while self.rfile.readline().decode(errors='replace').rstrip('\r\n') != '.':
                    pass
                self.ecrire('250 queued')
            elif commande == 'QUIT': self.ecrire('221 bye'); return
            else: self.ecrire('500 ?')

socketserver.TCPServer.allow_reuse_address = True
with socketserver.ThreadingTCPServer(('127.0.0.1', int(sys.argv[1])), Session) as serveur:
    serveur.serve_forever()
