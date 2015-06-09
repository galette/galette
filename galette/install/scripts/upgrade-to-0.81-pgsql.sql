-- Pref numrows can no longer equals to 0
UPDATE galette_preferences SET val_pref = '100' WHERE nom_pref = 'pref_numrows' AND val_pref = '0';

UPDATE galette_database SET version = 0.81;
