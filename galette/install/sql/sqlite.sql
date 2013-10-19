-- CREATE DATABASE `galette` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
-- $Id$

DROP TABLE IF EXISTS galette_adherents;
CREATE TABLE galette_adherents (
  id_adh INTEGER NOT NULL PRIMARY KEY, -- auto increment
  id_statut INTEGER NOT NULL default '4',
  nom_adh TEXT NOT NULL default '',
  prenom_adh TEXT NOT NULL default '',
  pseudo_adh TEXT NOT NULL default '',
  societe_adh TEXT default NULL,
  titre_adh INTEGER default NULL,
  ddn_adh TEXT default '1901-01-01',
  sexe_adh INTEGER NOT NULL default '0',
  adresse_adh TEXT NOT NULL default '',
  adresse2_adh TEXT default NULL,
  cp_adh TEXT NOT NULL default '',
  ville_adh TEXT NOT NULL default '',
  pays_adh TEXT default NULL,
  tel_adh TEXT default NULL,
  gsm_adh TEXT default NULL,
  email_adh TEXT default NULL,
  url_adh TEXT default NULL,
  icq_adh TEXT default NULL,
  msn_adh TEXT default NULL,
  jabber_adh TEXT default NULL,
  info_adh text,
  info_public_adh text,
  prof_adh TEXT default NULL,
  login_adh TEXT NOT NULL default '',
  mdp_adh TEXT NOT NULL default '',
  date_crea_adh TEXT NOT NULL default '1901-01-01',
  date_modif_adh TEXT NOT NULL default '1901-01-01',
  activite_adh INTEGER NOT NULL default 0,
  bool_admin_adh INTEGER NOT NULL default 0,
  bool_exempt_adh INTEGER NOT NULL default 0,
  bool_display_info INTEGER NOT NULL default 0,
  date_echeance TEXT default NULL,
  pref_lang TEXT default 'fr_FR',
  lieu_naissance text default '',
  gpgid TEXT DEFAULT NULL,
  fingerprint TEXT DEFAULT NULL,
  UNIQUE (login_adh),
  FOREIGN KEY (id_statut) REFERENCES galette_statuts (id_statut),
  FOREIGN KEY (titre_adh) REFERENCES galette_titles (id_title)
);

DROP TABLE IF EXISTS galette_cotisations;
CREATE TABLE galette_cotisations (
  id_cotis INTEGER NOT NULL PRIMARY KEY,
  id_adh INTEGER NOT NULL default '0',
  id_type_cotis INTEGER NOT NULL default '0',
  montant_cotis REAL unsigned default '0',
  type_paiement_cotis INTEGER unsigned NOT NULL default '0',
  info_cotis text,
  date_enreg TEXT NOT NULL default '1901-01-01',
  date_debut_cotis TEXT NOT NULL default '1901-01-01',
  date_fin_cotis TEXT NOT NULL default '1901-01-01',
  trans_id INTEGER default NULL,
  FOREIGN KEY (id_type_cotis) REFERENCES galette_types_cotisation (id_type_cotis),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh)
);

DROP TABLE IF EXISTS galette_transactions;
CREATE TABLE galette_transactions (
  trans_id INTEGER NOT NULL PRIMARY KEY,
  trans_date TEXT NOT NULL default '1901-01-01',
  trans_amount REAL default '0',
  trans_desc TEXT NOT NULL default '',
  id_adh INTEGER default NULL,
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh)
);

DROP TABLE IF EXISTS galette_statuts;
CREATE TABLE galette_statuts (
  id_statut INTEGER NOT NULL PRIMARY KEY,
  libelle_statut TEXT NOT NULL default '',
  priorite_statut INTEGER NOT NULL default '0'
);

DROP TABLE IF EXISTS galette_titles;
CREATE TABLE galette_titles (
  id_title INTEGER NOT NULL PRIMARY KEY,
  short_label TEXT NOT NULL default '',
  long_label TEXT NULL default ''
);

