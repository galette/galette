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
ALTER TABLE galette_logs ALTER ip_log TYPE varchar(46);
-- Change labels and translations sizes
ALTER TABLE galette_l10n ALTER text_orig TYPE varchar(100);
ALTER TABLE galette_l10n ALTER text_trans TYPE varchar(100);
ALTER TABLE galette_types_cotisation ALTER libelle_type_cotis TYPE varchar(100);
ALTER TABLE galette_statuts ALTER libelle_statut TYPE varchar(100);

