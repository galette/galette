ALTER TABLE galette_adherents ADD pref_lang varchar(20) default 'french' AFTER date_echeance;
INSERT INTO galette_types_cotisation VALUES (7, 'Cotisation annuelle (à payer)');
ALTER TABLE galette_adherents ADD  UNIQUE (login_adh);

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


