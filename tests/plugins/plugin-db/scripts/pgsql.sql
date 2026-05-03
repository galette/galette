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

DROP SEQUENCE IF EXISTS galette_db_test_id_seq;
CREATE SEQUENCE galette_db_test_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE IF EXISTS galette_db_test;
CREATE TABLE galette_db_test (
  id integer DEFAULT nextval('galette_db_test_id_seq'::text) NOT NULL,
  date_log timestamp NOT NULL,
  comment text,
  PRIMARY KEY (id)
);

