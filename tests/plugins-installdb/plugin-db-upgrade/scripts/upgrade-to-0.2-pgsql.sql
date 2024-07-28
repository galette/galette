--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Upgrade script from 0.1 to 0.2: adds a detail table
DROP TABLE IF EXISTS galette_plugin_db_upgrade_detail_test;
CREATE TABLE galette_plugin_db_upgrade_detail_test (
  id serial NOT NULL,
  upgrade_id integer NOT NULL,
  note text,
  PRIMARY KEY (id)
);

