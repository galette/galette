-- Add amount to payment types
ALTER TABLE galette_types_cotisation ADD amount double NULL;
-- Add region to members
ALTER TABLE galette_adherents ADD region_adh varchar(200) NOT NULL DEFAULT '';
-- Add payment type to transactions
ALTER TABLE galette_transactions ADD type_paiement_trans int(10) unsigned NULL DEFAULT NULL;
ALTER TABLE galette_transactions ADD FOREIGN KEY (type_paiement_trans)
    REFERENCES galette_paymenttypes (type_id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;
