-- $Id$
DROP SEQUENCE IF EXISTS galette_adherents_id_seq;
CREATE SEQUENCE galette_adherents_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP SEQUENCE IF EXISTS galette_cotisations_id_seq;
CREATE SEQUENCE galette_cotisations_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- sequence for statuts
DROP SEQUENCE IF EXISTS galette_statuts_id_seq;
CREATE SEQUENCE galette_statuts_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP SEQUENCE IF EXISTS galette_transactions_id_seq;
CREATE SEQUENCE galette_transactions_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP SEQUENCE IF EXISTS galette_preferences_id_seq;
CREATE SEQUENCE galette_preferences_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP SEQUENCE IF EXISTS galette_logs_id_seq;
CREATE SEQUENCE galette_logs_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;
    
-- Sequence for dynamic fields description;
DROP SEQUENCE IF EXISTS galette_field_types_id_seq;
CREATE SEQUENCE galette_field_types_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- sequence for contributions types
DROP SEQUENCE IF EXISTS galette_types_cotisation_id_seq;
CREATE SEQUENCE galette_types_cotisation_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

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

-- sequence for title
DROP SEQUENCE IF EXISTS galette_titles_id_seq;
CREATE SEQUENCE galette_titles_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- sequence for reminders
DROP SEQUENCE IF EXISTS galette_reminders_id_seq;
CREATE SEQUENCE galette_reminders_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- sequence for pdf models
DROP SEQUENCE IF EXISTS galette_pdfmodels_id_seq;
CREATE SEQUENCE galette_pdfmodels_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- sequence for import model
DROP SEQUENCE IF EXISTS galette_import_model_id_seq;
CREATE SEQUENCE galette_import_model_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- Schema
-- REMINDER: Create order IS important, dependencies first !!
DROP TABLE IF EXISTS galette_statuts CASCADE;
CREATE TABLE galette_statuts (
  id_statut integer DEFAULT nextval('galette_statuts_id_seq'::text) NOT NULL,
  libelle_statut  character varying(20) DEFAULT '' NOT NULL,
  priorite_statut smallint DEFAULT '0' NOT NULL,
  PRIMARY KEY (id_statut)
);

DROP TABLE IF EXISTS galette_titles CASCADE;
CREATE TABLE galette_titles (
  id_title integer DEFAULT nextval('galette_titles_id_seq'::text) NOT NULL,
  short_label character varying(10) DEFAULT '' NOT NULL,
  long_label character varying(30) DEFAULT '',
  PRIMARY KEY (id_title)
);

DROP TABLE IF EXISTS galette_adherents CASCADE;
CREATE TABLE galette_adherents (
    id_adh integer DEFAULT nextval('galette_adherents_id_seq'::text) NOT NULL,
    id_statut integer DEFAULT '4' REFERENCES galette_statuts(id_statut) ON DELETE RESTRICT ON UPDATE CASCADE,
    nom_adh character varying(50) DEFAULT '' NOT NULL,
    prenom_adh character varying(50) DEFAULT '' NOT NULL,
    societe_adh character varying(200) DEFAULT NULL,
    pseudo_adh character varying(20) DEFAULT '' NOT NULL,
    titre_adh integer DEFAULT NULL REFERENCES galette_titles(id_title) ON DELETE RESTRICT ON UPDATE CASCADE,
    ddn_adh date DEFAULT '19010101',
    sexe_adh smallint DEFAULT '0' NOT NULL,
    adresse_adh character varying(150) DEFAULT '' NOT NULL,
    adresse2_adh character varying(150) DEFAULT NULL,
    cp_adh character varying(10) DEFAULT '' NOT NULL,
    ville_adh character varying(50) DEFAULT '' NOT NULL,
    pays_adh character varying(50) DEFAULT NULL,
    tel_adh character varying(20),
    gsm_adh character varying(20),
    email_adh character varying(150),
    url_adh character varying(200),
    icq_adh character varying(20),
    msn_adh character varying(150),
    jabber_adh character varying(150),
    info_adh text,
    info_public_adh text,
    prof_adh character varying(150),
    login_adh character varying(20) DEFAULT '' NOT NULL,
    mdp_adh character varying(60) DEFAULT '' NOT NULL,
    date_crea_adh date DEFAULT '19010101' NOT NULL,
    date_modif_adh date DEFAULT '19010101' NOT NULL,
    activite_adh boolean DEFAULT FALSE,
    bool_admin_adh boolean DEFAULT FALSE,
    bool_exempt_adh boolean DEFAULT FALSE,
    bool_display_info boolean DEFAULT FALSE,
    date_echeance date,
    pref_lang character varying(20) DEFAULT 'fr_FR',
    lieu_naissance text DEFAULT '',
    gpgid character varying(8) DEFAULT NULL,
    fingerprint character varying(50) DEFAULT NULL,
    PRIMARY KEY (id_adh)
);
-- add index for faster search on login_adh (auth)
CREATE UNIQUE INDEX galette_adherents_login_adh_idx ON galette_adherents (login_adh);

