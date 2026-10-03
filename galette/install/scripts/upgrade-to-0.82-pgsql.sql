--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Families
ALTER TABLE galette_adherents ADD parent_id integer;
ALTER TABLE galette_adherents ALTER COLUMN parent_id SET DEFAULT NULL;
ALTER TABLE galette_adherents ADD CONSTRAINT galette_adherents_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES galette_adherents(id_adh);

UPDATE galette_database SET version = 0.82;
