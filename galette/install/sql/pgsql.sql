DROP SEQUENCE galette_adherents_id_seq;
CREATE SEQUENCE galette_adherents_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP SEQUENCE galette_cotisations_id_seq;
CREATE SEQUENCE galette_cotisations_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP SEQUENCE galette_transactions_id_seq;
CREATE SEQUENCE galette_transactions_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP SEQUENCE galette_preferences_id_seq;
CREATE SEQUENCE galette_preferences_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE galette_adherents;
CREATE TABLE galette_adherents (
    id_adh integer DEFAULT nextval('galette_adherents_id_seq'::text) NOT NULL,
    id_statut integer DEFAULT '4' NOT NULL,
    nom_adh character varying(20) DEFAULT '' NOT NULL,
    prenom_adh character varying(20) DEFAULT NULL,
    pseudo_adh character varying(20) DEFAULT NULL,
    titre_adh smallint DEFAULT '0' NOT NULL,
    ddn_adh date DEFAULT '19010101',
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
    mdp_adh character varying(40) DEFAULT '' NOT NULL,
    date_crea_adh date DEFAULT '00000101' NOT NULL,
    activite_adh character(1) DEFAULT '0' NOT NULL,
    bool_admin_adh character(1) DEFAULT NULL,
    bool_exempt_adh character(1) DEFAULT NULL,
    bool_display_info character(1) DEFAULT NULL,
    date_echeance date,
    pref_lang character varying(20) DEFAULT 'fr_FR',
    lieu_naissance text DEFAULT '',
    gpgid character varying(8) DEFAULT NULL,
    fingerprint character varying(50) DEFAULT NULL
);
CREATE UNIQUE INDEX galette_adherents_idx ON galette_adherents (id_adh);
CREATE UNIQUE INDEX galette_login_idx     ON galette_adherents (login_adh);

DROP TABLE galette_cotisations;
CREATE TABLE galette_cotisations (
    id_cotis integer DEFAULT nextval('galette_cotisations_id_seq'::text)  NOT NULL,
    id_adh integer DEFAULT '0' NOT NULL,
    id_type_cotis integer DEFAULT '0' NOT NULL,
    montant_cotis real DEFAULT '0',
    info_cotis text,
    date_enreg date DEFAULT '00000101' NOT NULL,
    date_debut_cotis date DEFAULT '00000101' NOT NULL,
    date_fin_cotis date DEFAULT '00000101' NOT NULL,
    trans_id integer DEFAULT NULL
);
CREATE UNIQUE INDEX galette_cotisations_idx ON galette_cotisations (id_cotis);

DROP TABLE galette_transactions;
CREATE TABLE galette_transactions (
    trans_id integer DEFAULT nextval('galette_transactions_id_seq'::text)  NOT NULL,
    trans_date date DEFAULT '00000101' NOT NULL,
    trans_amount real DEFAULT '0',
    trans_desc character varying(30) NOT NULL DEFAULT '',
    id_adh integer DEFAULT NULL
);
CREATE UNIQUE INDEX galette_transactions_idx ON galette_transactions (trans_id);

DROP TABLE galette_statuts;
CREATE TABLE galette_statuts (
  id_statut integer NOT NULL,
  libelle_statut  character varying(20) DEFAULT '' NOT NULL,
  priorite_statut smallint DEFAULT '0' NOT NULL
);
CREATE UNIQUE INDEX galette_statuts_idx ON galette_statuts (id_statut);

DROP TABLE galette_types_cotisation;
CREATE TABLE galette_types_cotisation (
  id_type_cotis integer NOT NULL,
  libelle_type_cotis character varying(30) DEFAULT '' NOT NULL,
  cotis_extension character(1) DEFAULT NULL
);
CREATE UNIQUE INDEX galette_types_cotisation_idx ON galette_types_cotisation (id_type_cotis);

DROP TABLE galette_preferences;
CREATE TABLE galette_preferences (
  id_pref integer DEFAULT nextval('galette_preferences_id_seq'::text) NOT NULL,
  nom_pref character varying(100) DEFAULT '' NOT NULL,
  val_pref character varying(200) DEFAULT '' NOT NULL
);
CREATE UNIQUE INDEX galette_preferences_idx ON galette_preferences (id_pref);

DROP SEQUENCE galette_logs_id_seq;
CREATE SEQUENCE galette_logs_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE galette_logs;
CREATE TABLE galette_logs (
  id_log integer DEFAULT nextval('galette_logs_id_seq'::text) NOT NULL,
  date_log timestamp NOT NULL,
  ip_log character varying(30) DEFAULT '' NOT NULL,
  adh_log character varying(41) DEFAULT '' NOT NULL,
  text_log text,
  action_log text,
  sql_log text
);
CREATE UNIQUE INDEX galette_logs_idx ON galette_logs (id_log);

