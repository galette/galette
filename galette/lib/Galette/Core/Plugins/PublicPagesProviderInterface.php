<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

/**
 * Public pages provider interface
 *
 * A plugin implementing this gives each of its public pages a visibility of
 * its own, set on the core settings page next to the core public pages, rather
 * than having them all follow the default one.
 *
 * Declaring a page does not protect it: its routes still need the
 * `Galette\Middleware\PublicPages` middleware, which is what reads the
 * visibility and turns visitors away.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface PublicPagesProviderInterface
{
    /**
     * Get the public pages the plugin declares
     *
     * Keys are page identifiers, made of lowercase letters, digits and
     * underscores; they name the stored visibility, so they must not change
     * once released. `routes` lists the names of the routes making the page -
     * a list and the form filtering it, for instance - and `default`, a
     * `Galette\Enums\PublicPageVisibility` value, is what an instance starts
     * with; pages inherit the default visibility unless told otherwise.
     *
     * This is read while modules are being loaded, before translations are
     * available: labels come from `getPublicPageLabel()`.
     *
     * @return array<string, array{routes: list<string>, default?: int}>
     */
    public function getPublicPages(): array;

    /**
     * Get the label of a declared public page, as the settings page shows it
     *
     * @param string $id Page identifier, one of the keys of `getPublicPages()`
     */
    public function getPublicPageLabel(string $id): string;
}
