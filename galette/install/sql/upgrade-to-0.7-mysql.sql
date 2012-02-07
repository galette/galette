-- Do not check foreign keys: will cause to fail on drop/create existent tables
SET FOREIGN_KEY_CHECKS=0;

-- Set some tables type to InnoDB so we can use foreign keys
ALTER TABLE galette_adherents ENGINE = InnoDB;
ALTER TABLE galette_types_cotisation ENGINE = InnoDB;
ALTER TABLE galette_cotisations ENGINE = InnoDB;
ALTER TABLE galette_transactions ENGINE = InnoDB;
ALTER TABLE galette_statuts ENGINE = InnoDB;
ALTER TABLE galette_pictures ENGINE = InnoDB;
ALTER TABLE galette_tmppasswds ENGINE = InnoDB;
ALTER TABLE galette_dynamic_fields ENGINE = InnoDB;
ALTER TABLE galette_field_types ENGINE = InnoDB;

-- Each preference must be unique
ALTER TABLE galette_preferences ADD UNIQUE (nom_pref);

-- Update languages
UPDATE galette_preferences SET val_pref='fr_FR' WHERE nom_pref='pref_lang' AND val_pref='french';
UPDATE galette_preferences SET val_pref='en_EN' WHERE nom_pref='pref_lang' AND val_pref='english';
-- spanish no longer exists, fallback to english
UPDATE galette_preferences SET val_pref='en_EN' WHERE nom_pref='pref_lang' AND val_pref='spanish';
UPDATE galette_adherents SET pref_lang='fr_FR' WHERE pref_lang='french';
UPDATE galette_adherents SET pref_lang='en_EN' WHERE pref_lang='english';
-- spanish no longer exists, fallback to english
UPDATE galette_adherents SET pref_lang='es_EN' WHERE pref_lang='spanish';
ALTER TABLE `galette_adherents` CHANGE `pref_lang` `pref_lang` VARCHAR( 20 ) NULL DEFAULT 'fr_FR';
UPDATE galette_preferences SET nom_pref='pref_mail_smtp_host' WHERE nom_pref='pref_mail_smtp';

-- Table for dynamic required fields;
DROP TABLE IF EXISTS galette_required;
CREATE TABLE IF NOT EXISTS galette_required (
    field_id varchar(15) NOT NULL,
    required tinyint(1) NOT NULL,
    PRIMARY KEY  (field_id)
) ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Add new table for automatic mails and their translations;
DROP TABLE IF EXISTS galette_texts;
CREATE TABLE IF NOT EXISTS galette_texts (
  tid smallint(6) NOT NULL auto_increment,
  tref varchar(20) NOT NULL,
  tsubject varchar(256) NOT NULL,
  tbody text NOT NULL,
  tlang varchar(16) NOT NULL,
  tcomment varchar(64) NOT NULL,
  PRIMARY KEY  (tid)
) ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Modify table picture to allow for negative indexes;
ALTER TABLE galette_pictures CHANGE id_adh id_adh INT( 10 ) UNSIGNED NOT NULL DEFAULT '0';

-- Add missing primary keys
ALTER TABLE `galette_dynamic_fields` ADD PRIMARY KEY ( `item_id` , `field_id` , `field_form` , `val_index` );
ALTER TABLE `galette_l10n` DROP INDEX `text_orig`, ADD PRIMARY KEY (`text_orig` (30), `text_locale` (5));

-- Changes "boolean" fields to tinyint ; enum is not enough standard
ALTER TABLE `galette_adherents` CHANGE `activite_adh` `activite_adh` VARCHAR( 1 ) NOT NULL DEFAULT 0;
ALTER TABLE `galette_adherents` CHANGE `activite_adh` `activite_adh` TINYINT( 1 ) NOT NULL DEFAULT 0;
ALTER TABLE `galette_adherents` CHANGE `bool_admin_adh` `bool_admin_adh` TINYINT( 1 ) NOT NULL DEFAULT 0;
ALTER TABLE `galette_adherents` CHANGE `bool_exempt_adh` `bool_exempt_adh` TINYINT( 1 ) NOT NULL DEFAULT 0;
ALTER TABLE `galette_adherents` CHANGE `bool_display_info` `bool_display_info` TINYINT( 1 ) NOT NULL DEFAULT 0;
ALTER TABLE `galette_types_cotisation` CHANGE `cotis_extension` `cotis_extension` TINYINT( 1 ) NOT NULL DEFAULT 0;
ALTER TABLE `galette_field_types` CHANGE `field_required` `field_required` TINYINT( 1 ) NOT NULL DEFAULT 0;

