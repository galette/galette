-- add sender name and email in mailing history
ALTER TABLE galette_mailing_history ADD COLUMN mailing_sender_name character varying(100) DEFAULT NULL;
ALTER TABLE galette_mailing_history ADD COLUMN mailing_sender_address character varying(255) DEFAULT NULL;

UPDATE galette_database SET version = 0.91;
