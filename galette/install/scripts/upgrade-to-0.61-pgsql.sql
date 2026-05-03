--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

ALTER TABLE adherents RENAME TO galette_adherents;
ALTER TABLE cotisations RENAME TO galette_cotisations;
ALTER TABLE logs RENAME TO galette_logs;
ALTER TABLE preferences RENAME TO galette_preferences;
ALTER TABLE statuts RENAME TO galette_statuts;
ALTER TABLE types_cotisation RENAME TO galette_types_cotisation;