-- New table for fields categories
DROP TABLE IF EXISTS galette_fields_categories;
CREATE TABLE IF NOT EXISTS galette_fields_categories (
  id_field_category int(2) NOT NULL AUTO_INCREMENT,
  table_name varchar(30) NOT NULL,
  category varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  position int(2) NOT NULL,
  PRIMARY KEY (id_field_category)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- New table for fields configuration
DROP TABLE IF EXISTS galette_fields_config;
CREATE TABLE IF NOT EXISTS galette_fields_config (
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
CREATE TABLE IF NOT EXISTS galette_mailing_history (
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
CREATE TABLE IF NOT EXISTS galette_groups (
  id_group int(10) NOT NULL AUTO_INCREMENT,
  group_name varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  creation_date datetime NOT NULL,
  parent_group int(10) DEFAULT NULL,
  PRIMARY KEY (id_group),
  UNIQUE KEY `name` (group_name),
  FOREIGN KEY (parent_group) REFERENCES galette_groups (id_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- table for groups managers
CREATE TABLE IF NOT EXISTS galette_groups_managers (
  id_group int(10) NOT NULL,
  id_adh int(10) unsigned NOT NULL,
  PRIMARY KEY (id_group,id_adh),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh),
  FOREIGN KEY (id_group) REFERENCES galette_groups (id_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- table for groups members
CREATE TABLE IF NOT EXISTS galette_groups_members (
  id_group int(10) NOT NULL,
  id_adh int(10) unsigned NOT NULL,
  PRIMARY KEY (id_group,id_adh),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh),
  FOREIGN KEY (id_group) REFERENCES galette_groups (id_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- table for database version
CREATE TABLE IF NOT EXISTS galette_database (
  version DECIMAL(4,3) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
INSERT INTO galette_database(version) VALUES(0.700);

ALTER TABLE galette_adherents ADD societe_adh VARCHAR( 20 ) NULL AFTER prenom_adh;

ALTER TABLE galette_cotisations ADD FOREIGN KEY (id_type_cotis)
  REFERENCES galette_types_cotisation (id_type_cotis)
  ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE galette_cotisations ADD FOREIGN KEY (id_adh)
  REFERENCES galette_adherents (id_adh)
  ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE galette_transactions ADD FOREIGN KEY (id_adh)
  REFERENCES galette_adherents (id_adh)
  ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE galette_adherents ADD FOREIGN KEY (id_statut)
  REFERENCES galette_statuts (id_statut)
  ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE galette_tmppasswds CHANGE id_adh id_adh INT( 10 ) UNSIGNED NOT NULL;
ALTER TABLE galette_tmppasswds ADD FOREIGN KEY (id_adh)
  REFERENCES galette_adherents (id_adh)
  ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE galette_dynamic_fields CHANGE field_id field_id INT( 10 ) UNSIGNED NOT NULL DEFAULT '0';
ALTER TABLE galette_dynamic_fields ADD FOREIGN KEY (field_id)
  REFERENCES galette_field_types (field_id)
  ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE galette_cotisations ADD type_paiement_cotis TINYINT( 3 ) unsigned NOT NULL DEFAULT '0' AFTER montant_cotis;

ALTER TABLE galette_adherents ADD date_modif_adh date DEFAULT '1901-01-01' NOT NULL AFTER date_crea_adh;

-- Fix round issues
ALTER TABLE galette_cotisations CHANGE montant_cotis montant_cotis DECIMAL( 15, 2 ) UNSIGNED NULL DEFAULT '0';
ALTER TABLE galette_transactions CHANGE trans_amount trans_amount DECIMAL( 15, 2 ) NULL DEFAULT '0' ;

-- Put back foreign keys
SET FOREIGN_KEY_CHECKS=1;
