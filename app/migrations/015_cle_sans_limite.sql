-- Clé d'API sans date d'expiration (au choix de son propriétaire, toujours révocable)
ALTER TABLE cle_api MODIFY expire_le DATE NULL;
