-- Add amount to payment types
ALTER TABLE galette_types_cotisation ADD amount real DEFAULT '0';
-- Add region to members
ALTER TABLE galette_adherents ADD region_adh character varying(200) DEFAULT '' NOT NULL;
-- Add payment type to transactions
ALTER TABLE galette_transactions ADD type_paiement_trans integer NULL DEFAULT NULL;
ALTER TABLE galette_transactions ADD CONSTRAINT type_paiement_trans_fkey
    FOREIGN KEY (type_paiement_trans) REFERENCES galette_paymenttypes(type_id);
-- Add field_min_size to galette_field_types
ALTER TABLE galette_field_types ADD field_min_size integer NULL DEFAULT NULL;
-- Add display properties to core fields
ALTER TABLE galette_fields_config ADD width_in_forms integer DEFAULT '1' NOT NULL;
-- Add display properties to dynamic fields
ALTER TABLE galette_field_types ADD field_width_in_forms integer DEFAULT '1' NOT NULL;
ALTER TABLE galette_field_types ADD field_information_above boolean DEFAULT FALSE;

-- change dynamic fields permissions
ALTER TABLE galette_field_types ALTER COLUMN field_perm SET DEFAULT 1;

-- sequence for documents
DROP SEQUENCE IF EXISTS galette_documents_id_seq;
CREATE SEQUENCE galette_documents_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- table for documents
DROP TABLE IF EXISTS galette_documents CASCADE;
CREATE TABLE galette_documents (
  id_document integer DEFAULT nextval('galette_documents_id_seq'::text) NOT NULL,
  type character varying(250) NOT NULL,
  visible integer NOT NULL,
  filename character varying(255) DEFAULT NULL,
  comment text,
  creation_date timestamp NOT NULL,
  PRIMARY KEY (id_document)
);
-- add index on table to look for type
CREATE INDEX galette_documents_idx ON galette_documents (type);
