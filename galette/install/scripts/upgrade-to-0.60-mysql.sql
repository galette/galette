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
