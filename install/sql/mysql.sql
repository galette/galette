DROP TABLE galette_adherents;
CREATE TABLE galette_adherents (
  id_adh int(10) unsigned NOT NULL auto_increment,
  id_statut int(10) unsigned NOT NULL default '4',
  nom_adh varchar(20) NOT NULL default '',
  prenom_adh varchar(20) default NULL,
  pseudo_adh varchar(20) default NULL,
  titre_adh tinyint(3) unsigned NOT NULL default '0',
  ddn_adh date default '1901-01-01',
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
  pref_lang varchar(20) default 'french',
  lieu_naissance text default '',
  gpgid varchar(8) DEFAULT NULL,
  fingerprint varchar(50) DEFAULT NULL,
  PRIMARY KEY  (id_adh),
  UNIQUE (login_adh)
) TYPE=MyISAM;

DROP TABLE galette_cotisations;
CREATE TABLE galette_cotisations (
  id_cotis int(10) unsigned NOT NULL auto_increment,
  id_adh int(10) unsigned NOT NULL default '0',
  id_type_cotis int(10) unsigned NOT NULL default '0',
  montant_cotis float unsigned default '0',
  info_cotis text,
  date_enreg date NOT NULL default '0000-00-00',
  date_debut_cotis date NOT NULL default '0000-00-00',
  date_fin_cotis date NOT NULL default '0000-00-00',
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
  cotis_extension enum('1') default NULL,
  PRIMARY KEY  (id_type_cotis)
) TYPE=MyISAM;

INSERT INTO galette_types_cotisation VALUES (1, 'Cotisation annuelle normale', '1');
INSERT INTO galette_types_cotisation VALUES (2, 'Cotisation annuelle réduite', '1');
INSERT INTO galette_types_cotisation VALUES (3, 'Cotisation entreprise', '1');
INSERT INTO galette_types_cotisation VALUES (4, 'Donation en nature', null);
INSERT INTO galette_types_cotisation VALUES (5, 'Donation pécunière', null);
INSERT INTO galette_types_cotisation VALUES (6, 'Partenariat', null);
INSERT INTO galette_types_cotisation VALUES (7, 'Cotisation annuelle (à payer)', '1');

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

DROP TABLE galette_adh_field_type;
CREATE TABLE galette_adh_field_type (
    field_id int(10) unsigned NOT NULL auto_increment,
    field_index int(10) NOT NULL default '0',
    field_name varchar(40) NOT NULL default '',
    field_perm int(10) NOT NULL default '0',
    field_type int(10) NOT NULL default '0',
    field_repeat int(10) NOT NULL default '1',
    field_req enum('1') default NULL,
    field_contents text DEFAULT '',
    PRIMARY KEY  (field_id)
) TYPE=MyISAM;

DROP TABLE galette_adh_fields;
CREATE TABLE galette_adh_fields (
    id_adh int(10) NOT NULL default '0',
    field_id int(10) NOT NULL default '0',
    val_index int(10) NOT NULL default '0',
    field_val text DEFAULT '',
    KEY  (id_adh)
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

