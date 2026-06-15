/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import { closeSync, existsSync, openSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';

/**
 * Helper for the web installer enable file.
 *
 * The installer only runs while an `ENABLE_INSTALL` file exists in Galette data
 * directory, and Galette removes it once an install or an update succeeds.
 * Tests have to create and remove it themselves; they run on the same host as
 * the server.
 *
 * That file is global server state, and the suite is fully parallel - across
 * projects too, `npm test` runs a11y and chromium together. Any test touching
 * it must therefore hold the lock below for its whole duration:
 *
 *   test.beforeEach(async () => { await InstallerHelper.acquire(); });
 *   test.afterEach(() => { InstallerHelper.disable(); InstallerHelper.release(); });
 */
export class InstallerHelper {

  // Where Galette looks for the file depends on how the server was started:
  // tests/router_e2e.php moves GALETTE_DATA_PATH to tests/tests-data (see
  // tests/test_env.inc.php), a plain instance keeps galette/data. Rather than
  // guess, write the marker in every candidate - it is an empty file, and both
  // paths are gitignored. Playwright runs from the repo root.
  private static readonly candidates = [
    'tests/tests-data',
    'galette/data',
  ].map(dir => path.resolve(process.cwd(), dir, 'ENABLE_INSTALL'));

  private static readonly lockFile = path.join(tmpdir(), 'galette-e2e-installer.lock');

  /**
   * Create the enable file, so the installer answers
   */
  static enable(): void {
    const written = InstallerHelper.candidates.filter(file => existsSync(path.dirname(file)));

    if (written.length === 0) {
      throw new Error(
        `No Galette data directory found among ${InstallerHelper.candidates.join(', ')}. `
        + 'Run the tests from the repository root, on an instance served from it.'
      );
    }

    written.forEach(file => writeFileSync(file, ''));
  }

  /**
   * Remove the enable file, so the installer is disabled again
   */
  static disable(): void {
    InstallerHelper.candidates.forEach(file => rmSync(file, { force: true }));
  }

  /**
   * Take exclusive ownership of the enable file
   *
   * @param timeout - How long to wait for another worker, in milliseconds
   */
  static async acquire(timeout: number = 30000): Promise<void> {
    const deadline = Date.now() + timeout;

    while (!InstallerHelper.tryLock()) {
      if (Date.now() > deadline) {
        // Left over by a run that died before releasing it
        InstallerHelper.release();
        InstallerHelper.tryLock();
        return;
      }
      await new Promise(resolve => setTimeout(resolve, 100));
    }
  }

  /**
   * Give the enable file back to the other workers
   */
  static release(): void {
    rmSync(InstallerHelper.lockFile, { force: true });
  }

  /**
   * Create the lock file, which only succeeds when no one else holds it
   */
  private static tryLock(): boolean {
    try {
      closeSync(openSync(InstallerHelper.lockFile, 'wx'));
      return true;
    } catch {
      return false;
    }
  }
}
