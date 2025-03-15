-- CREATE DATABASE `galette` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
-- $Id$

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS galette_adherents;
CREATE TABLE galette_adherents (
  id_adh int unsigned NOT NULL auto_increment,
  id_statut int unsigned NOT NULL,
  nom_adh varchar(255) NOT NULL default '',
  prenom_adh varchar(255) NOT NULL default '',
  pseudo_adh varchar(255) NOT NULL default '',
  societe_adh varchar(200) default NULL,
  titre_adh int unsigned default NULL,
  ddn_adh date,
  sexe_adh smallint NOT NULL,
  adresse_adh LONGTEXT NOT NULL,
  cp_adh varchar(10) NOT NULL default '',
  ville_adh varchar(200) NOT NULL default '',
  region_adh varchar(200) NOT NULL default '',
  pays_adh varchar(200) default NULL,
  tel_adh varchar(50) default NULL,
  gsm_adh varchar(50) default NULL,
  email_adh varchar(255) default NULL,
  info_adh longtext,
  info_public_adh longtext,
  prof_adh varchar(150) default NULL,
  login_adh varchar(255) NOT NULL default '',
  mdp_adh varchar(255) NOT NULL default '',
  date_crea_adh date NOT NULL,
  date_modif_adh date NOT NULL,
  activite_adh tinyint(1) NOT NULL default 0,
  bool_admin_adh tinyint(1) NOT NULL default 0,
  bool_exempt_adh tinyint(1) NOT NULL default 0,
  bool_display_info tinyint(1) NOT NULL default 0,
  date_echeance date default NULL,
  pref_lang varchar(20),
  lieu_naissance varchar(255),
  gpgid longtext DEFAULT NULL,
  fingerprint varchar(255) DEFAULT NULL,
  parent_id int unsigned DEFAULT NULL,
  num_adh varchar(255) DEFAULT NULL,
  PRIMARY KEY (id_adh),
  UNIQUE (login_adh),
  FOREIGN KEY (id_statut) REFERENCES galette_statuts (id_statut) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (titre_adh) REFERENCES galette_titles (id_title) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_cotisations;
CREATE TABLE galette_cotisations (
  id_cotis int unsigned NOT NULL auto_increment,
  id_adh int unsigned NOT NULL,
  id_type_cotis int unsigned NOT NULL,
  montant_cotis decimal(15, 2) NOT NULL,
  type_paiement_cotis int unsigned NOT NULL,
  info_cotis longtext,
  date_enreg date NOT NULL,
  date_debut_cotis date NOT NULL,
  date_fin_cotis date,
  trans_id int unsigned default NULL,
  PRIMARY KEY (id_cotis),
  FOREIGN KEY (id_type_cotis) REFERENCES galette_types_cotisation (id_type_cotis) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (trans_id) REFERENCES galette_transactions (trans_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (type_paiement_cotis) REFERENCES galette_paymenttypes (type_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_transactions;
CREATE TABLE galette_transactions (
  trans_id int unsigned NOT NULL auto_increment,
  trans_date date NOT NULL,
  trans_amount decimal(15, 2) NOT NULL,
  trans_desc varchar(255) NOT NULL default '',
  id_adh int unsigned default NULL,
  type_paiement_trans int unsigned DEFAULT NULL,
  PRIMARY KEY (trans_id),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (type_paiement_trans) REFERENCES galette_paymenttypes (type_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_statuts;
CREATE TABLE galette_statuts (
  id_statut int unsigned NOT NULL auto_increment,
  libelle_statut varchar(255) NOT NULL default '',
  priorite_statut smallint NOT NULL default 0,
  PRIMARY KEY (id_statut)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_titles;
CREATE TABLE galette_titles (
  id_title int unsigned NOT NULL auto_increment,
  short_label varchar(10) NOT NULL default '',
  long_label varchar(100) default '',
  PRIMARY KEY (id_title)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_types_cotisation;
CREATE TABLE galette_types_cotisation (
  id_type_cotis int unsigned NOT NULL auto_increment,
  libelle_type_cotis varchar(255) NOT NULL default '',
  amount decimal(15,2) DEFAULT NULL,
  cotis_extension smallint NOT NULL default 0,
  PRIMARY KEY (id_type_cotis)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_preferences;
CREATE TABLE galette_preferences (
  id_pref int unsigned NOT NULL auto_increment,
  nom_pref varchar(100) NOT NULL default '',
  val_pref varchar(255) NOT NULL default '',
  PRIMARY KEY (id_pref),
  UNIQUE (nom_pref)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_logs;
CREATE TABLE galette_logs (
  id_log int unsigned NOT NULL auto_increment,
  date_log datetime NOT NULL,
  ip_log varchar(46) NOT NULL default '',
  adh_log varchar(255) NOT NULL default '', -- see galette_adherents.login_adh
  text_log longtext,
  action_log longtext,
  sql_log longtext,
  PRIMARY KEY (id_log)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- Table for dynamic fields description;
DROP TABLE IF EXISTS galette_field_types;
CREATE TABLE galette_field_types (
    field_id int unsigned NOT NULL auto_increment,
    field_form varchar(10) NOT NULL,
    field_index int NOT NULL default 0,
    field_name varchar(255) NOT NULL default '',
    field_perm int NOT NULL default 1,
    field_type int NOT NULL default 0,
    field_required tinyint(1) NOT NULL default 0,
    field_pos int NOT NULL default 0,
    field_width int default NULL,
    field_height int default NULL,
    field_min_size int default NULL,
    field_size int default NULL,
    field_repeat int default NULL,
    field_information longtext default NULL,
    field_width_in_forms smallint NOT NULL default 1,
    field_information_above tinyint(1) NOT NULL default 0,
    PRIMARY KEY (field_id),
    INDEX (field_form)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- Table for dynamic fields data;
DROP TABLE IF EXISTS galette_dynamic_fields;
CREATE TABLE galette_dynamic_fields (
    item_id int NOT NULL default 0,
    field_id int unsigned NOT NULL default 0,
    field_form varchar(10) NOT NULL,
    val_index int NOT NULL default 0,
    field_val longtext,
    PRIMARY KEY (item_id, field_id, field_form, val_index),
    FOREIGN KEY (field_id) REFERENCES galette_field_types (field_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_pictures;
CREATE TABLE galette_pictures (
    id_adh int unsigned NOT NULL default 0,
    picture mediumblob NOT NULL,
    format varchar(30) NOT NULL default '',
    PRIMARY KEY (id_adh)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- Table for dynamic translation of strings;
DROP TABLE IF EXISTS galette_l10n;
CREATE TABLE galette_l10n (
    text_orig varchar(255) NOT NULL,
    text_orig_sum varchar(40) NOT NULL,
    text_locale varchar(15) NOT NULL,
    text_nref int NOT NULL default 1,
    text_trans varchar(255) NOT NULL default '',
    PRIMARY KEY (text_orig_sum, text_locale)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- new table for temporary passwords 2006-02-18;
DROP TABLE IF EXISTS galette_tmppasswds;
CREATE TABLE galette_tmppasswds (
    id_adh int unsigned NOT NULL,
    tmp_passwd varchar(250) NOT NULL,
    date_crea_tmp_passwd datetime NOT NULL,
    PRIMARY KEY (id_adh),
    FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- Add new table for automatic mails and their translations;
DROP TABLE IF EXISTS galette_texts;
CREATE TABLE galette_texts (
  tid int unsigned NOT NULL auto_increment,
  tref varchar(20) NOT NULL,
  tsubject varchar(256) NOT NULL,
  tbody longtext NOT NULL,
  tlang varchar(16) NOT NULL,
  tcomment varchar(255) NOT NULL,
  PRIMARY KEY (tid),
  UNIQUE KEY `localizedtxt` (tref, tlang)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_fields_categories;
CREATE TABLE galette_fields_categories (
  id_field_category int unsigned NOT NULL AUTO_INCREMENT,
  table_name varchar(30) NOT NULL,
  category varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  position smallint NOT NULL,
  PRIMARY KEY (id_field_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_fields_config;
CREATE TABLE galette_fields_config (
  table_name varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  field_id varchar(30) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  required tinyint(1) NOT NULL,
  visible tinyint(1) NOT NULL,
  position smallint NOT NULL,
  id_field_category int unsigned NOT NULL,
  list_visible tinyint(1) NOT NULL,
  list_position smallint NOT NULL,
  width_in_forms smallint NOT NULL DEFAULT 1,
  PRIMARY KEY (table_name, field_id),
  FOREIGN KEY (id_field_category) REFERENCES galette_fields_categories (id_field_category) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Table for mailing history storage;
DROP TABLE IF EXISTS galette_mailing_history;
CREATE TABLE galette_mailing_history (
  mailing_id int unsigned NOT NULL auto_increment,
  mailing_sender int unsigned,
  mailing_subject varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  mailing_body longtext NOT NULL,
  mailing_date datetime NOT NULL,
  mailing_recipients longtext NOT NULL,
  mailing_sent tinyint(1) NOT NULL,
  mailing_sender_name varchar(255) DEFAULT NULL,
  mailing_sender_address varchar(255) DEFAULT NULL,
  PRIMARY KEY (mailing_id),
  FOREIGN KEY (mailing_sender) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- table for groups
DROP TABLE IF EXISTS galette_groups;
CREATE TABLE galette_groups (
  id_group int unsigned NOT NULL AUTO_INCREMENT,
  group_name varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  creation_date datetime NOT NULL,
  parent_group int unsigned DEFAULT NULL,
  PRIMARY KEY (id_group),
  FOREIGN KEY (parent_group) REFERENCES galette_groups (id_group) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- table for groups managers
DROP TABLE IF EXISTS galette_groups_managers;
CREATE TABLE galette_groups_managers (
  id_group int unsigned NOT NULL,
  id_adh int unsigned NOT NULL,
  PRIMARY KEY (id_group,id_adh),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (id_group) REFERENCES galette_groups (id_group) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- table for groups member
DROP TABLE IF EXISTS galette_groups_members;
CREATE TABLE galette_groups_members (
  id_group int unsigned NOT NULL,
  id_adh int unsigned NOT NULL,
  PRIMARY KEY (id_group,id_adh),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (id_group) REFERENCES galette_groups (id_group) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Table for reminders
DROP TABLE IF EXISTS galette_reminders;
CREATE TABLE galette_reminders (
  reminder_id int unsigned NOT NULL auto_increment,
  reminder_type int NOT NULL,
  reminder_dest int unsigned,
  reminder_date datetime NOT NULL,
  reminder_success tinyint(1) NOT NULL DEFAULT 0,
  reminder_nomail tinyint(1) NOT NULL DEFAULT 1,
  reminder_comment longtext,
  PRIMARY KEY (reminder_id),
  FOREIGN KEY (reminder_dest) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Table for PDF models
DROP TABLE IF EXISTS galette_pdfmodels;
CREATE TABLE galette_pdfmodels (
  model_id int unsigned NOT NULL auto_increment,
  model_name varchar(50) NOT NULL,
  model_type smallint NOT NULL,
  model_header longtext,
  model_footer longtext,
  model_body longtext,
  model_styles longtext,
  model_title varchar(250),
  model_subtitle varchar(250),
  model_parent int unsigned DEFAULT NULL REFERENCES galette_pdfmodels (model_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  PRIMARY KEY (model_id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- Table for import models
DROP TABLE IF EXISTS galette_import_model;
CREATE TABLE galette_import_model (
  model_id int unsigned NOT NULL auto_increment,
  model_fields longtext,
  model_creation_date datetime NOT NULL,
  PRIMARY KEY (model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Table for payment types
DROP TABLE IF EXISTS galette_paymenttypes;
CREATE TABLE galette_paymenttypes (
  type_id int unsigned NOT NULL auto_increment,
  type_name varchar(255) NOT NULL,
  PRIMARY KEY (type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- table for saved searches
DROP TABLE IF EXISTS galette_searches;
CREATE TABLE galette_searches (
  search_id int unsigned NOT NULL auto_increment,
  name varchar(100) DEFAULT NULL,
  form varchar(50) NOT NULL,
  parameters longtext NOT NULL,
  id_adh int unsigned,
  creation_date datetime NOT NULL,
  PRIMARY KEY (search_id),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- new table for temporary links
DROP TABLE IF EXISTS galette_tmplinks;
CREATE TABLE galette_tmplinks (
  hash varchar(250) NOT NULL,
  target smallint NOT NULL,
  id int unsigned,
  creation_date datetime NOT NULL,
  PRIMARY KEY (target, id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- table for social networks
DROP TABLE IF EXISTS galette_socials;
CREATE TABLE galette_socials (
  id_social int unsigned NOT NULL auto_increment,
  id_adh int unsigned DEFAULT NULL,
  type varchar(250) NOT NULL,
  url varchar(255) DEFAULT NULL,
  PRIMARY KEY (id_social),
  KEY (type),
  FOREIGN KEY (id_adh) REFERENCES  galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- table for documents
DROP TABLE IF EXISTS galette_documents;
CREATE TABLE galette_documents (
  id_document int unsigned NOT NULL auto_increment,
  type varchar(250) NOT NULL,
  visible smallint NOT NULL,
  filename varchar(255) DEFAULT NULL,
  comment longtext,
  creation_date datetime NOT NULL,
  PRIMARY KEY (id_document),
  KEY (type)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- table for payments schedules
DROP TABLE IF EXISTS galette_payments_schedules;
CREATE TABLE galette_payments_schedules (
  id_schedule int unsigned NOT NULL auto_increment,
  id_cotis int unsigned NOT NULL,
  id_paymenttype int unsigned NOT NULL,
  creation_date datetime NOT NULL,
  scheduled_date datetime NOT NULL,
  amount decimal(15, 2) NOT NULL,
  paid tinyint(1) DEFAULT FALSE,
  comment longtext,
  PRIMARY KEY (id_schedule),
  FOREIGN KEY (id_cotis) REFERENCES galette_cotisations (id_cotis) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_paymenttype) REFERENCES galette_paymenttypes (type_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

-- table for database version
DROP TABLE IF EXISTS galette_database;
CREATE TABLE galette_database (
  version DECIMAL(4,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
INSERT INTO galette_database(version) VALUES(1.20);

SET FOREIGN_KEY_CHECKS=1;
