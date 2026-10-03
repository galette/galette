--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

RENAME TABLE adherents TO galette_adherents;
RENAME TABLE cotisations TO galette_cotisations;
RENAME TABLE logs TO galette_logs;
RENAME TABLE preferences TO galette_preferences;
RENAME TABLE statuts TO galette_statuts;
RENAME TABLE types_cotisation TO galette_types_cotisation;
