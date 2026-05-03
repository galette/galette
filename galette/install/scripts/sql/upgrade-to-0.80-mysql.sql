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

-- Change IP size to handle ipv6 address
ALTER TABLE galette_logs CHANGE ip_log ip_log varchar(46) NOT NULL DEFAULT '';
-- Change labels and translations sizes
ALTER TABLE galette_l10n CHANGE text_orig text_orig VARCHAR(100) NOT NULL;
ALTER TABLE galette_l10n DROP PRIMARY KEY, ADD PRIMARY KEY (text_orig, text_locale);
ALTER TABLE galette_types_cotisation CHANGE libelle_type_cotis libelle_type_cotis VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE galette_statuts CHANGE libelle_statut libelle_statut VARCHAR(100) NOT NULL DEFAULT '';