-- Sequence for dynamic fields description;
DROP SEQUENCE galette_field_types_id_seq;
CREATE SEQUENCE galette_field_types_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- Table for dynamic fields description;
DROP TABLE galette_field_types;
CREATE TABLE galette_field_types (
  field_id integer DEFAULT nextval('galette_field_types_id_seq'::text) NOT NULL,
  field_form character varying(10) NOT NULL,
  field_index integer DEFAULT '0' NOT NULL,
  field_name character varying(40) DEFAULT '' NOT NULL,
  field_perm integer DEFAULT '0' NOT NULL,
  field_type integer DEFAULT '0' NOT NULL,
  field_required character(1) DEFAULT NULL,
  field_pos integer DEFAULT '0' NOT NULL,
  field_width integer DEFAULT NULL,
  field_height integer DEFAULT NULL,
  field_size integer DEFAULT NULL,
  field_repeat integer DEFAULT NULL,
  field_layout integer DEFAULT NULL
);
CREATE UNIQUE INDEX galette_field_types_idx ON galette_field_types (field_id);
CREATE INDEX galette_field_types_form_idx ON galette_field_types (field_form);

-- Table for dynamic fields data;
DROP TABLE galette_dynamic_fields;
CREATE TABLE galette_dynamic_fields (
  item_id integer DEFAULT '0' NOT NULL,
  field_id integer DEFAULT '0' NOT NULL,
  field_form character varying(10) NOT NULL,
  val_index integer DEFAULT '0' NOT NULL,
  field_val text DEFAULT ''
);
CREATE UNIQUE INDEX galette_dynamic_fields_unique_idx ON galette_dynamic_fields (item_id, field_id, field_form, val_index);
CREATE INDEX galette_dynamic_fields_item_idx ON galette_dynamic_fields (item_id);

DROP TABLE galette_pictures;
CREATE TABLE galette_pictures (
  id_adh integer DEFAULT '0' NOT NULL,
  picture bytea NOT NULL,
  format character varying(30) DEFAULT '' NOT NULL
);
CREATE INDEX galette_pictures_idx ON galette_pictures (id_adh);

-- Table for dynamic translation of strings;
DROP TABLE galette_l10n;
CREATE TABLE galette_l10n (
  text_orig character varying(40) NOT NULL,
  text_locale character varying(15) NOT NULL,
  text_nref integer DEFAULT '1' NOT NULL,
  text_trans character varying(40) DEFAULT '' NOT NULL
);
CREATE UNIQUE INDEX galette_l10n_idx ON galette_l10n (text_orig, text_locale);

-- new table for temporary passwords  2006-02-18;
DROP TABLE galette_tmppasswds;
CREATE TABLE galette_tmppasswds (
    id_adh integer NOT NULL,
		tmp_passwd character varying(40) NOT NULL,
		date_crea_tmp_passwd timestamp NOT NULL
		);
CREATE UNIQUE INDEX galette_tmppasswds_idx ON galette_tmppasswds (id_adh);

-- Table for dynamic required fields 2007-07-10;
DROP TABLE galette_required;
CREATE TABLE galette_required (
	field_id  character varying(20) NOT NULL,
	required boolean DEFAULT false NOT NULL
);
CREATE UNIQUE INDEX galette_required_idx ON galette_required (field_id);

-- Table for automatic mails and their translations 2007-10-22;
DROP TABLE galette_texts;
CREATE TABLE galette_texts (
  tid integer DEFAULT nextval('galette_texts_id_seq'::text) NOT NULL,
  tref character varying(20) NOT NULL,
  tsubject character varying(256) NOT NULL,
  tbody text NOT NULL,
  tlang character varying(16) NOT NULL,
  tcomment character varying(64) NOT NULL
);
CREATE UNIQUE INDEX galette_texts_idx ON galette_texts (tid);

-- New table for documents models: table galette__models
DROP TABLE galette_models;
CREATE TABLE galette_models (
  mod_id integer NOT NULL,
  mod_name character varying(64) NOT NULL,
  mod_xml text NOT NULL
);
CREATE UNIQUE INDEX galette_models_idx ON galette_models (mod_id);

DROP TABLE galette_fields_categories;
CREATE TABLE galette_fields_categories (
  id_field_category integer  DEFAULT nextval('galette_fields_categories_id_seq'::text) NOT NULL,
  category character varying(50) NOT NULL,
  position integer NOT NULL,
  PRIMARY KEY (id_field_category)
);
CREATE UNIQUE INDEX galette_fields_categories_idx ON galette_fields_categories (id_field_category);

DROP TABLE galette_fields_config;
CREATE TABLE galette_fields_config (
  table_name character varying(30) NOT NULL,
  field_id character varying(30) NOT NULL,
  required character(1) NOT NULL,
  visible character(1) NOT NULL,
  position integer NOT NULL,
  id_field_category integer REFERENCES galette_fields_categories ON DELETE RESTRICT
);