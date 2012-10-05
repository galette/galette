-- sequence for contributions types
DROP SEQUENCE IF EXISTS galette_types_cotisation_id_seq;
CREATE SEQUENCE galette_types_cotisation_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;
-- Set sequence value to handle existing data
SELECT setval('galette_types_cotisation_id_seq', (SELECT max(id_type_cotis) FROM galette_types_cotisation));

-- sequence for statuts
DROP SEQUENCE IF EXISTS galette_statuts_id_seq;
CREATE SEQUENCE galette_statuts_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;
-- Set sequence value to handle existing data
SELECT setval('galette_statuts_id_seq', (SELECT max(id_statut) FROM galette_statuts));

-- sequence for groups
DROP SEQUENCE IF EXISTS galette_groups_id_seq;
CREATE SEQUENCE galette_groups_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- sequence for mailing history
DROP SEQUENCE IF EXISTS galette_mailing_history_id_seq;
CREATE SEQUENCE galette_mailing_history_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- Each preference must be unique
CREATE UNIQUE INDEX galette_preferences_name ON galette_preferences (nom_pref);

-- Update languages
UPDATE galette_preferences SET val_pref='fr_FR' WHERE nom_pref='pref_lang' AND val_pref='french';
UPDATE galette_preferences SET val_pref='en_EN' WHERE nom_pref='pref_lang' AND val_pref='english';
-- spanish no longer exists, fallback to english
UPDATE galette_preferences SET val_pref='en_EN' WHERE nom_pref='pref_lang' AND val_pref='spanish';
UPDATE galette_adherents SET pref_lang='fr_FR' WHERE pref_lang='french';
UPDATE galette_adherents SET pref_lang='en_EN' WHERE pref_lang='english';
-- spanish no longer exists, fallback to english
UPDATE galette_adherents SET pref_lang='es_EN' WHERE pref_lang='spanish';
ALTER TABLE galette_adherents ALTER pref_lang SET DEFAULT 'fr_FR';
UPDATE galette_preferences SET nom_pref='pref_mail_smtp_host' WHERE nom_pref='pref_mail_smtp';

-- Table for dynamic required fields 2007-07-10;
DROP TABLE IF EXISTS galette_required;
CREATE TABLE galette_required (
	field_id character varying(20) NOT NULL,
	required boolean DEFAULT false NOT NULL,
	PRIMARY KEY (field_id)
);

-- Table for automatic mails and their translations 2007-10-22;
DROP TABLE IF EXISTS galette_texts;
CREATE TABLE galette_texts (
  tid integer DEFAULT nextval('galette_texts_id_seq'::text) NOT NULL,
  tref character varying(20) NOT NULL,
  tsubject character varying(256) NOT NULL,
  tbody text NOT NULL,
  tlang character varying(16) NOT NULL,
  tcomment character varying(64) NOT NULL,
  PRIMARY KEY (tid)
);

-- New table for fields categories
DROP TABLE IF EXISTS galette_fields_categories CASCADE;
CREATE TABLE galette_fields_categories (
  id_field_category integer  DEFAULT nextval('galette_fields_categories_id_seq'::text) NOT NULL,
  table_name character varying(30) NOT NULL,
  category character varying(50) NOT NULL,
  position integer NOT NULL,
  PRIMARY KEY (id_field_category)
);

DROP TABLE IF EXISTS galette_fields_config;
CREATE TABLE galette_fields_config (
  table_name character varying(30) NOT NULL,
  field_id character varying(30) NOT NULL,
  required boolean NOT NULL, -- should replace later galette_required(required)
  visible boolean NOT NULL,
  position integer NOT NULL,
  id_field_category integer REFERENCES galette_fields_categories ON DELETE RESTRICT ON UPDATE CASCADE,
  PRIMARY KEY (table_name, field_id)
);

-- Table for mailing history storage
DROP TABLE IF EXISTS galette_mailing_history;
CREATE TABLE galette_mailing_history (
  mailing_id integer DEFAULT nextval('galette_mailing_history_id_seq'::text) NOT NULL,
  mailing_sender integer REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
  mailing_subject character varying(255) NOT NULL,
  mailing_body text NOT NULL,
  mailing_date timestamp NOT NULL,
  mailing_recipients text NOT NULL,
  mailing_sent boolean DEFAULT FALSE,
  PRIMARY KEY (mailing_id)
);

-- table for groups
DROP TABLE IF EXISTS galette_groups CASCADE;
CREATE TABLE galette_groups (
  id_group integer DEFAULT nextval('galette_groups_id_seq'::text) NOT NULL,
  group_name character varying(50) NOT NULL CONSTRAINT name UNIQUE,
  creation_date timestamp NOT NULL,
  parent_group integer DEFAULT NULL REFERENCES galette_groups(id_group) ON DELETE RESTRICT ON UPDATE CASCADE,
  PRIMARY KEY (id_group)
);

-- table for groups managers
DROP TABLE IF EXISTS galette_groups_managers;
CREATE TABLE galette_groups_managers (
  id_group integer REFERENCES galette_groups(id_group) ON DELETE RESTRICT ON UPDATE CASCADE,
  id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
  PRIMARY KEY (id_group,id_adh)
);

