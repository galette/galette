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

ALTER TABLE adherents ADD jabber_adh character varying(150);
ALTER TABLE adherents ADD bool_display_info character(1) DEFAULT NULL;
ALTER TABLE adherents ADD info_public_adh text;
ALTER TABLE adherents ADD pays_adh character varying(50) DEFAULT NULL;
ALTER TABLE adherents ADD adresse2_adh character varying(150) DEFAULT NULL;

CREATE SEQUENCE logs_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

CREATE TABLE logs (
  id_log integer DEFAULT nextval('logs_id_seq'::text) NOT NULL,
  date_log timestamp NOT NULL,
  ip_log character varying(30) DEFAULT '' NOT NULL,
  adh_log character varying(41) DEFAULT '' NOT NULL,
  text_log text
);
