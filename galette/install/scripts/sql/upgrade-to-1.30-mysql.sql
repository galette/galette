--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

ALTER TABLE galette_field_types ADD COLUMN field_specifications JSON DEFAULT NULL;

ALTER TABLE galette_types_cotisation ADD COLUMN description longtext NULL;
UPDATE galette_types_cotisation SET description = '' WHERE description IS NULL;
ALTER TABLE galette_types_cotisation MODIFY COLUMN description longtext NOT NULL;

CREATE TABLE galette_plugins (
  plugin_id varchar(100) NOT NULL,
  version DECIMAL(4,3) NULL DEFAULT NULL,
  PRIMARY KEY (plugin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- preference values no longer fit in 255 characters (footer HTML, feature flags list)
ALTER TABLE galette_preferences MODIFY COLUMN val_pref text NOT NULL;

-- table for authentication attempts throttling
CREATE TABLE galette_auth_attempts (
  id_attempt int unsigned NOT NULL auto_increment,
  scope varchar(64) NOT NULL,
  identifier varchar(255) NOT NULL,
  failures int NOT NULL DEFAULT 0,
  first_failure datetime NULL DEFAULT NULL,
  last_failure datetime NULL DEFAULT NULL,
  locked_until datetime NULL DEFAULT NULL,
  PRIMARY KEY (id_attempt),
  UNIQUE KEY galette_auth_attempts_scope (scope, identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
