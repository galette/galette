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

-- Update fields length
ALTER TABLE galette_adherents ALTER COLUMN nom_adh TYPE varchar(255);
ALTER TABLE galette_adherents ALTER COLUMN prenom_adh TYPE varchar(255);
ALTER TABLE galette_adherents ALTER COLUMN pseudo_adh TYPE varchar(255);
ALTER TABLE galette_adherents ALTER COLUMN adresse_adh TYPE text;
ALTER TABLE galette_adherents ALTER COLUMN ville_adh TYPE varchar(200);
ALTER TABLE galette_adherents ALTER COLUMN pays_adh TYPE varchar(200);
ALTER TABLE galette_adherents ALTER COLUMN tel_adh TYPE varchar(50);
ALTER TABLE galette_adherents ALTER COLUMN gsm_adh TYPE varchar(50);
ALTER TABLE galette_adherents ALTER COLUMN url_adh TYPE varchar(255);
ALTER TABLE galette_adherents ALTER COLUMN login_adh TYPE varchar(255);
ALTER TABLE galette_adherents ALTER COLUMN mdp_adh TYPE varchar(255);
ALTER TABLE galette_adherents ALTER COLUMN fingerprint TYPE varchar(255);

ALTER TABLE galette_transactions ALTER COLUMN trans_desc TYPE varchar(255);

ALTER TABLE galette_statuts ALTER COLUMN libelle_statut TYPE varchar(255);

ALTER TABLE galette_titles ALTER COLUMN long_label TYPE varchar(100);

ALTER TABLE galette_preferences ALTER COLUMN val_pref TYPE varchar(255);

ALTER TABLE galette_logs ALTER COLUMN adh_log TYPE varchar(255);

ALTER TABLE galette_field_types ALTER COLUMN field_name TYPE varchar(255);

ALTER TABLE galette_l10n ALTER COLUMN text_orig TYPE varchar(255);
ALTER TABLE galette_l10n ALTER COLUMN text_trans TYPE varchar(255);

ALTER TABLE galette_tmppasswds ALTER COLUMN tmp_passwd TYPE varchar(250);

ALTER TABLE galette_texts ALTER COLUMN tcomment TYPE varchar(255);

ALTER TABLE galette_fields_categories ALTER COLUMN category TYPE varchar(100);

ALTER TABLE galette_mailing_history ALTER COLUMN mailing_sender_name TYPE varchar(255);

ALTER TABLE galette_groups ALTER COLUMN group_name TYPE varchar(250);

ALTER TABLE galette_pdfmodels ALTER COLUMN model_title TYPE varchar(250);
ALTER TABLE galette_pdfmodels ALTER COLUMN model_subtitle TYPE varchar(250);

ALTER TABLE galette_tmplinks ALTER COLUMN hash TYPE varchar(250);

DROP TABLE IF EXISTS galette_required;

UPDATE galette_database SET version = 0.950;
