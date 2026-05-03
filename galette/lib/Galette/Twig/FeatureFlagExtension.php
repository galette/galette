<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
 * @phpstan-import-type Flag from FeatureFlagManager
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
     * @return array<string, Flag> Associative array of flag names => flag status
     */
    public function getFeatureFlags(): array
    {
        return $this->featureFlags->getAllFlagsWithStatus();
    }
}
