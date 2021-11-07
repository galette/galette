DROP TABLE IF EXISTS adherents;
CREATE TABLE adherents (
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
  date_crea_adh date NOT NULL default '1901-01-01',
  activite_adh enum('0','1') NOT NULL default '0',
  bool_admin_adh enum('1') default NULL,
  bool_exempt_adh enum('1') default NULL,
  bool_display_info enum('1') default NULL,
  date_echeance date default NULL,
  PRIMARY KEY  (id_adh)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS cotisations;
CREATE TABLE cotisations (
  id_cotis int(10) unsigned NOT NULL auto_increment,
  id_adh int(10) unsigned NOT NULL default '0',
  id_type_cotis int(10) unsigned NOT NULL default '0',
  montant_cotis float unsigned default '0',
  info_cotis text,
  duree_mois_cotis tinyint(3) unsigned NOT NULL default '12',
  date_cotis date NOT NULL default '1901-01-01',
  PRIMARY KEY  (id_cotis)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS statuts;
CREATE TABLE statuts (
  id_statut int(10) unsigned NOT NULL auto_increment,
  libelle_statut varchar(20) NOT NULL default '',
  priorite_statut tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (id_statut)
) ENGINE=MyISAM;


INSERT INTO statuts VALUES (1,'President',0);
INSERT INTO statuts VALUES (10,'Vice-president',5);
INSERT INTO statuts VALUES (2,'Tresorier',10);
INSERT INTO statuts VALUES (4,'Membre actif',30);
INSERT INTO statuts VALUES (5,'Membre bienfaiteur',40);
INSERT INTO statuts VALUES (6,'Membre fondateur',50);
INSERT INTO statuts VALUES (3,'Secretaire',20);
INSERT INTO statuts VALUES (7,'Ancien',60);
INSERT INTO statuts VALUES (8,'Personne morale',70);
INSERT INTO statuts VALUES (9,'Non membre',80);

DROP TABLE IF EXISTS types_cotisation;
CREATE TABLE types_cotisation (
  id_type_cotis int(10) unsigned NOT NULL auto_increment,
  libelle_type_cotis varchar(30) NOT NULL default '',
  PRIMARY KEY  (id_type_cotis)
) ENGINE=MyISAM;


INSERT INTO types_cotisation VALUES (1,'Cotisation annuelle normale');
INSERT INTO types_cotisation VALUES (2,'Cotisation annuelle reduite');
INSERT INTO types_cotisation VALUES (3,'Cotisation entreprise');
INSERT INTO types_cotisation VALUES (4,'Donation en nature');
INSERT INTO types_cotisation VALUES (5,'Donation pecuniere');
INSERT INTO types_cotisation VALUES (6,'Partenariat');

DROP TABLE IF EXISTS preferences;
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
) ENGINE=MyISAM;

DROP TABLE IF EXISTS logs;
CREATE TABLE logs (
  id_log int(10) unsigned NOT NULL auto_increment,
  date_log datetime NOT NULL,
  ip_log varchar(30) NOT NULL default '',
  adh_log varchar(41) NOT NULL default '',
  text_log text,
  PRIMARY KEY  (id_log)
) ENGINE=MyISAM;

