--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

SET FOREIGN_KEY_CHECKS=0;
-- Families
ALTER TABLE galette_adherents ADD parent_id int(10) unsigned DEFAULT NULL;
ALTER TABLE galette_adherents ADD FOREIGN KEY (parent_id) REFERENCES galette_adherents (id_adh) ON DELETE RESTRICT ON UPDATE RESTRICT;

UPDATE galette_database SET version = 0.82;
SET FOREIGN_KEY_CHECKS=1;
