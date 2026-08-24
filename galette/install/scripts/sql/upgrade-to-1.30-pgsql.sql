--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

ALTER TABLE galette_field_types ADD COLUMN field_specifications JSON DEFAULT NULL;

ALTER TABLE galette_types_cotisation ADD COLUMN description text;
UPDATE galette_types_cotisation SET description = '' WHERE description IS NULL;
ALTER TABLE galette_types_cotisation ALTER COLUMN description SET NOT NULL;

CREATE TABLE galette_plugins (
  plugin_id character varying(100) NOT NULL,
  version decimal DEFAULT NULL,
  PRIMARY KEY (plugin_id)
);

-- preference values no longer fit in 255 characters (footer HTML, feature flags list)
ALTER TABLE galette_preferences ALTER COLUMN val_pref TYPE text;