DROP TABLE IF EXISTS galette_types_cotisation;
CREATE TABLE galette_types_cotisation (
  id_type_cotis INTEGER NOT NULL PRIMARY KEY,
  libelle_type_cotis TEXT NOT NULL default '',
  cotis_extension INTEGER NOT NULL default 0
);

DROP TABLE IF EXISTS galette_preferences;
CREATE TABLE galette_preferences (
  id_pref INTEGER NOT NULL PRIMARY KEY,
  nom_pref TEXT NOT NULL default '',
  val_pref TEXT NOT NULL default '',
  UNIQUE (nom_pref)
);

DROP TABLE IF EXISTS galette_logs;
CREATE TABLE galette_logs (
  id_log INTEGER NOT NULL PRIMARY KEY,
  date_log TEXT NOT NULL,
  ip_log TEXT NOT NULL default '',
  adh_log TEXT NOT NULL default '',
  text_log text,
  action_log text,
  sql_log text
);

-- Table for dynamic fields description;
DROP TABLE IF EXISTS galette_field_types;
CREATE TABLE galette_field_types (
    field_id INTEGER NOT NULL PRIMARY KEY,
    field_form TEXT NOT NULL,
    field_index INTEGER NOT NULL default '0',
    field_name TEXT NOT NULL default '',
    field_perm INTEGER NOT NULL default '0',
    field_type INTEGER NOT NULL default '0',
    field_required INTEGER NOT NULL default 0,
    field_pos INTEGER NOT NULL default '0',
    field_width INTEGER default NULL,
    field_height INTEGER default NULL,
    field_size INTEGER default NULL,
    field_repeat INTEGER default NULL,
    field_layout INTEGER default NULL
);

CREATE INDEX galette_field_types_field_form ON galette_field_types (field_form);

-- Table for dynamic fields data;
DROP TABLE IF EXISTS galette_dynamic_fields;
CREATE TABLE galette_dynamic_fields (
    item_id INTEGER NOT NULL default '0',
    field_id INTEGER NOT NULL default '0',
    field_form TEXT NOT NULL,
    val_index INTEGER NOT NULL default '0',
    field_val text DEFAULT '',
    PRIMARY KEY (item_id, field_id, field_form, val_index),
    FOREIGN KEY (field_id) REFERENCES galette_field_types (field_id)
);

DROP TABLE IF EXISTS galette_pictures;
CREATE TABLE galette_pictures (
    id_adh INTEGER NOT NULL default '0',
    picture BLOB NOT NULL,
    format TEXT NOT NULL default '',
    PRIMARY KEY  (id_adh)
);

-- Table for dynamic translation of strings;
DROP TABLE IF EXISTS galette_l10n;
CREATE TABLE galette_l10n (
    text_orig TEXT NOT NULL,
    text_locale TEXT NOT NULL,
    text_nref INTEGER NOT NULL default '1',
    text_trans TEXT NOT NULL default '',
    PRIMARY KEY (text_orig, text_locale)
);

-- new table for temporary passwords  2006-02-18;
DROP TABLE IF EXISTS galette_tmppasswds;
CREATE TABLE galette_tmppasswds (
    id_adh INTEGER NOT NULL,
    tmp_passwd TEXT NOT NULL,
    date_crea_tmp_passwd TEXT NOT NULL,
    PRIMARY KEY (id_adh),
    FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE
);

-- Add new table for automatic mails and their translations;
DROP TABLE IF EXISTS galette_texts;
CREATE TABLE galette_texts (
  tid INTEGER NOT NULL PRIMARY KEY,
  tref TEXT NOT NULL,
  tsubject TEXT NOT NULL,
  tbody text NOT NULL,
  tlang TEXT NOT NULL,
  tcomment TEXT NOT NULL
);

DROP TABLE IF EXISTS galette_fields_categories;
CREATE TABLE galette_fields_categories (
  id_field_category INTEGER NOT NULL PRIMARY KEY,
  table_name TEXT NOT NULL,
  category TEXT NOT NULL,
  position INTEGER NOT NULL
);


