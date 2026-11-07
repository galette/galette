<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Navigation;

use Analog\Analog;
use Closure;
use Galette\Core\Logs;
use Galette\Util\Release;

/**
 * New Galette and plugins releases
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ReleaseNotices
{
    /**
     * Constructor
     *
     * @param Closure(): Release $release Releases checker provider; it may reach the network
     *                                    when built, it is only built when needed
     */
    public function __construct(
        private readonly Closure $release
    ) {
    }

    /**
     * Get Galette new release
     *
     * @return array<string, bool|string|null>
     */
    public function getNewRelease(): array
    {
        if (defined('GALETTE_TESTS') || getenv('GALETTE_TESTS')) {
            return [
                'new' => false,
                'version' => GALETTE_VERSION
            ];
        }

        $release = $this->getRelease();
        return [
            'new' => $release->checkNewRelease(),
            'version' => $release->getLatestRelease()
        ];
    }

    /**
     * Get plugins for which a new release is available
     *
     * @return array<string, array{name: string, current: string, latest: string, url: string}>
     */
    public function getPluginsUpdates(): array
    {
        if (defined('GALETTE_TESTS') || getenv('GALETTE_TESTS')) {
            return [];
        }

        try {
            return $this->getRelease()->getPluginsUpdates();
        } catch (\Throwable $e) {
            Logs::exception($e, 'Error looking for new plugins releases', Analog::ERROR);
            return [];
        }
    }

    /**
     * Get releases checker, shared with the rest of the request when possible
     */
    private function getRelease(): Release
    {
        return ($this->release)();
    }
}
