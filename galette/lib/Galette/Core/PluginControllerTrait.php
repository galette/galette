<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Core;

use DI\Attribute\Inject;

/**
 * Plugin controllers trait
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait PluginControllerTrait
{
    /**
     * Something like:
     * #[Inject("Plugin Galette Name")]
     * @var array<string, mixed>
     */
    protected array $module_info;

    /**
     * Get plugin module ID
     *
     * @return string
     */
    protected function getModuleId(): string
    {
        return $this->module_info['module_id'];
    }

    /**
     * Get plugin module route namespace
     *
     * @return string
     */
    protected function getModuleRoute(): string
    {
        return $this->module_info['module']['route'];
    }

    /**
     * Get plugin template name for Twig
     *
     * @param string $name Template name
     *
     * @return string
     */
    protected function getTemplate(string $name): string
    {
        return sprintf('@%s/%s.html.twig', $this->plugins->getClassName($this->getModuleId()), $name);
    }
}
