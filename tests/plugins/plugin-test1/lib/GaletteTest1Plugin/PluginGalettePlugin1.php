<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteTest1Plugin;

use Galette\Core\GalettePlugin;
use Galette\Core\PreferencesSchema;
use Galette\Core\Plugins\PreferencesProviderInterface;
use Galette\Core\Plugins\PublicPagesProviderInterface;

/**
 * This fixture also declares preferences and a public page, so that the
 * registration path is exercised by every test that loads plugins.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginGalettePlugin1 extends GalettePlugin implements PreferencesProviderInterface, PublicPagesProviderInterface
{
    /**
     * Get the preferences the plugin declares
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPreferences(): array
    {
        return [
            'pref_plugin1_label' => [
                'type' => PreferencesSchema::TYPE_STRING,
                'default' => 'plugin one',
            ],
            'pref_plugin1_count' => [
                'type' => PreferencesSchema::TYPE_INT,
                'default' => 3,
                'min' => 0,
                'max' => 10,
                'error' => PreferencesSchema::ERR_POSITIVE_NUMBER,
            ],
            'pref_plugin1_enabled' => [
                'type' => PreferencesSchema::TYPE_BOOL,
                'default' => false,
            ],
        ];
    }

    /**
     * Get the public pages the plugin declares
     *
     * @return array<string, array{routes: list<string>, default?: int}>
     */
    public function getPublicPages(): array
    {
        return [
            'page' => ['routes' => ['plugin1_public_page']],
        ];
    }

    /**
     * Get the label of a declared public page
     *
     * @param string $id Page identifier
     */
    public function getPublicPageLabel(string $id): string
    {
        return 'Plugin one page';
    }

    /**
     * Is the plugin fully installed (including database, extra configuration, etc.)?
     */
    public function isInstalled(): bool
    {
        return true;
    }
}
