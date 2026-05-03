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
DROP TABLE IF EXISTS galette_titles;
CREATE TABLE galette_titles (
  id_title int(10) unsigned NOT NULL auto_increment,
  short_label varchar(10) NOT NULL default '',
  long_label varchar(30) NULL default '',
  PRIMARY KEY  (id_title)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- insert required data for proper conversion
INSERT INTO galette_titles(short_label) VALUES ('Mr.');
INSERT INTO galette_titles(short_label) VALUES ('Mrs.');
INSERT INTO galette_titles(short_label) VALUES ('Miss');

ALTER TABLE galette_adherents CHANGE titre_adh titre_adh int(10) unsigned DEFAULT NULL;
ALTER TABLE galette_adherents ADD FOREIGN KEY (titre_adh) REFERENCES galette_titles (id_title) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE galette_adherents CHANGE mdp_adh mdp_adh VARCHAR( 60 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '';
ALTER TABLE galette_tmppasswds CHANGE tmp_passwd tmp_passwd VARCHAR( 60 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '';

ALTER TABLE galette_adherents ADD sexe_adh TINYINT(1) DEFAULT 0;
UPDATE galette_adherents SET sexe_adh = titre_adh;
UPDATE galette_adherents SET sexe_adh = 2 WHERE sexe_adh = 3;

UPDATE galette_database SET version = 0.702;
SET FOREIGN_KEY_CHECKS=1;
