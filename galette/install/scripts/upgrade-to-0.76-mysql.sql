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

-- Table for import models
DROP TABLE IF EXISTS galette_import_model;
CREATE TABLE galette_import_model (
  model_id smallint(6) NOT NULL auto_increment,
  model_fields text,
  model_creation_date datetime NOT NULL,
  PRIMARY KEY (model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

UPDATE galette_database SET version = 0.704;
