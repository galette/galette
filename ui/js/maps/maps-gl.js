/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import * as maplibregl from 'maplibre-gl';
// Registers L.maplibreGL on the Leaflet the page already loaded (see the
// `leaflet` alias in gulpfile.js).
import '@maplibre/maplibre-gl-leaflet';

// The worker is shipped next to this bundle; derive its URL from our own
// <script> so no template has to know where assets are served from.
const bundle_url = document.currentScript && document.currentScript.src;
if (bundle_url) {
  maplibregl.setWorkerUrl(new URL('maps-gl.worker.min.js', bundle_url).href);
}

export * from 'maplibre-gl';
