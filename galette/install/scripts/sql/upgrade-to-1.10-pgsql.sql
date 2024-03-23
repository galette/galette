-- Add amount to payment types
ALTER TABLE galette_types_cotisation ADD amount decimal(15,2) NULL DEFAULT NULL;
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

-- change fields types and default values
ALTER TABLE galette_cotisations ALTER COLUMN montant_cotis TYPE decimal(15,2);
ALTER TABLE galette_cotisations ALTER COLUMN montant_cotis DROP DEFAULT;
ALTER TABLE galette_cotisations ALTER COLUMN montant_cotis SET NOT NULL;
ALTER TABLE galette_transactions ALTER COLUMN trans_amount TYPE decimal(15,2);
ALTER TABLE galette_transactions ALTER COLUMN trans_amount DROP DEFAULT;
ALTER TABLE galette_transactions ALTER COLUMN trans_amount SET NOT NULL;

-- sequence for payments schedules
DROP SEQUENCE IF EXISTS galette_payments_schedules_id_seq;
CREATE SEQUENCE galette_payments_schedules_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- table for payments schedules
DROP TABLE IF EXISTS galette_payments_schedules CASCADE;
CREATE TABLE galette_payments_schedules (
  id_schedule integer DEFAULT nextval('galette_payments_schedules_id_seq'::text) NOT NULL,
  id_cotis integer REFERENCES galette_cotisations (id_cotis) ON DELETE CASCADE ON UPDATE CASCADE,
  id_paymenttype integer REFERENCES galette_paymenttypes (type_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  creation_date date NOT NULL,
  scheduled_date date NOT NULL,
  amount decimal(15,2) NOT NULL,
  paid boolean DEFAULT FALSE,
  comment text,
  PRIMARY KEY (id_schedule)
);
-- change fields types and default values
ALTER TABLE galette_cotisations ALTER COLUMN montant_cotis TYPE decimal(15,2);
ALTER TABLE galette_cotisations ALTER COLUMN montant_cotis DROP DEFAULT;
ALTER TABLE galette_cotisations ALTER COLUMN montant_cotis SET NOT NULL;
ALTER TABLE galette_transactions ALTER COLUMN trans_amount TYPE decimal(15,2);
ALTER TABLE galette_transactions ALTER COLUMN trans_amount DROP DEFAULT;
ALTER TABLE galette_transactions ALTER COLUMN trans_amount SET NOT NULL;