---
description: Build Galette frontend assets via Gulp/npm. Handles standard build, watch mode, or full rebuild.
---

Build the Galette frontend assets from the project root `/var/www/html/private/galette.git`.

Detect the intent:
- **Standard build** (default): `npm run build`
- **Watch mode** (for active frontend development): `npm run watch` — runs continuously, stop with Ctrl+C
- **Full rebuild** (if build is broken): `npm run rebuild` — slow, cleans and reinstalls everything

**Critical rule**: source files live in `ui/`. Never edit files in `galette/webroot/themes/` — they are generated and overwritten by the next build.

Report which files were compiled. If the build succeeds, confirm: "Frontend assets built to galette/webroot/themes/default/ui/".
