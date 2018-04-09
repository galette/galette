SET FOREIGN_KEY_CHECKS=0;

-- add sender name and email in mailing history
ALTER TABLE galette_mailing_history ADD mailing_sender_name VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE galette_mailing_history ADD mailing_sender_address VARCHAR(255) NULL DEFAULT NULL;

-- fix email field size
ALTER TABLE galette_adherents CHANGE email_adh email_adh VARCHAR(255) DEFAULT NULL;

-- fix gpg field size
ALTER TABLE galette_adherents CHANGE gpgid gpgid TEXT;

-- Clean possible buggy data from RC
DELETE FROM galette_dynamic_fields WHERE item_id = 0;

-- Fix DB relations
UPDATE galette_cotisations
    LEFT JOIN galette_transactions ON galette_cotisations.trans_id = galette_transactions.trans_id
    SET galette_cotisations.trans_id=null
    WHERE galette_cotisations.trans_id IS NOT NULL AND galette_transactions.trans_id IS NULL;
ALTER TABLE galette_cotisations ADD FOREIGN KEY (trans_id) REFERENCES galette_transactions(trans_id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Detailled log history has ben dropped
UPDATE galette_preferences SET val_pref = 1 WHERE nom_pref = 'pref_log' AND val_pref = 2;

UPDATE galette_database SET version = 0.91;
SET FOREIGN_KEY_CHECKS=1;
