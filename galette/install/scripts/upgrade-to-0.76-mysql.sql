--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Table for import models
DROP TABLE IF EXISTS galette_import_model;
CREATE TABLE galette_import_model (
  model_id smallint(6) NOT NULL auto_increment,
  model_fields text,
  model_creation_date datetime NOT NULL,
  PRIMARY KEY (model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

UPDATE galette_database SET version = 0.704;
