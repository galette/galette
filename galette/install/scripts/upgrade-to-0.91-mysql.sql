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

-- add sender name and email in mailing history
ALTER TABLE galette_mailing_history ADD mailing_sender_name VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE galette_mailing_history ADD mailing_sender_address VARCHAR(255) NULL DEFAULT NULL;

-- fix email field size
ALTER TABLE galette_adherents CHANGE email_adh email_adh VARCHAR(255) DEFAULT NULL;

-- fix gpg field size
ALTER TABLE galette_adherents CHANGE gpgid gpgid TEXT;

-- Clean possible buggy data from RC
DELETE FROM galette_dynamic_fields WHERE item_id = 0;

-- Fix DB relations
UPDATE galette_cotisations
    LEFT JOIN galette_transactions ON galette_cotisations.trans_id = galette_transactions.trans_id
    SET galette_cotisations.trans_id=null
    WHERE galette_cotisations.trans_id IS NOT NULL AND galette_transactions.trans_id IS NULL;
ALTER TABLE galette_cotisations ADD FOREIGN KEY (trans_id) REFERENCES galette_transactions(trans_id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Detailled log history has ben dropped
UPDATE galette_preferences SET val_pref = 1 WHERE nom_pref = 'pref_log' AND val_pref = 2;

UPDATE galette_database SET version = 0.91;
SET FOREIGN_KEY_CHECKS=1;
