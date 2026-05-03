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

ALTER TABLE adherents RENAME TO galette_adherents;
ALTER TABLE cotisations RENAME TO galette_cotisations;
ALTER TABLE logs RENAME TO galette_logs;
ALTER TABLE preferences RENAME TO galette_preferences;
ALTER TABLE statuts RENAME TO galette_statuts;
ALTER TABLE types_cotisation RENAME TO galette_types_cotisation;
