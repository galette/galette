--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- invalid on purpose: key on a missing column
CREATE TABLE galette_plugin_db_broken_test (
  id integer NOT NULL,
  PRIMARY KEY (missing_column)
);
