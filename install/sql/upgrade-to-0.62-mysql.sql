RENAME TABLE adherents TO galette_adherents;
RENAME TABLE cotisations TO galette_cotisations;
RENAME TABLE logs TO galette_logs;
RENAME TABLE preferences TO galette_preferences;
RENAME TABLE statuts TO galette_statuts;
RENAME TABLE types_cotisation TO galette_types_cotisation;

DROP TABLE galette_preferences;
CREATE TABLE galette_preferences (
  id_pref int(10) unsigned NOT NULL auto_increment,
  nom_pref varchar(100) NOT NULL default '',
  val_pref varchar(200) NOT NULL default '',
  PRIMARY KEY  (id_pref)
) TYPE=MyISAM;
