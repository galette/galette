ALTER TABLE galette_adherents ADD pref_lang varchar(20) default 'french' AFTER date_echeance;
INSERT INTO galette_types_cotisation VALUES (7, 'Cotisation annuelle (à payer)');
ALTER TABLE galette_adherents ADD  UNIQUE (login_adh);

-- TODI: change ddn_adh type from date to varchar(10) default NULL;

-- Add new or missing preferences;
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_pays', '');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_method', '0');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_mail_smtp', '0');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_membership_ext', '12');
INSERT INTO galette_preferences (nom_pref, val_pref) VALUES ('pref_beg_membership', '');

-- New tables for dynamic fields;
DROP TABLE galette_info_categories;
CREATE TABLE galette_info_categories (
    id_cat int(10) unsigned NOT NULL auto_increment,
    index_cat int(10) NOT NULL default '0',
    name_cat varchar(40) NOT NULL default '',
    perm_cat int(10) NOT NULL default '0',
    type_cat int(10) NOT NULL default '0',
    size_cat int(10) NOT NULL default '1',
    contents_cat text DEFAULT '',
    PRIMARY KEY  (id_cat)
) TYPE=MyISAM;

DROP TABLE galette_adh_info;
CREATE TABLE galette_adh_info (
    id_adh_info int(10) unsigned NOT NULL auto_increment,
    id_adh int(10) NOT NULL default '0',
    id_cat int(10) NOT NULL default '0',
    index_info int(10) NOT NULL default '0',
    val_info text DEFAULT '',
    PRIMARY KEY  (id_adh_info)
) TYPE=MyISAM;

DROP TABLE IF EXISTS galette_pictures;
CREATE TABLE `galette_pictures` (
    `id_adh` int(10) unsigned NOT NULL default '0',
    `picture` blob NOT NULL,
    `format` varchar(10) NOT NULL default '',
    `width` int(10) unsigned NOT NULL default '0',
    `height` int(10) unsigned NOT NULL default '0',
    PRIMARY KEY  (`id_adh`)
) TYPE=MyISAM;

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
