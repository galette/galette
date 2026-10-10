--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- tables for roles based access control
CREATE TABLE galette_roles (
  id_role int unsigned NOT NULL auto_increment,
  role_key varchar(50) NULL DEFAULT NULL,
  name varchar(100) NOT NULL,
  id_parent int unsigned NULL DEFAULT NULL,
  PRIMARY KEY (id_role),
  UNIQUE KEY (role_key),
  UNIQUE KEY (name),
  FOREIGN KEY (id_parent) REFERENCES galette_roles (id_role) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE galette_roles_permissions (
  id_role int unsigned NOT NULL,
  permission varchar(100) NOT NULL,
  PRIMARY KEY (id_role, permission),
  FOREIGN KEY (id_role) REFERENCES galette_roles (id_role) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE galette_members_roles (
  id_member_role int unsigned NOT NULL auto_increment,
  id_adh int unsigned NOT NULL,
  id_role int unsigned NOT NULL,
  id_group int unsigned NULL DEFAULT NULL,
  PRIMARY KEY (id_member_role),
  UNIQUE KEY (id_adh, id_role, id_group),
  FOREIGN KEY (id_adh) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_role) REFERENCES galette_roles (id_role) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_group) REFERENCES galette_groups (id_group) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