DROP TABLE IF EXISTS galette_types_cotisation CASCADE;
CREATE TABLE galette_types_cotisation (
  id_type_cotis integer DEFAULT nextval('galette_types_cotisation_id_seq'::text) NOT NULL,
  libelle_type_cotis character varying(30) DEFAULT '' NOT NULL,
  cotis_extension boolean DEFAULT FALSE,
  PRIMARY KEY (id_type_cotis)
);

DROP TABLE IF EXISTS galette_transactions CASCADE;
CREATE TABLE galette_transactions (
    trans_id integer DEFAULT nextval('galette_transactions_id_seq'::text)  NOT NULL,
    trans_date date DEFAULT '19010101' NOT NULL,
    trans_amount real DEFAULT '0',
    trans_desc character varying(150) NOT NULL DEFAULT '',
    id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
    PRIMARY KEY (trans_id)
);

DROP TABLE IF EXISTS galette_cotisations;
CREATE TABLE galette_cotisations (
    id_cotis integer DEFAULT nextval('galette_cotisations_id_seq'::text)  NOT NULL,
    id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
    id_type_cotis integer REFERENCES galette_types_cotisation (id_type_cotis) ON DELETE RESTRICT ON UPDATE CASCADE,
    montant_cotis real DEFAULT '0',
    type_paiement_cotis smallint DEFAULT '0' NOT NULL,
    info_cotis text,
    date_enreg date DEFAULT '19010101' NOT NULL,
    date_debut_cotis date DEFAULT '19010101' NOT NULL,
    date_fin_cotis date DEFAULT '19010101' NOT NULL,
    trans_id integer DEFAULT NULL REFERENCES galette_transactions (trans_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    PRIMARY KEY (id_cotis)
);

DROP TABLE IF EXISTS galette_preferences;
CREATE TABLE galette_preferences (
  id_pref integer DEFAULT nextval('galette_preferences_id_seq'::text) NOT NULL,
  nom_pref character varying(100) DEFAULT '' NOT NULL,
  val_pref character varying(200) DEFAULT '' NOT NULL,
  PRIMARY KEY (id_pref)
);
-- add index, nom_pref is used as foreign key elsewhere
CREATE UNIQUE INDEX galette_preferences_nom_pref_idx ON galette_preferences (nom_pref);

DROP TABLE IF EXISTS galette_logs;
CREATE TABLE galette_logs (
  id_log integer DEFAULT nextval('galette_logs_id_seq'::text) NOT NULL,
  date_log timestamp NOT NULL,
  ip_log character varying(30) DEFAULT '' NOT NULL,
  adh_log character varying(41) DEFAULT '' NOT NULL,
  text_log text,
  action_log text,
  sql_log text,
  PRIMARY KEY (id_log)
);

-- Table for dynamic fields description;
DROP TABLE IF EXISTS galette_field_types CASCADE;
CREATE TABLE galette_field_types (
  field_id integer DEFAULT nextval('galette_field_types_id_seq'::text) NOT NULL,
  field_form character varying(10) NOT NULL,
  field_index integer DEFAULT '0' NOT NULL,
  field_name character varying(40) DEFAULT '' NOT NULL,
  field_perm integer DEFAULT '0' NOT NULL,
  field_type integer DEFAULT '0' NOT NULL,
  field_required boolean DEFAULT FALSE,
  field_pos integer DEFAULT '0' NOT NULL,
  field_width integer DEFAULT NULL,
  field_height integer DEFAULT NULL,
  field_size integer DEFAULT NULL,
  field_repeat integer DEFAULT NULL,
  field_layout integer DEFAULT NULL,
  PRIMARY KEY (field_id)
);
-- add index, field_form is used elsewhere
CREATE INDEX galette_field_types_field_form_idx ON galette_field_types (field_form);

-- Table for dynamic fields data;
DROP TABLE IF EXISTS galette_dynamic_fields;
CREATE TABLE galette_dynamic_fields (
  item_id integer DEFAULT '0' NOT NULL, -- could be id_adh, trans_id, id_cotis
  field_id integer REFERENCES galette_field_types (field_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  field_form character varying(10) NOT NULL, -- not an fkey!
  val_index integer DEFAULT '0' NOT NULL,
  field_val text DEFAULT '',
  PRIMARY KEY (item_id, field_id, field_form, val_index)
);

DROP TABLE IF EXISTS galette_pictures;
CREATE TABLE galette_pictures (
  id_adh integer DEFAULT '0' NOT NULL,
  picture bytea NOT NULL,
  format character varying(30) DEFAULT '' NOT NULL,
  PRIMARY KEY (id_adh)
);

-- Table for dynamic translation of strings;
DROP TABLE IF EXISTS galette_l10n;
CREATE TABLE galette_l10n (
  text_orig character varying(40) NOT NULL,
  text_locale character varying(15) NOT NULL,
  text_nref integer DEFAULT '1' NOT NULL,
  text_trans character varying(40) DEFAULT '' NOT NULL,
  PRIMARY KEY (text_orig, text_locale)
);

-- new table for temporary passwords  2006-02-18;
DROP TABLE IF EXISTS galette_tmppasswds;
CREATE TABLE galette_tmppasswds (
  id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
  tmp_passwd character varying(60) NOT NULL,
  date_crea_tmp_passwd timestamp NOT NULL,
  PRIMARY KEY (id_adh)
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
  required boolean NOT NULL,
  visible integer NOT NULL,
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
DROP TABLE IF EXISTS galette_groups_managers CASCADE;
CREATE TABLE galette_groups_managers (
  id_group integer REFERENCES galette_groups(id_group) ON DELETE RESTRICT ON UPDATE CASCADE,
  id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
  PRIMARY KEY (id_group,id_adh)
);

-- table for groups members
DROP TABLE IF EXISTS galette_groups_members CASCADE;
CREATE TABLE galette_groups_members (
  id_group integer REFERENCES galette_groups(id_group) ON DELETE RESTRICT ON UPDATE CASCADE,
  id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
  PRIMARY KEY (id_group,id_adh)
);

-- Table for reminders
DROP TABLE IF EXISTS galette_reminders;
CREATE TABLE galette_reminders (
  reminder_id integer DEFAULT nextval('galette_reminders_id_seq'::text) NOT NULL,
  reminder_type integer NOT NULL,
  reminder_dest integer REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
  reminder_date timestamp NOT NULL,
  reminder_success boolean DEFAULT FALSE,
  reminder_nomail boolean DEFAULT TRUE,
  reminder_comment text,
  PRIMARY KEY (reminder_id)
);

DROP TABLE IF EXISTS galette_pdfmodels CASCADE;
CREATE TABLE galette_pdfmodels (
  model_id integer DEFAULT nextval('galette_pdfmodels_id_seq'::text) NOT NULL,
  model_name character varying(50) NOT NULL,
  model_type integer NOT NULL,
  model_header text,
  model_footer text,
  model_body text,
  model_styles text,
  model_title character varying(100),
  model_subtitle character varying(100),
  model_parent integer DEFAULT NULL REFERENCES galette_pdfmodels (model_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  PRIMARY KEY (model_id)
);

-- Table for import models
DROP TABLE IF EXISTS galette_import_model;
CREATE TABLE galette_import_model (
  model_id integer DEFAULT nextval('galette_import_model_id_seq'::text) NOT NULL,
  model_fields text,
  model_creation_date timestamp NOT NULL,
  PRIMARY KEY (model_id)
);

-- table for database version
DROP TABLE IF EXISTS galette_database;
CREATE TABLE galette_database (
  version decimal NOT NULL
);
INSERT INTO galette_database (version) VALUES(0.704);
