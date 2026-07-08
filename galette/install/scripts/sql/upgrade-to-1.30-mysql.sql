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

CREATE TABLE galette_mailing_queue (
  mailing_queue_id int unsigned NOT NULL auto_increment,
  kind tinyint(1) NOT NULL DEFAULT 0,
  mailing_id int unsigned DEFAULT NULL,
  reminder_type int DEFAULT NULL,
  recipient_id int unsigned DEFAULT NULL,
  recipient_email varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  recipient_name varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  status tinyint(1) NOT NULL DEFAULT 0,
  attempts int unsigned NOT NULL DEFAULT 0,
  last_error longtext,
  scheduled_at datetime DEFAULT NULL,
  sent_at datetime DEFAULT NULL,
  PRIMARY KEY (mailing_queue_id),
  KEY galette_mailing_queue_status (status, scheduled_at),
  KEY galette_mailing_queue_sent_at (sent_at),
  FOREIGN KEY (mailing_id) REFERENCES galette_mailing_history (mailing_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- qmail method has been removed, fall back to sendmail (closest local MTA method)
UPDATE galette_preferences SET val_pref = '5' WHERE nom_pref = 'pref_mail_method' AND val_pref = '3';
