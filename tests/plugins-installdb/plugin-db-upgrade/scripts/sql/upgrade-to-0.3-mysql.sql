--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Upgrade script from 0.2 to 0.3, run by the UpgradeTo03 update class
ALTER TABLE galette_plugin_db_upgrade_detail_test ADD kind varchar(20) DEFAULT NULL;
