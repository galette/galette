<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Throwable;

use function Safe\opcache_get_status;

/**
 * OPcache status, as seen by the running PHP process
 *
 * OPcache memory is shared by every process of a PHP-FPM pool, or of the web
 * server: what is read and reset here is what the web server uses, but the
 * command line has an OPcache of its own, and a reset also applies to any other
 * application sharing the pool.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class Opcache
{
    /**
     * Is OPcache loaded and enabled for the running process?
     */
    public function isEnabled(): bool
    {
        return $this->getRawStatus() !== null;
    }

    /**
     * Memory OPcache uses, and may use, or null when it cannot be used
     *
     * @return ?array{memory_used: int, memory_total: int}
     */
    public function getStatus(): ?array
    {
        $status = $this->getRawStatus();
        if ($status === null) {
            return null;
        }

        return [
            'memory_used'  => (int)($status['memory_usage']['used_memory'] ?? 0),
            'memory_total' => (int)($this->getDirectives()['opcache.memory_consumption'] ?? 0),
        ];
    }

    /**
     * Reset OPcache
     *
     * Scripts are compiled again on their next use.
     */
    public function reset(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $reset = opcache_reset();
        Analog::log(
            $reset ? 'OPcache has been reset.' : 'OPcache could not be reset.',
            $reset ? Analog::INFO : Analog::WARNING
        );
        return $reset;
    }

    /**
     * OPcache status, or null when it is not loaded, disabled or restricted
     *
     * @return ?array<string, mixed>
     */
    private function getRawStatus(): ?array
    {
        if (!function_exists('opcache_get_status') || !function_exists('opcache_reset')) {
            return null;
        }

        try {
            //restrict_api raises a warning on top of failing
            $status = @opcache_get_status(include_scripts: false);
        } catch (Throwable) {
            // no log: OPcache disabled or restricted is a supported setup
            return null;
        }

        if (!($status['opcache_enabled'] ?? false)) {
            return null;
        }
        return $status;
    }

    /**
     * OPcache directives
     *
     * @return array<string, mixed>
     */
    private function getDirectives(): array
    {
        if (!function_exists('opcache_get_configuration')) {
            return [];
        }
        $configuration = @opcache_get_configuration();
        return is_array($configuration) ? $configuration['directives'] : [];
    }
}
