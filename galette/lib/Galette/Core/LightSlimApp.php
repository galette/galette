<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Psr\Container\ContainerInterface;

/**
 * Light Slim application
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @template TContainerInterface of (ContainerInterface|null)
 * @extends SlimApp<TContainerInterface>
 */
class LightSlimApp extends SlimApp
{
    /**
     * Create a new Slim application
     */
    public function __construct(
        Plugins $plugins,
        string $mode = 'NEED_UPDATE'
    ) {
        parent::__construct($plugins, $mode);
    }

    /**
     * Get container definitions
     *
     * @return array{"galette": array{"mode": string}, "mode": string, "galette.mode": string, "templates.path": string, "settings.displayErrorDetails": bool, "settings.addContentLengthHeader": bool}
     */
    protected function getContainerDefinitions(): array
    {
        return
            parent::getContainerDefinitions()
            + [
                'templates.path'                    => GALETTE_ROOT . GALETTE_THEME,
                'settings.displayErrorDetails'      => Galette::isDebugEnabled(),
                'settings.addContentLengthHeader'   => false
            ];
    }
}
