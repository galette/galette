-- Add amount to payment types
ALTER TABLE galette_types_cotisation ADD amount real DEFAULT '0';
-- Add region to members
ALTER TABLE galette_adherents ADD region_adh character varying(200) DEFAULT '' NOT NULL;
-- Add payment type to transactions
ALTER TABLE galette_transactions ADD type_paiement_trans integer NULL DEFAULT NULL;
ALTER TABLE galette_transactions ADD CONSTRAINT type_paiement_trans_fkey
    FOREIGN KEY (type_paiement_trans) REFERENCES galette_paymenttypes(type_id);