DROP TABLE IF EXISTS galette_fields_config;
CREATE TABLE galette_fields_config (
  table_name TEXT NOT NULL,
  field_id TEXT NOT NULL,
  required INTEGER NOT NULL,
  visible INTEGER NOT NULL,
  position INTEGER NOT NULL,
  id_field_category INTEGER NOT NULL,
  PRIMARY KEY (table_name, field_id),
  FOREIGN KEY (id_field_category) REFERENCES galette_fields_categories (id_field_category)
);

-- Table for mailing history storage;
DROP TABLE IF EXISTS galette_mailing_history;
CREATE TABLE galette_mailing_history (
  mailing_id INTEGER NOT NULL PRIMARY KEY,
  mailing_sender INTEGER,
  mailing_subject TEXT NOT NULL,
  mailing_body text NOT NULL,
  mailing_date TEXT NOT NULL,
  mailing_recipients text NOT NULL,
  mailing_sent INTEGER NOT NULL,
  FOREIGN KEY (mailing_sender) REFERENCES galette_adherents (id_adh)
);

-- table for groups
DROP TABLE IF EXISTS galette_groups;
CREATE TABLE galette_groups (
  id_group INTEGER NOT NULL PRIMARY KEY,
  group_name TEXT NOT NULL,
  creation_date TEXT NOT NULL,
  parent_group INTEGER DEFAULT NULL,
  FOREIGN KEY (parent_group) REFERENCES galette_groups (id_group)
);

CREATE UNIQUE INDEX galette_groups_name ON galette_groups (group_name);

-- table for groups managers
DROP TABLE IF EXISTS galette_groups_managers;
CREATE TABLE galette_groups_managers (
  id_group INTEGER NOT NULL,
  id_adh INTEGER NOT NULL,
  PRIMARY KEY (id_group,id_adh),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh),
  FOREIGN KEY (id_group) REFERENCES galette_groups (id_group)
);

-- table for groups member
DROP TABLE IF EXISTS galette_groups_members;
CREATE TABLE galette_groups_members (
  id_group INTEGER NOT NULL,
  id_adh INTEGER NOT NULL,
  PRIMARY KEY (id_group,id_adh),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh),
  FOREIGN KEY (id_group) REFERENCES galette_groups (id_group)
);

-- Table for reminders;
DROP TABLE IF EXISTS galette_reminders;
CREATE TABLE galette_reminders (
  reminder_id INTEGER NOT NULL PRIMARY KEY,
  reminder_type INTEGER NOT NULL,
  reminder_dest INTEGER,
  reminder_date TEXT NOT NULL,
  reminder_success INTEGER NOT NULL default 0,
  reminder_nomail INTEGER NOT NULL default 1,
  reminder_comment TEXT,
  FOREIGN KEY (reminder_dest) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE
);

-- Table for PDF models
DROP TABLE IF EXISTS galette_pdfmodels;
CREATE TABLE galette_pdfmodels (
  model_id INTEGER NOT NULL PRIMARY KEY,
  model_name TEXT NOT NULL,
  model_type INTEGER NOT NULL,
  model_header TEXT,
  model_footer TEXT,
  model_body TEXT,
  model_styles TEXT,
  model_title TEXT,
  model_subtitle TEXT,
  model_parent INTEGER DEFAULT NULL,
  FOREIGN KEY (model_parent) REFERENCES galette_pdfmodels (model_id)
);

-- Table for import models
DROP TABLE IF EXISTS galette_import_model;
CREATE TABLE galette_import_model (
  model_id INTEGER NOT NULL PRIMARY KEY,
  model_fields TEXT,
  model_creation_date TEXT NOT NULL
);

-- table for database version
DROP TABLE IF EXISTS galette_database;
CREATE TABLE galette_database (
  version REAL NOT NULL
);
INSERT INTO galette_database(version) VALUES(0.704);

PRAGMA foreign_keys = ON;
