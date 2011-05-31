ALTER TABLE adherents ADD jabber_adh varchar(150) default NULL AFTER msn_adh;
ALTER TABLE adherents ADD bool_display_info enum('1') default NULL AFTER bool_exempt_adh;
ALTER TABLE adherents ADD info_public_adh text AFTER info_adh;
ALTER TABLE adherents ADD pays_adh varchar(50) default NULL AFTER ville_adh;
ALTER TABLE adherents ADD adresse2_adh varchar(150) default NULL AFTER adresse_adh;

CREATE TABLE logs (
  id_log int(10) unsigned NOT NULL auto_increment,
  date_log datetime NOT NULL,
  ip_log varchar(30) NOT NULL default '',
  adh_log varchar(41) NOT NULL default '',
  text_log text,
  PRIMARY KEY  (id_log)
) ENGINE=MyISAM;
