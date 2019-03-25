SET FOREIGN_KEY_CHECKS=0;

-- table for saved searches
DROP TABLE IF EXISTS galette_searches;
CREATE TABLE galette_searches (
  search_id int(10) unsigned NOT NULL auto_increment,
  name varchar(100) DEFAULT NULL,
  form varchar(50) NOT NULL,
  parameters text NOT NULL,
  parameters_sum binary(20),
  id_adh int(10) unsigned,
  creation_date datetime NOT NULL,
  PRIMARY KEY (search_id),
  KEY (form, parameters_sum, id_adh),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE galette_adherents CHANGE date_crea_adh date_crea_adh date NOT NULL default '1901-01-01';
ALTER TABLE galette_cotisations CHANGE date_enreg date_enreg date NOT NULL default '1901-01-01';
ALTER TABLE galette_cotisations CHANGE date_debut_cotis date_debut_cotis date NOT NULL default '1901-01-01';
ALTER TABLE galette_cotisations CHANGE date_fin_cotis date_fin_cotis date NOT NULL default '1901-01-01';
ALTER TABLE galette_transactions CHANGE trans_date trans_date date NOT NULL default '1901-01-01';

UPDATE galette_database SET version = 0.93;
SET FOREIGN_KEY_CHECKS=1;
