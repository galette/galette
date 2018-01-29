-- add sender name and email in mailing history
ALTER TABLE galette_mailing_history ADD COLUMN mailing_sender_name character varying(100) DEFAULT NULL;
ALTER TABLE galette_mailing_history ADD COLUMN mailing_sender_address character varying(255) DEFAULT NULL;

-- fix email field size
ALTER TABLE galette_adherents ALTER COLUMN email_adh TYPE varchar(255);

-- fix gpg field size
ALTER TABLE galette_adherents ALTER COLUMN gpgid TYPE text;

UPDATE galette_database SET version = 0.91;
