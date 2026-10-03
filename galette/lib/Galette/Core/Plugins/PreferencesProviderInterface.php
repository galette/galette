<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

/**
 * Preferences provider interface
 *
 * A plugin implementing this stores its settings in Galette preferences rather
 * than in a table of its own: they get a type, validation, the usual
 * `$preferences->name` access, and a row on the advanced configuration page.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface PreferencesProviderInterface
{
    /**
     * Get the preferences the plugin declares
     *
     * Keys must be prefixed with `pref_<plugin route>_`, values are
     * `PreferencesSchema` entries: a known `type`, a scalar `default`, and
     * optionally `min`, `max`, `error`, `sensitive`, `acl`. Anything else is
     * dropped and reported.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPreferences(): array;
}
