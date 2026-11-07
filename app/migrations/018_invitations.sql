-- Invitation de bienvenue : date du dernier envoi (pour relancer ceux qui ne se sont jamais connectés)
ALTER TABLE membre ADD COLUMN invite_le DATETIME NULL;
