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

-- add sender name and email in mailing history
ALTER TABLE galette_mailing_history ADD COLUMN mailing_sender_name character varying(100) DEFAULT NULL;
ALTER TABLE galette_mailing_history ADD COLUMN mailing_sender_address character varying(255) DEFAULT NULL;

-- fix email field size
ALTER TABLE galette_adherents ALTER COLUMN email_adh TYPE varchar(255);

-- fix gpg field size
ALTER TABLE galette_adherents ALTER COLUMN gpgid TYPE text;

-- Clean possible buggy data from RC
DELETE FROM galette_dynamic_fields WHERE item_id = 0;

-- Detailled log history has ben dropped
UPDATE galette_preferences SET val_pref = 1 WHERE nom_pref = 'pref_log' AND val_pref = '2';

UPDATE galette_database SET version = 0.91;
