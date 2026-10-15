--
-- This file is part of Galette (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- table for members geographic positions
CREATE TABLE galette_maps_coordinates (
  id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
  latitude decimal(9,6) NOT NULL,
  longitude decimal(9,6) NOT NULL,
  PRIMARY KEY (id_adh)
);
