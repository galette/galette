SET FOREIGN_KEY_CHECKS=0;

-- add sender name and email in mailing history
ALTER TABLE galette_mailing_history ADD mailing_sender_name VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE galette_mailing_history ADD mailing_sender_address VARCHAR(255) NULL DEFAULT NULL;

UPDATE galette_database SET version = 0.91;
SET FOREIGN_KEY_CHECKS=1;
