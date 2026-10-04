--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- outgoing webhooks and their deliveries queue
CREATE TABLE galette_webhooks (
  id_webhook int unsigned NOT NULL auto_increment,
  name varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  url varchar(2048) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  secret varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  events text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  access_level tinyint NOT NULL DEFAULT 2,
  active tinyint(1) NOT NULL DEFAULT 1,
  creation_date datetime NOT NULL,
  modification_date datetime NOT NULL,
  PRIMARY KEY (id_webhook)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE galette_webhooks_deliveries (
  id_delivery int unsigned NOT NULL auto_increment,
  id_webhook int unsigned NOT NULL,
  message_id varchar(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  event_type varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  payload longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  status tinyint(1) NOT NULL DEFAULT 0,
  attempts int unsigned NOT NULL DEFAULT 0,
  next_attempt_at datetime DEFAULT NULL,
  claimed_at datetime DEFAULT NULL,
  delivered_at datetime DEFAULT NULL,
  response_code smallint DEFAULT NULL,
  last_error text COLLATE utf8mb4_unicode_520_ci,
  creation_date datetime NOT NULL,
  PRIMARY KEY (id_delivery),
  UNIQUE KEY galette_webhooks_deliveries_message (id_webhook, message_id),
  KEY galette_webhooks_deliveries_status (status, next_attempt_at),
  KEY galette_webhooks_deliveries_creation (creation_date),
  FOREIGN KEY (id_webhook) REFERENCES galette_webhooks (id_webhook) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
