<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

/**
 * Content Security Policy provider interface
 *
 * A plugin implementing this declares the external origins its pages need:
 * a payment form embedded in a frame, an API called from the browser,
 * a checkout page a form redirects to...
 *
 * Scripts do not need it: any script carrying the nonce of the request
 * (`nonce="{{ csp_nonce() }}"`) runs, and so do the ones it loads.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface ContentSecurityPolicyProviderInterface
{
    /**
     * Get the sources the plugin adds to the Content Security Policy
     *
     * Keys are directives, one of `Galette\Core\Csp::PLUGIN_DIRECTIVES`;
     * values list their sources: `https://` or `wss://` origins, with an
     * optional wildcard on the first label and an optional path, or `data:`
     * and `blob:` (not for `script-src`). Keywords such as `'unsafe-inline'`
     * are not accepted.
     *
     * Sources are added to every page, whatever the plugin shows on it.
     * Invalid ones are logged and ignored.
     *
     * @return array<string, list<string>>
     */
    public function getCspSources(): array;
}
