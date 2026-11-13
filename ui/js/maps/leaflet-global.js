/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

// Leaflet is already loaded by maps-main.bundle.min.js; hand the bundler that
// instance instead of a second copy, or the binding would extend a Leaflet the
// page never sees.
module.exports = window.L;
