ALTER TABLE adherents ADD jabber_adh varchar(150) default NULL AFTER msn_adh;
ALTER TABLE adherents ADD bool_display_info enum('1') default NULL AFTER bool_exempt_adh;
ALTER TABLE adherents ADD info_public_adh text AFTER info_adh;
ALTER TABLE adherents ADD pays_adh varchar(50) default NULL AFTER ville_adh;
ALTER TABLE adherents ADD adresse2_adh varchar(150) default NULL AFTER adresse_adh;
CREATE TABLE preferences (
  pref_nom varchar(40) NOT NULL default '',
  pref_adresse varchar(150) NOT NULL default '',
  pref_adresse2 varchar(150) default NULL,
  pref_cp varchar(10) NOT NULL default '',
  pref_ville varchar(50) NOT NULL default '',
  pref_pays varchar(50) default NULL,
  pref_lang varchar(20) NOT NULL default '',
  pref_numrows int(10) unsigned NOT NULL default '30',
  pref_log enum('0','1','2') NOT NULL default '1',
  pref_email_nom varchar(20) NOT NULL default '',
  pref_email varchar(150) NOT NULL default '',
  pref_etiq_marges int(10) unsigned NOT NULL default '0',
  pref_etiq_hspace int(10) unsigned NOT NULL default '0',
  pref_etiq_vspace int(10) unsigned NOT NULL default '0',
  pref_etiq_hsize int(10) unsigned NOT NULL default '0',
  pref_etiq_vsize int(10) unsigned NOT NULL default '0',
  pref_etiq_cols int(10) unsigned NOT NULL default '0',
  pref_etiq_rows int(10) unsigned NOT NULL default '0',
  pref_etiq_corps int(10) unsigned NOT NULL default '0',
  pref_admin_login varchar(20) NOT NULL default '',
  pref_admin_pass varchar(20) NOT NULL default ''
) TYPE=MyISAM;

CREATE TABLE logs (
  id_log int(10) unsigned NOT NULL auto_increment,
  date_log datetime NOT NULL,
  ip_log varchar(30) NOT NULL default '',
  adh_log varchar(41) NOT NULL default '',
  text_log text,
  PRIMARY KEY  (id_log)
) TYPE=MyISAM;

DELETE FROM statuts;
INSERT INTO statuts VALUES (1,'Président',0);
INSERT INTO statuts VALUES (10,'Vice-président',5);
INSERT INTO statuts VALUES (2,'Trésorier',10);
INSERT INTO statuts VALUES (4,'Membre actif',30);
INSERT INTO statuts VALUES (5,'Membre bienfaiteur',40);
INSERT INTO statuts VALUES (6,'Membre fondateur',50);
INSERT INTO statuts VALUES (3,'Secrétaire',20);
INSERT INTO statuts VALUES (7,'Ancien',60);
INSERT INTO statuts VALUES (8,'Personne morale',70);
INSERT INTO statuts VALUES (9,'Non membre',80);
