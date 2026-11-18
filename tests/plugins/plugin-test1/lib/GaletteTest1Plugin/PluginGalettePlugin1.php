<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteTest1Plugin;

use DI\Attribute\Inject;
use Galette\Access\AccessControl;
use Galette\Access\Permission;
use Galette\Core\Authentication;
use Galette\Core\Db;
use Galette\Core\GalettePlugin;
use Galette\Core\Login;
use Galette\Core\PreferencesSchema;
use Galette\Core\Plugins\DynamicFormsProviderInterface;
use Galette\Core\Plugins\PermissionsProviderInterface;
use Galette\Core\Plugins\PreferencesProviderInterface;
use Galette\Core\Plugins\PublicPagesProviderInterface;
use Slim\Routing\RouteParser;

/**
 * This fixture also declares preferences, a public page, permissions and a
 * dynamic fields form, so that the registration path is exercised by every
 * test that loads plugins.
 * It gets access control injected, as plugins checking permissions do.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginGalettePlugin1 extends GalettePlugin implements
    PreferencesProviderInterface,
    PublicPagesProviderInterface,
    PermissionsProviderInterface,
    DynamicFormsProviderInterface
{
    #[Inject]
    protected AccessControl $accessControl;

    #[Inject]
    protected Db $zdb;

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
     * Get the permissions the plugin declares
     *
     * @return array<Permission>
     */
    public function getPermissions(): array
    {
        return [
            new Permission('plugin1:read', 'Display plugin one', Authentication::ACCESS_USER),
            new Permission(
                name: 'plugin1:manage',
                label: 'Manage plugin one',
                level: Authentication::ACCESS_STAFF,
                managers: true,
                owner: true
            ),
        ];
    }

    /**
     * Get the forms the plugin declares
     *
     * @return array<string, class-string>
     */
    public function getDynamicForms(): array
    {
        return ['plugin1' => Thing::class];
    }

    /**
     * Get the label of a declared form
     *
     * @param string $form Form name
     */
    public function getDynamicFormLabel(string $form): string
    {
        return 'Plugin one things';
    }

    /**
     * Get an object of a declared form, if the user can see it
     *
     * Things 1 and 2 exist; anyone logged in sees the first, only staff the second.
     *
     * @param string $form  Form name
     * @param int    $id    Object identifier
     * @param Login  $login Current user
     */
    public function getDynamicFormObject(string $form, int $id, Login $login): ?object
    {
        if (!$login->isLogged() || !in_array($id, [1, 2], strict: true) || ($id === 2 && $login->getAccessLevel() < Authentication::ACCESS_STAFF)) {
            return null;
        }
        return new Thing($this->zdb, $login, $id);
    }

    /**
     * Get the page to go back to when a file of a declared form cannot be sent
     *
     * @param string      $form        Form name
     * @param int         $id          Object identifier
     * @param RouteParser $routeparser Route parser
     */
    public function getDynamicFormReturnUrl(string $form, int $id, RouteParser $routeparser): string
    {
        return $routeparser->urlFor('plugin1_public_page');
    }

    /**
     * Is the plugin fully installed (including database, extra configuration, etc.)?
     */
    public function isInstalled(): bool
    {
        return true;
    }
}
