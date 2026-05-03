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

UPDATE galette_adherents SET pseudo_adh = '' WHERE pseudo_adh IS NULL;
UPDATE galette_adherents SET prenom_adh = '' WHERE prenom_adh IS NULL;

-- Update fields length
ALTER TABLE galette_adherents ALTER COLUMN nom_adh TYPE varchar(50);
ALTER TABLE galette_adherents ALTER COLUMN prenom_adh TYPE varchar(50);
ALTER TABLE galette_adherents ALTER COLUMN prenom_adh SET DEFAULT '';
ALTER TABLE galette_adherents ALTER COLUMN prenom_adh SET NOT NULL;
ALTER TABLE galette_adherents ALTER COLUMN societe_adh TYPE varchar(200);
ALTER TABLE galette_transactions ALTER COLUMN trans_desc TYPE varchar(150);
ALTER TABLE galette_adherents ALTER COLUMN pseudo_adh SET DEFAULT '';
ALTER TABLE galette_adherents ALTER COLUMN pseudo_adh SET NOT NULL;

ALTER TABLE galette_field_types ALTER COLUMN field_repeat DROP DEFAULT;
ALTER TABLE galette_field_types ALTER field_repeat TYPE integer USING CASE WHEN field_repeat=false THEN NULL ELSE 0 END;
ALTER TABLE galette_field_types ALTER COLUMN field_repeat SET DEFAULT NULL;

UPDATE galette_database SET version = 0.701;
