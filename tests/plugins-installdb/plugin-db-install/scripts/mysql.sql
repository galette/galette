--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

DROP TABLE IF EXISTS galette_plugin_db_install_test;
CREATE TABLE galette_plugin_db_install_test (
  id int(10) unsigned NOT NULL auto_increment,
  label varchar(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

