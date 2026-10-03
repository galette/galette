<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests;

use Safe\DateTime;

/**
 * Freeze the current date/time in tests.
 *
 * Galette reads "now" directly through `new DateTime()` and `date()`. As there
 * is no clock abstraction, some computations (membership periods, due dates,
 * ...) behave differently depending on the day the test suite is run - a test
 * may pass on August 1st but fail on July 31st. This trait lets a test pin
 * "now" to an arbitrary instant so such cases become deterministic and
 * reproducible.
 *
 * It relies on libfaketime (https://github.com/wolfcw/libfaketime), which must
 * be preloaded when running PHPUnit:
 *
 *   LD_PRELOAD=/usr/lib64/libfaketime.so.1 FAKETIME_NO_CACHE=1 \
 *       DB=pgsql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/
 *
 * `FAKETIME_NO_CACHE=1` is required so the `FAKETIME` environment variable is
 * re-read on every call, which is what allows changing the faked time from one
 * test to the next within the same PHPUnit process.
 *
 * When libfaketime is not available, {@see FakeTime::setFakeTime()} marks the
 * test as skipped rather than letting it run against the real clock (which
 * would defeat the purpose and could be flaky).
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait FakeTime
{
    private bool $faketime_set = false;

    /**
     * Freeze "now" to the given date/time for the rest of the test.
     *
     * @param string $datetime Date/time to freeze to (any format libfaketime
     *                         understands, e.g. '2026-07-31 10:00:00')
     */
    protected function setFakeTime(string $datetime): void
    {
        if (!self::isFakeTimeAvailable()) {
            $this->markTestSkipped(
                'This test requires libfaketime to be preloaded. Run PHPUnit with '
                . 'LD_PRELOAD=/path/to/libfaketime.so.1 FAKETIME_NO_CACHE=1'
            );
        }

        putenv('FAKETIME=' . $datetime); //@phpstan-ignore theCodingMachineSafe.function
        $this->faketime_set = true;
    }

    /**
     * Restore the real clock. Safe to call even if no fake time was set.
     */
    protected function restoreRealTime(): void
    {
        if ($this->faketime_set) {
            putenv('FAKETIME'); //unset //@phpstan-ignore theCodingMachineSafe.function
            $this->faketime_set = false;
        }
    }

    /**
     * Check whether libfaketime is actually able to alter the clock in the
     * current process. Result is memoized for the whole run.
     */
    protected static function isFakeTimeAvailable(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }

        $previous = getenv('FAKETIME');
        putenv('FAKETIME=2000-01-02 12:00:00'); //@phpstan-ignore theCodingMachineSafe.function
        $available = (new DateTime())->format('Y-m-d') === '2000-01-02';
        //restore any previous value (or unset)
        if ($previous === false) {
            putenv('FAKETIME'); //@phpstan-ignore theCodingMachineSafe.function
        } else {
            putenv('FAKETIME=' . $previous); //@phpstan-ignore theCodingMachineSafe.function
        }

        return $available;
    }
}
