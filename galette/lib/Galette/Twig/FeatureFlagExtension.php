<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Twig;

use Galette\Core\FeatureFlagManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig Feature Flag extension for Galette
 *
 * Provides functions to check feature flags in templates
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class FeatureFlagExtension extends AbstractExtension
{
    /**
     * Constructor
     *
     * @param FeatureFlagManager $featureFlags Feature flag manager instance
     */
    public function __construct(private readonly FeatureFlagManager $featureFlags)
    {
    }

    /**
     * Get functions
     *
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_feature_enabled', $this->isFeatureEnabled(...)),
            new TwigFunction('get_feature_flags', $this->getFeatureFlags(...)),
        ];
    }

    /**
     * Check if a feature flag is enabled
     *
     * Usage in templates:
     * {% if is_feature_enabled('acls') %}
     *   <!-- New ACL feature content -->
     * {% endif %}
     *
     * @param string $flag Feature flag name
     *
     * @return bool True if the feature is enabled
     */
    public function isFeatureEnabled(string $flag): bool
    {
        return $this->featureFlags->isEnabled($flag);
    }

    /**
     * Get all declared feature flags with their status
     *
     * Usage in templates:
     * {% set flags = get_feature_flags() %}
     * {% for flag, enabled in flags %}
     *   {{ flag }}: {{ enabled ? 'ON' : 'OFF' }}
     * {% endfor %}
     *
     * @return array<string, bool> Associative array of flag names => enabled status
     */
    public function getFeatureFlags(): array
    {
        return $this->featureFlags->getAllFlagsWithStatus();
    }
}
