ALTER TABLE galette_adherents ADD pref_lang varchar(20) default 'french' AFTER date_echeance;
INSERT INTO galette_types_cotisation VALUES (7, 'Cotisation annuelle (à payer)');
ALTER TABLE galette_adherents ADD  UNIQUE (login_adh);

-- Add new or missing preferences;
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_pays', '');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_method', '0');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp', '0');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_membership_ext', '12');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_beg_membership', '');

-- New tables for dynamic fields;
DROP TABLE galette_field_types;
CREATE TABLE galette_field_types (
    field_id int(10) unsigned NOT NULL auto_increment,
    field_form varchar(10) NOT NULL,
    field_index int(10) NOT NULL default '0',
    field_name varchar(40) NOT NULL default '',
    field_perm int(10) NOT NULL default '0',
    field_type int(10) NOT NULL default '0',
    field_required enum('1') default NULL,
    field_pos int(10) NOT NULL default '0',
    field_width int(10) default NULL,
    field_height int(10) default NULL,
    field_size int(10) default NULL,
    field_repeat int(10) default NULL,
    field_layout int(10) default NULL,
    PRIMARY KEY (field_id),
    INDEX (field_form)
) TYPE=MyISAM;

DROP TABLE galette_dynamic_fields;
CREATE TABLE galette_dynamic_fields (
    item_id int(10) NOT NULL default '0',
    field_id int(10) NOT NULL default '0',
    field_form varchar(10) NOT NULL,
    val_index int(10) NOT NULL default '0',
    field_val text DEFAULT '',
    KEY  (item_id)
) TYPE=MyISAM;

-- Table for member photographs;
DROP TABLE IF EXISTS galette_pictures;
CREATE TABLE `galette_pictures` (
    `id_adh` int(10) unsigned NOT NULL default '0',
    `picture` blob NOT NULL,
    `format` varchar(10) NOT NULL default '',
    `width` int(10) unsigned NOT NULL default '0',
    `height` int(10) unsigned NOT NULL default '0',
    PRIMARY KEY  (`id_adh`)
) TYPE=MyISAM;

-- Add two fileds for log;
ALTER TABLE galette_logs ADD action_log text;
ALTER TABLE galette_logs ADD sql_log text;

-- Change table cotisations to store date_fin_cotis instead of duration;
ALTER TABLE galette_cotisations ADD date_enreg date NOT NULL default '0000-00-00';
ALTER TABLE galette_cotisations ADD date_debut_cotis date NOT NULL default '0000-00-00';
ALTER TABLE galette_cotisations ADD date_fin_cotis date NOT NULL default '0000-00-00';
UPDATE galette_cotisations
	SET date_enreg=date_cotis,
	    date_debut_cotis=date_cotis,
	    date_fin_cotis=DATE_ADD(date_cotis, INTERVAL duree_mois_cotis MONTH);
ALTER TABLE galette_cotisations DROP duree_mois_cotis;
ALTER TABLE galette_cotisations DROP date_cotis;

-- Add column to galette_types_cotisations;
ALTER TABLE galette_types_cotisation ADD cotis_extension enum('1') default NULL;
UPDATE galette_types_cotisation SET cotis_extension='1' WHERE
	id_type_cotis <= 3 OR id_type_cotis = 7;

-- Table for dynamic translation of strings;
DROP TABLE galette_l10n;
CREATE TABLE galette_l10n (
    text_orig varchar(40) NOT NULL,
    text_locale varchar(15) NOT NULL,
    text_nref int(10) NOT NULL default '1',
    text_trans varchar(100) NOT NULL default '',
    UNIQUE INDEX (text_orig(20), text_locale(5))
) TYPE=MyISAM;
