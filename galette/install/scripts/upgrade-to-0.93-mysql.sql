--
-- Copyright © 2003-2026 The Galette Team
--
-- This file is part of Galette (https://galette.eu).
--
-- Galette is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- Galette is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
--  GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with Galette. If not, see <http://www.gnu.org/licenses/>.
--

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

UPDATE galette_fields_categories SET category = 'Identity:' WHERE id_field_category = 1;
UPDATE galette_fields_categories SET category = 'Galette-related data:' WHERE id_field_category = 2;
UPDATE galette_fields_categories SET category = 'Contact information:' WHERE id_field_category = 3;

UPDATE galette_database SET version = 0.93;
SET FOREIGN_KEY_CHECKS=1;
