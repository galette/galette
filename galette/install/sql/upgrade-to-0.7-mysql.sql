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

-- Add new or missing preferences;
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_slogan', '');
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
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_abrev', 'Galette');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_strip','Gestion d Adherents en Ligne Extrêmement Tarabiscoté');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_tcol', 'FFFFFF');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_scol', '8C2453');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_bcol', '53248C');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_hcol', '248C53');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_bool_display_title', '');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_address', '1');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_year', '2007');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_marges_v', '15');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_marges_h', '20');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_vspace', '5');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_hspace', '10');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_card_self', '1');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_editor_enabled', '');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_theme', 'default');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp_auth', 'false');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp_secure', 'false');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp_port', '25');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp_user', '');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp_password', '');

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

-- Base fields categories
INSERT INTO `galette_fields_categories` (`id_field_category`, `category`, `position`) VALUES (1, 'Identity:', 1);
INSERT INTO `galette_fields_categories` (`id_field_category`, `category`, `position`) VALUES (2, 'Galette-related data:', 2);
INSERT INTO `galette_fields_categories` (`id_field_category`, `category`, `position`) VALUES (3, 'Contact information:', 3);

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
  CONSTRAINT galette_fields_config_categories
    FOREIGN KEY (id_field_category)
    REFERENCES galette_fields_categories (id_field_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Table for mailing history storage;
DROP TABLE IF EXISTS galette_mailing_history;
CREATE TABLE IF NOT EXISTS galette_mailing_history (
  mailing_id smallint(6) NOT NULL auto_increment,
  mailing_sender int(10) unsigned NOT NULL,
  mailing_subject varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  mailing_body text NOT NULL,
  mailing_date datetime NOT NULL,
  mailing_recipients text NOT NULL,
  mailing_sent tinyint(1) NOT NULL,
  PRIMARY KEY (mailing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- table for groups
CREATE TABLE IF NOT EXISTS galette_groups (
  id_group int(10) NOT NULL AUTO_INCREMENT,
  group_name varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  creation_date datetime NOT NULL,
  id_adh int(10) unsigned NOT NULL,
  PRIMARY KEY (id_group),
  UNIQUE KEY `name` (group_name),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- table for groups users
CREATE TABLE IF NOT EXISTS galette_groups_users (
  id_group int(10) NOT NULL,
  id_adh int(10) unsigned NOT NULL,
  manager tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (id_group,id_adh),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh),
  FOREIGN KEY (id_group) REFERENCES galette_groups (id_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

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