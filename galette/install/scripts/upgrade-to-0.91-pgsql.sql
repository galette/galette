-- add sender name and email in mailing history
ALTER TABLE galette_mailing_history ADD COLUMN mailing_sender_name character varying(100) DEFAULT NULL;
ALTER TABLE galette_mailing_history ADD COLUMN mailing_sender_address character varying(255) DEFAULT NULL;

-- fix email field size
ALTER TABLE galette_adherents ALTER COLUMN email_adh TYPE varchar(255);

-- fix gpg field size
ALTER TABLE galette_adherents ALTER COLUMN gpgid TYPE text;

-- Clean possible buggy data from RC
DELETE FROM galette_dynamic_fields WHERE item_id = 0;

-- Detailled log history has ben dropped
UPDATE galette_preferences SET val_pref = 1 WHERE nom_pref = 'pref_log' AND val_pref = '2';

UPDATE galette_database SET version = 0.91;
