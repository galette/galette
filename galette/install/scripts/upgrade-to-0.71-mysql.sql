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

-- NULL cause issues on filtering, see #311
UPDATE galette_adherents SET pseudo_adh = '' WHERE pseudo_adh IS NULL;
UPDATE galette_adherents SET prenom_adh = '' WHERE prenom_adh IS NULL;

-- Update fields length
ALTER TABLE galette_adherents CHANGE nom_adh nom_adh varchar(50) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE prenom_adh prenom_adh varchar(50) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE societe_adh societe_adh varchar(200) default NULL;
ALTER TABLE galette_transactions CHANGE trans_desc trans_desc varchar(150) NOT NULL default '';
ALTER TABLE galette_adherents CHANGE pseudo_adh pseudo_adh varchar(20) NOT NULL default '';

UPDATE galette_database SET version = 0.701;
