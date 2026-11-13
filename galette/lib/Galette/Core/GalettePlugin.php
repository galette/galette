<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;

/**
 * Galette plugins
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * Designed to be extended by plugins; which can implement the following interfaces:
 * - MenuProviderInterface: if the plugin provides extra menu entries (public or private menus)
 * - DashboardProviderInterface: if the plugin provides extra dashboard entries
 * - MemberActionProviderInterface: if the plugin provides extra member actions
 * - NewsProviderInterface: if the plugin provides news to be displayed in the dashboard
 * - PreferencesProviderInterface: if the plugin stores settings in Galette preferences
 * - ContentSecurityPolicyProviderInterface: if the plugin pages need external origins (frames, APIs, ...)
 *
 * Note: a plugin can implement one or more of these interfaces, but it is not mandatory to implement all of them.
 */
abstract class GalettePlugin implements Plugins\InstallableInterface
{
    /**
     * Get plugins public menus
     *
     * @return array<int, string|array<string,mixed>>
     */
    public function getPublicMenuItems(): array
    {
        global $preferences, $login;

        //a plugin declaring its pages gives each entry a visibility of its
        //own; the others all follow the default one
        $declares = $this instanceof Plugins\PublicPagesProviderInterface;
        if (!$declares && !$preferences->showPublicPage($login, 'pref_publicpages_visibility_generic')) {
            return [];
        }

        $menus = [];
        if ($this instanceof Plugins\MenuProviderInterface) {
            $menus = $this->getPublicMenus();
        }

        return $declares ? $this->filterPublicMenuItems($menus, $preferences, $login) : $menus;
    }

    /**
     * Keep the public menu entries the current user may see
     *
     * An entry with children is kept as long as one of them is.
     *
     * @param array<int|string, string|array<string,mixed>> $items       Menu entries
     * @param Preferences                                   $preferences Preferences instance
     * @param Authentication                                $login       Authentication instance
     *
     * @return array<int|string, string|array<string,mixed>>
     */
    private function filterPublicMenuItems(array $items, Preferences $preferences, Authentication $login): array
    {
        $visible = [];
        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                $visible[$key] = $item;
                continue;
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->filterPublicMenuItems($item['children'], $preferences, $login);
                if ($item['children'] === []) {
                    continue;
                }
            } elseif (
                !$preferences->showPluginPublicPage($login, (string)($item['route']['name'] ?? ''))
            ) {
                continue;
            }

            $visible[$key] = $item;
        }

        return array_is_list($items) ? array_values($visible) : $visible;
    }

    /**
     * Is the plugin fully installed (including database, extra configuration, etc.)?
     */
    public function isInstalled(): bool
    {
        Analog::log(
            static::class . '::isInstalled() is deprecated, please implement InstallableInterface',
            Analog::WARNING
        );
        return true;
    }

    /**
     * Database version of an installation that predates plugins versions tracking
     *
     * Called when plugin tables exist, but no version has been stored for it
     * yet: this is the case coming from Galette 1.2. Return the version
     * existing tables are at so that pending update scripts are run, or null
     * if they are up to date with the declared version.
     */
    public function getLegacyDbVersion(): ?float
    {
        return null;
    }
}