-- table for groups members
DROP TABLE IF EXISTS galette_groups_members;
CREATE TABLE galette_groups_members (
  id_group integer REFERENCES galette_groups(id_group) ON DELETE RESTRICT ON UPDATE CASCADE,
  id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
  PRIMARY KEY (id_group,id_adh)
);

-- table for database version
DROP TABLE IF EXISTS galette_database;
CREATE TABLE galette_database (
  version decimal NOT NULL
);
INSERT INTO galette_database(version) VALUES(0.700);

ALTER TABLE galette_cotisations ADD type_paiement_cotis smallint DEFAULT '0' NOT NULL;

ALTER TABLE galette_adherents ADD societe_adh character varying(20) DEFAULT NULL;
ALTER TABLE galette_adherents ADD date_modif_adh date DEFAULT '1901-01-01' NOT NULL;

-- Missing auto increment
ALTER TABLE galette_types_cotisation ALTER id_type_cotis SET DEFAULT nextval('galette_types_cotisation_id_seq'::text);
ALTER TABLE galette_statuts ALTER id_statut SET DEFAULT nextval('galette_statuts_id_seq'::text);

-- Missing primary keys
ALTER TABLE galette_types_cotisation ADD CONSTRAINT galette_types_cotisation_pkey PRIMARY KEY (id_type_cotis);
ALTER TABLE galette_adherents ADD CONSTRAINT galette_adherents_pkey PRIMARY KEY (id_adh);
ALTER TABLE galette_dynamic_fields ADD CONSTRAINT galette_dynamic_fields_pkey PRIMARY KEY (item_id, field_id, field_form, val_index);
ALTER TABLE galette_cotisations ADD CONSTRAINT galette_cotisation_pkey PRIMARY KEY (id_cotis);
ALTER TABLE galette_transactions ADD CONSTRAINT galette_transactions_pkey PRIMARY KEY (trans_id);
ALTER TABLE galette_statuts ADD CONSTRAINT galette_statuts_pkey PRIMARY KEY (id_statut);
ALTER TABLE galette_preferences ADD CONSTRAINT galette_preferences_pkey PRIMARY KEY (id_pref);
ALTER TABLE galette_logs ADD CONSTRAINT galette_logs_pkey PRIMARY KEY (id_log);
ALTER TABLE galette_field_types ADD CONSTRAINT galette_field_types_pkey PRIMARY KEY (field_id);
ALTER TABLE galette_pictures ADD CONSTRAINT galette_pictures_pkey PRIMARY KEY (id_adh);
ALTER TABLE galette_l10n ADD CONSTRAINT galette_l10n_pkey PRIMARY KEY (text_orig, text_locale);
ALTER TABLE galette_tmppasswds ADD CONSTRAINT galette_tmppasswds_pkey PRIMARY KEY (id_adh);

-- change types
ALTER TABLE galette_adherents ALTER COLUMN bool_admin_adh DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN bool_exempt_adh DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN bool_display_info DROP DEFAULT;
ALTER TABLE galette_adherents ALTER COLUMN activite_adh DROP DEFAULT;
ALTER TABLE galette_adherents ALTER bool_admin_adh TYPE boolean USING CASE WHEN bool_admin_adh='1' THEN TRUE ELSE FALSE END;
ALTER TABLE galette_adherents ALTER bool_exempt_adh TYPE boolean USING CASE WHEN bool_exempt_adh='1' THEN TRUE ELSE FALSE END;
ALTER TABLE galette_adherents ALTER bool_display_info TYPE boolean USING CASE WHEN bool_display_info='1' THEN TRUE ELSE FALSE END;
ALTER TABLE galette_adherents ALTER activite_adh TYPE boolean USING CASE WHEN activite_adh='1' THEN TRUE ELSE FALSE END;
ALTER TABLE galette_adherents ALTER COLUMN bool_admin_adh SET DEFAULT FALSE;
ALTER TABLE galette_adherents ALTER COLUMN bool_exempt_adh SET DEFAULT FALSE;
ALTER TABLE galette_adherents ALTER COLUMN bool_display_info SET DEFAULT FALSE;
ALTER TABLE galette_adherents ALTER COLUMN activite_adh SET DEFAULT FALSE;

ALTER TABLE galette_types_cotisation ALTER COLUMN cotis_extension DROP DEFAULT;
ALTER TABLE galette_types_cotisation ALTER cotis_extension TYPE boolean USING CASE WHEN cotis_extension='1' THEN TRUE ELSE FALSE END;
ALTER TABLE galette_types_cotisation ALTER COLUMN cotis_extension SET DEFAULT FALSE;

ALTER TABLE galette_field_types ALTER COLUMN field_required DROP DEFAULT;
ALTER TABLE galette_field_types ALTER COLUMN field_repeat DROP DEFAULT;
ALTER TABLE galette_field_types ALTER field_required TYPE boolean USING CASE WHEN field_required='1' THEN TRUE ELSE FALSE END;
ALTER TABLE galette_field_types ALTER field_repeat TYPE boolean USING CASE WHEN field_repeat=1 THEN TRUE ELSE FALSE END;
ALTER TABLE galette_field_types ALTER COLUMN field_required SET DEFAULT FALSE;
ALTER TABLE galette_field_types ALTER COLUMN field_repeat SET DEFAULT FALSE;

ALTER TABLE galette_adherents ADD CONSTRAINT galette_adherents_id_statut_fkey FOREIGN KEY (id_statut) REFERENCES galette_statuts(id_statut);
