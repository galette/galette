DROP TABLE galette_adherents;
CREATE TABLE galette_adherents (
  id_adh int(10) unsigned NOT NULL auto_increment,
  id_statut int(10) unsigned NOT NULL default '4',
  nom_adh varchar(20) NOT NULL default '',
  prenom_adh varchar(20) default NULL,
  pseudo_adh varchar(20) default NULL,
  titre_adh tinyint(3) unsigned NOT NULL default '0',
  ddn_adh date default NULL,
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
  mdp_adh varchar(20) NOT NULL default '',
  date_crea_adh date NOT NULL default '0000-00-00',
  activite_adh enum('0','1') NOT NULL default '0',
  bool_admin_adh enum('1') default NULL,
  bool_exempt_adh enum('1') default NULL,
  bool_display_info enum('1') default NULL,
  date_echeance date default NULL,
  PRIMARY KEY  (id_adh)
) TYPE=MyISAM;

DROP TABLE galette_cotisations;
CREATE TABLE galette_cotisations (
  id_cotis int(10) unsigned NOT NULL auto_increment,
  id_adh int(10) unsigned NOT NULL default '0',
  id_type_cotis int(10) unsigned NOT NULL default '0',
  montant_cotis float unsigned default '0',
  info_cotis text,
  duree_mois_cotis tinyint(3) unsigned NOT NULL default '12',
  date_cotis date NOT NULL default '0000-00-00',
  PRIMARY KEY  (id_cotis)
) TYPE=MyISAM;

DROP TABLE galette_statuts;
CREATE TABLE galette_statuts (
  id_statut int(10) unsigned NOT NULL auto_increment,
  libelle_statut varchar(20) NOT NULL default '',
  priorite_statut tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (id_statut)
) TYPE=MyISAM;


INSERT INTO galette_statuts VALUES (1,'Président',0);
INSERT INTO galette_statuts VALUES (10,'Vice-président',5);
INSERT INTO galette_statuts VALUES (2,'Trésorier',10);
INSERT INTO galette_statuts VALUES (4,'Membre actif',30);
INSERT INTO galette_statuts VALUES (5,'Membre bienfaiteur',40);
INSERT INTO galette_statuts VALUES (6,'Membre fondateur',50);
INSERT INTO galette_statuts VALUES (3,'Secrétaire',20);
INSERT INTO galette_statuts VALUES (7,'Ancien',60);
INSERT INTO galette_statuts VALUES (8,'Personne morale',70);
INSERT INTO galette_statuts VALUES (9,'Non membre',80);

DROP TABLE galette_types_cotisation;
CREATE TABLE galette_types_cotisation (
  id_type_cotis int(10) unsigned NOT NULL auto_increment,
  libelle_type_cotis varchar(30) NOT NULL default '',
  PRIMARY KEY  (id_type_cotis)
) TYPE=MyISAM;


INSERT INTO galette_types_cotisation VALUES (1,'Cotisation annuelle normale');
INSERT INTO galette_types_cotisation VALUES (2,'Cotisation annuelle réduite');
INSERT INTO galette_types_cotisation VALUES (3,'Cotisation entreprise');
INSERT INTO galette_types_cotisation VALUES (4,'Donation en nature');
INSERT INTO galette_types_cotisation VALUES (5,'Donation pécunière');
INSERT INTO galette_types_cotisation VALUES (6,'Partenariat');

DROP TABLE galette_preferences;
CREATE TABLE galette_preferences (
  id_pref int(10) unsigned NOT NULL auto_increment,
  nom_pref varchar(100) NOT NULL default '',
  val_pref varchar(200) NOT NULL default '',
  PRIMARY KEY  (id_pref)
) TYPE=MyISAM;
	
DROP TABLE galette_logs;
CREATE TABLE galette_logs (
  id_log int(10) unsigned NOT NULL auto_increment,
  date_log datetime NOT NULL,
  ip_log varchar(30) NOT NULL default '',
  adh_log varchar(41) NOT NULL default '',
  text_log text,
  PRIMARY KEY  (id_log)
) TYPE=MyISAM;

