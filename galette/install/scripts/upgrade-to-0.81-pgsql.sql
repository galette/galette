--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Pref numrows can no longer equals to 0
UPDATE galette_preferences SET val_pref = '100' WHERE nom_pref = 'pref_numrows' AND val_pref = '0';

UPDATE galette_database SET version = 0.81;
