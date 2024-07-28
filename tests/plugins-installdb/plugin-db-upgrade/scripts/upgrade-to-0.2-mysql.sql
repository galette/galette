--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Upgrade script from 0.1 to 0.2: adds a detail table
DROP TABLE IF EXISTS galette_plugin_db_upgrade_detail_test;
CREATE TABLE galette_plugin_db_upgrade_detail_test (
  id int(10) unsigned NOT NULL auto_increment,
  upgrade_id int(10) unsigned NOT NULL,
  note text,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

