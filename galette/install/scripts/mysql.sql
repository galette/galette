-- CREATE DATABASE `galette` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
-- $Id$

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS galette_adherents;
CREATE TABLE galette_adherents (
  id_adh int(10) unsigned NOT NULL auto_increment,
  id_statut int(10) unsigned NOT NULL default '4',
  nom_adh varchar(50) NOT NULL default '',
  prenom_adh varchar(50) NOT NULL default '',
  pseudo_adh varchar(20) NOT NULL default '',
  societe_adh varchar(200) default NULL,
  titre_adh int(10) unsigned default NULL,
  ddn_adh date default '1901-01-01',
  sexe_adh tinyint(1) NOT NULL default '0',
  adresse_adh varchar(150) NOT NULL default '',
  adresse2_adh varchar(150) default NULL,
  cp_adh varchar(10) NOT NULL default '',
  ville_adh varchar(50) NOT NULL default '',
  pays_adh varchar(50) default NULL,
  tel_adh varchar(20) default NULL,
  gsm_adh varchar(20) default NULL,
  email_adh varchar(150) default NULL,
  url_adh varchar(200) default NULL,
  icq_adh varchar(20) default NULL,
  msn_adh varchar(150) default NULL,
  jabber_adh varchar(150) default NULL,
  info_adh text,
  info_public_adh text,
  prof_adh varchar(150) default NULL,
  login_adh varchar(20) NOT NULL default '',
  mdp_adh varchar(60) NOT NULL default '',
  date_crea_adh date NOT NULL default '1901-01-01',
  date_modif_adh date NOT NULL default '1901-01-01',
  activite_adh tinyint(1) NOT NULL default 0,
  bool_admin_adh tinyint(1) NOT NULL default 0,
  bool_exempt_adh tinyint(1) NOT NULL default 0,
  bool_display_info tinyint(1) NOT NULL default 0,
  date_echeance date default NULL,
  pref_lang varchar(20) default 'fr_FR',
  lieu_naissance text default '',
  gpgid varchar(8) DEFAULT NULL,
  fingerprint varchar(50) DEFAULT NULL,
  PRIMARY KEY  (id_adh),
  UNIQUE (login_adh),
  FOREIGN KEY (id_statut) REFERENCES galette_statuts (id_statut),
  FOREIGN KEY (titre_adh) REFERENCES galette_titles (id_title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS galette_cotisations;
CREATE TABLE galette_cotisations (
  id_cotis int(10) unsigned NOT NULL auto_increment,
  id_adh int(10) unsigned NOT NULL default '0',
  id_type_cotis int(10) unsigned NOT NULL default '0',
  montant_cotis decimal(15, 2) unsigned default '0',
  type_paiement_cotis tinyint(3) unsigned NOT NULL default '0',
  info_cotis text,
  date_enreg date NOT NULL default '1901-01-01',
  date_debut_cotis date NOT NULL default '1901-01-01',
  date_fin_cotis date NOT NULL default '1901-01-01',
  trans_id int(10) unsigned default NULL,
  PRIMARY KEY  (id_cotis),
  FOREIGN KEY (id_type_cotis) REFERENCES galette_types_cotisation (id_type_cotis),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS galette_transactions;
CREATE TABLE galette_transactions (
  trans_id int(10) unsigned NOT NULL auto_increment,
  trans_date date NOT NULL default '1901-01-01',
  trans_amount decimal(15, 2) default '0',
  trans_desc varchar(150) NOT NULL default '',
  id_adh int(10) unsigned default NULL,
  PRIMARY KEY  (trans_id),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS galette_statuts;
CREATE TABLE galette_statuts (
  id_statut int(10) unsigned NOT NULL auto_increment,
  libelle_statut varchar(20) NOT NULL default '',
  priorite_statut tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (id_statut)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS galette_titles;
CREATE TABLE galette_titles (
  id_title int(10) unsigned NOT NULL auto_increment,
  short_label varchar(10) NOT NULL default '',
  long_label varchar(30) default '',
  PRIMARY KEY  (id_title)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS galette_types_cotisation;
CREATE TABLE galette_types_cotisation (
  id_type_cotis int(10) unsigned NOT NULL auto_increment,
  libelle_type_cotis varchar(30) NOT NULL default '',
  cotis_extension tinyint(1) NOT NULL default 0,
  PRIMARY KEY  (id_type_cotis)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS galette_preferences;
CREATE TABLE galette_preferences (
  id_pref int(10) unsigned NOT NULL auto_increment,
  nom_pref varchar(100) NOT NULL default '',
  val_pref varchar(200) NOT NULL default '',
  PRIMARY KEY (id_pref),
  UNIQUE (nom_pref)
) ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS galette_logs;
CREATE TABLE galette_logs (
  id_log int(10) unsigned NOT NULL auto_increment,
  date_log datetime NOT NULL,
  ip_log varchar(30) NOT NULL default '',
  adh_log varchar(41) NOT NULL default '',
  text_log text,
  action_log text,
  sql_log text,
  PRIMARY KEY  (id_log)
) ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Table for dynamic fields description;
DROP TABLE IF EXISTS galette_field_types;
CREATE TABLE galette_field_types (
    field_id int(10) unsigned NOT NULL auto_increment,
    field_form varchar(10) NOT NULL,
    field_index int(10) NOT NULL default '0',
    field_name varchar(40) NOT NULL default '',
    field_perm int(10) NOT NULL default '0',
    field_type int(10) NOT NULL default '0',
    field_required tinyint(1) NOT NULL default 0,
    field_pos int(10) NOT NULL default '0',
    field_width int(10) default NULL,
    field_height int(10) default NULL,
    field_size int(10) default NULL,
    field_repeat int(10) default NULL,
    field_layout int(10) default NULL,
    PRIMARY KEY (field_id),
    INDEX (field_form)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Table for dynamic fields data;
DROP TABLE IF EXISTS galette_dynamic_fields;
CREATE TABLE galette_dynamic_fields (
    item_id int(10) NOT NULL default '0',
    field_id int(10) unsigned NOT NULL default '0',
    field_form varchar(10) NOT NULL,
    val_index int(10) NOT NULL default '0',
    field_val text DEFAULT '',
    PRIMARY KEY (item_id, field_id, field_form, val_index),
    FOREIGN KEY (field_id) REFERENCES galette_field_types (field_id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS galette_pictures;
CREATE TABLE galette_pictures (
    id_adh int(10) unsigned NOT NULL default '0',
    picture mediumblob NOT NULL,
    format varchar(10) NOT NULL default '',
    PRIMARY KEY  (id_adh)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Table for dynamic translation of strings;
DROP TABLE IF EXISTS galette_l10n;
CREATE TABLE galette_l10n (
    text_orig varchar(40) NOT NULL,
    text_locale varchar(15) NOT NULL,
    text_nref int(10) NOT NULL default '1',
    text_trans varchar(100) NOT NULL default '',
    PRIMARY KEY (text_orig(30), text_locale(5))
) ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- new table for temporary passwords  2006-02-18;
DROP TABLE IF EXISTS galette_tmppasswds;
CREATE TABLE galette_tmppasswds (
    id_adh int(10) unsigned NOT NULL,
    tmp_passwd varchar(60) NOT NULL,
    date_crea_tmp_passwd datetime NOT NULL,
    PRIMARY KEY (id_adh),
    FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Add new table for automatic mails and their translations;
DROP TABLE IF EXISTS galette_texts;
CREATE TABLE galette_texts (
  tid smallint(6) NOT NULL auto_increment,
  tref varchar(20) NOT NULL,
  tsubject varchar(256) NOT NULL,
  tbody text NOT NULL,
  tlang varchar(16) NOT NULL,
  tcomment varchar(64) NOT NULL,
  PRIMARY KEY  (tid)
) ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS galette_fields_categories;
CREATE TABLE galette_fields_categories (
  id_field_category int(2) NOT NULL AUTO_INCREMENT,
  table_name varchar(30) NOT NULL,
  category varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  position int(2) NOT NULL,
  PRIMARY KEY (id_field_category)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS galette_fields_config;
CREATE TABLE galette_fields_config (
  table_name varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  field_id varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  required tinyint(1) NOT NULL,
  visible tinyint(1) NOT NULL,
  position int(2) NOT NULL,
  id_field_category int(2) NOT NULL,
  PRIMARY KEY (table_name, field_id),
  FOREIGN KEY (id_field_category) REFERENCES galette_fields_categories (id_field_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Table for mailing history storage;
DROP TABLE IF EXISTS galette_mailing_history;
CREATE TABLE galette_mailing_history (
  mailing_id smallint(6) NOT NULL auto_increment,
  mailing_sender int(10) unsigned,
  mailing_subject varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  mailing_body text NOT NULL,
  mailing_date datetime NOT NULL,
  mailing_recipients text NOT NULL,
  mailing_sent tinyint(1) NOT NULL,
  PRIMARY KEY (mailing_id),
  FOREIGN KEY (mailing_sender) REFERENCES galette_adherents (id_adh)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- table for groups
DROP TABLE IF EXISTS galette_groups;
CREATE TABLE galette_groups (
  id_group int(10) NOT NULL AUTO_INCREMENT,
  group_name varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  creation_date datetime NOT NULL,
  parent_group int(10) DEFAULT NULL,
  PRIMARY KEY (id_group),
  UNIQUE KEY `name` (group_name),
  FOREIGN KEY (parent_group) REFERENCES galette_groups (id_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- table for groups managers
DROP TABLE IF EXISTS galette_groups_managers;
CREATE TABLE galette_groups_managers (
  id_group int(10) NOT NULL,
  id_adh int(10) unsigned NOT NULL,
  PRIMARY KEY (id_group,id_adh),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh),
  FOREIGN KEY (id_group) REFERENCES galette_groups (id_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- table for groups member
DROP TABLE IF EXISTS galette_groups_members;
CREATE TABLE galette_groups_members (
  id_group int(10) NOT NULL,
  id_adh int(10) unsigned NOT NULL,
  PRIMARY KEY (id_group,id_adh),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh),
  FOREIGN KEY (id_group) REFERENCES galette_groups (id_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Table for reminders
DROP TABLE IF EXISTS galette_reminders;
CREATE TABLE galette_reminders (
  reminder_id smallint(6) NOT NULL auto_increment,
  reminder_type int(10) NOT NULL,
  reminder_dest int(10) unsigned,
  reminder_date datetime NOT NULL,
  reminder_success tinyint(1) NOT NULL DEFAULT 0,
  reminder_nomail tinyint(1) NOT NULL DEFAULT 1,
  reminder_comment text,
  PRIMARY KEY (reminder_id),
  FOREIGN KEY (reminder_dest) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Table for PDF models
DROP TABLE IF EXISTS galette_pdfmodels;
CREATE TABLE galette_pdfmodels (
  model_id int(10) unsigned NOT NULL auto_increment,
  model_name varchar(50) NOT NULL,
  model_type tinyint(2) NOT NULL,
  model_header text,
  model_footer text,
  model_body text,
  model_styles text,
  model_title varchar(100),
  model_subtitle varchar(100),
  model_parent int(10) unsigned DEFAULT NULL REFERENCES galette_pdfmodels (model_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  PRIMARY KEY (model_id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Table for import models
DROP TABLE IF EXISTS galette_import_model;
CREATE TABLE galette_import_model (
  model_id smallint(6) NOT NULL auto_increment,
  model_fields text,
  model_creation_date datetime NOT NULL,
  PRIMARY KEY (model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- table for database version
DROP TABLE IF EXISTS galette_database;
CREATE TABLE galette_database (
  version DECIMAL(4,3) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
INSERT INTO galette_database(version) VALUES(0.704);

SET FOREIGN_KEY_CHECKS=1;
