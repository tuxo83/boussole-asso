"""Serveur FTP local pour tester le déploiement (FTPS si un certificat est donné).
Usage : python tests/serveur_ftp.py <port> <dossier> <utilisateur> <mot de passe> [certificat.pem]"""
import sys
from pyftpdlib.authorizers import DummyAuthorizer
from pyftpdlib.handlers import FTPHandler, TLS_FTPHandler
from pyftpdlib.servers import FTPServer

port, root, user, password = int(sys.argv[1]), sys.argv[2], sys.argv[3], sys.argv[4]
auth = DummyAuthorizer()
auth.add_user(user, password, root, perm='elradfmwMT')
handler = FTPHandler
if len(sys.argv) > 5:
    handler = TLS_FTPHandler
    handler.certfile = sys.argv[5]
    handler.tls_control_required = True
    handler.tls_data_required = True
handler.authorizer = auth
handler.passive_ports = range(30000, 30100)
FTPServer(('127.0.0.1', port), handler).serve_forever()
