<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
     * Do not forget the "use DI\Attribute\Inject;" instruction
     * @var array<string, mixed>
     */
    protected array $module_info;

    /**
     * Get plugin module ID
     */
    protected function getModuleId(): string
    {
        return $this->module_info['module_id'];
    }

    /**
     * Get plugin module route namespace
     */
    protected function getModuleRoute(): string
    {
        return $this->module_info['module']['route'];
    }

    /**
     * Get plugin template name for Twig
     *
     * @param string $name Template name
     */
    protected function getTemplate(string $name): string
    {
        return sprintf('@%s/%s.html.twig', $this->plugins->getClassName($this->getModuleId()), $name);
    }

    /**
     * Get filter name in session
     *
     * @param string                   $filter_name Filter name
     * @param array<string,mixed>|null $args        Arguments
     */
    public function getFilterName(string $filter_name, ?array $args = null): string
    {
        if (!isset($args['prefix'])) {
            $args['prefix'] = 'plugin_' . $this->module_info['module']['route'];
        }

        return parent::getFilterName($filter_name, $args);
    }
}
