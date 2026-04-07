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

namespace Galette\Core;

use Analog\Analog;

/**
 * Feature Flag Manager
 *
 * Manages feature flags for experimental/development features.
 * Features are only enabled in development mode AND if explicitly declared.
 *
 * Supports three types of flags:
 * - Registry flags: Defined in galette/includes/sys_config/feature_flags.inc.php
 * - Declared flags: Activated in config (GALETTE_FEATURE_FLAGS in behavior.inc.php)
 * - Accessed flags: Tracked at runtime when isEnabled() is called
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class FeatureFlagManager
{
    /** @var array<string> List of declared/enabled feature flags */
    private array $declaredFlags = [];

    /** @var array<string, string|array{description: string, requires?: array<string>}> Registry of known feature flags with descriptions */
    private array $registryFlags = [];

    /** @var array<string> List of flags accessed during runtime */
    private array $accessedFlags = [];

    /**
     * Constructor
     *
     * Loads feature flags from configuration constants or environment variables
     */
    public function __construct()
    {
        $this->loadRegistryFlags();
        $this->loadFeatureFlags();
    }

    /**
     * Load feature flags registry from system config
     *
     * The registry contains all known feature flags in the codebase
     */
    private function loadRegistryFlags(): void
    {
        $registryFile = GALETTE_ROOT . 'includes/sys_config/feature_flags.inc.php';

        if (file_exists($registryFile)) {
            $feature_flags_registry = include $registryFile;
            if (is_array($feature_flags_registry)) {
                // Normalize keys to lowercase and preserve structure
                foreach ($feature_flags_registry as $flag => $entry) {
                    $this->registryFlags[strtolower($flag)] = $entry;
                }
            }
        }
    }

    /**
     * Load feature flags from configuration
     */
    private function loadFeatureFlags(): void
    {
        // Load from GALETTE_FEATURE_FLAGS constant (array of flag names)
        /** @phpstan-ignore-next-line */
        if (defined('GALETTE_FEATURE_FLAGS') && is_array(GALETTE_FEATURE_FLAGS)) {
            /** @phpstan-ignore-next-line */
            $this->declaredFlags = array_map('strtolower', GALETTE_FEATURE_FLAGS);
        }

        // Also check individual environment variables (GALETTE_FEATURE_<FLAG>=1)
        $envFlags = array_filter($_ENV, function (string $key): bool {
            return str_starts_with($key, 'GALETTE_FEATURE_');
        }, ARRAY_FILTER_USE_KEY);

        foreach ($envFlags as $key => $value) {
            if ($value === '1' || $value === 'true' || $value === 'on') {
                $flagName = strtolower(str_replace('GALETTE_FEATURE_', '', $key));
                if (!in_array($flagName, $this->declaredFlags, true)) {
                    $this->declaredFlags[] = $flagName;
                }
            }
        }
    }

    /**
     * Check if a feature flag is enabled
     *
     * A feature is only enabled if:
     * 1. Debug mode is enabled (GALETTE_DEBUG === true)
     * 2. The flag is explicitly declared in configuration
     * 3. All dependencies are satisfied
     *
     * This method also tracks which flags are accessed at runtime.
     *
     * @param string $flag Feature flag name (case-insensitive)
     *
     * @return bool True if the feature is enabled
     */
    public function isEnabled(string $flag): bool
    {
        $flag = strtolower($flag);

        // Track this flag as accessed
        if (!in_array($flag, $this->accessedFlags, true)) {
            $this->accessedFlags[] = $flag;
        }

        // Warn if flag is used but not in registry
        if (!isset($this->registryFlags[$flag]) && Galette::isDebugEnabled()) {
            Analog::log(
                sprintf(
                    'Feature flag "%s" is used in code but not registered in feature_flags.inc.php. '
                    . 'Please add it to the registry.',
                    $flag
                ),
                Analog::WARNING
            );
        }

        // CRITICAL: Features are ONLY available in debug/development mode
        if (!Galette::isDebugEnabled()) {
            // Log warning if someone tries to use a feature flag in production
            if (in_array($flag, $this->declaredFlags, true)) {
                Analog::log(
                    sprintf(
                        'Feature flag "%s" is declared but cannot be enabled in production mode. '
                        . 'Set GALETTE_DEBUG=true to enable feature flags.',
                        $flag
                    ),
                    Analog::WARNING
                );
            }
            return false;
        }

        // Check if the flag is declared
        if (!in_array($flag, $this->declaredFlags, true)) {
            return false;
        }

        // NEW: Check dependencies
        if (!$this->areDependenciesSatisfied($flag)) {
            if (Galette::isDebugEnabled()) {
                $missing = $this->getMissingDependencies($flag);
                Analog::log(
                    sprintf(
                        'Feature flag "%s" is enabled but has unsatisfied dependencies: %s',
                        $flag,
                        implode(', ', $missing)
                    ),
                    Analog::WARNING
                );
            }
            return false;
        }

        return true;
    }

    /**
     * Get all declared feature flags (enabled flags)
     *
     * @return array<string> List of declared feature flag names
     */
    public function getDeclaredFlags(): array
    {
        return $this->declaredFlags;
    }

    /**
     * Get all registered feature flags with descriptions
     *
     * @return array<string, string> Associative array of flag names => descriptions
     */
    public function getRegistryFlags(): array
    {
        return $this->registryFlags;
    }

    /**
     * Get flags that were accessed at runtime
     *
     * @return array<string> List of accessed feature flag names
     */
    public function getAccessedFlags(): array
    {
        return $this->accessedFlags;
    }

    /**
     * Get description of a feature flag
     *
     * @param string $flag Feature flag name
     *
     * @return string|null Description or null if not in registry
     */
    public function getDescription(string $flag): ?string
    {
        $flag = strtolower($flag);

        if (!isset($this->registryFlags[$flag])) {
            return null;
        }

        $entry = $this->registryFlags[$flag];

        // Handle both formats: string or array with description
        if (is_string($entry)) {
            return $entry;
        } elseif (is_array($entry) && isset($entry['description'])) {
            return $entry['description'];
        }

        return null;
    }

    /**
     * Get dependencies of a feature flag
     *
     * @param string $flag Feature flag name
     *
     * @return array<string> List of required flags
     */
    public function getDependencies(string $flag): array
    {
        $flag = strtolower($flag);

        if (!isset($this->registryFlags[$flag])) {
            return [];
        }

        $entry = $this->registryFlags[$flag];

        // Handle both formats: string (no dependencies) or array (may have dependencies)
        if (is_array($entry) && isset($entry['requires'])) {
            return array_map('strtolower', $entry['requires']);
        }

        return [];
    }

    /**
     * Check if all dependencies of a flag are satisfied
     *
     * @param string $flag Feature flag name
     *
     * @return bool True if all dependencies are enabled
     */
    public function areDependenciesSatisfied(string $flag): bool
    {
        $dependencies = $this->getDependencies($flag);

        if (empty($dependencies)) {
            return true; // No dependencies = always satisfied
        }

        // Check each dependency recursively
        foreach ($dependencies as $dependency) {
            // Use basic check without triggering full isEnabled (avoid infinite recursion)
            if (!$this->isBasicEnabled($dependency)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Basic enable check without dependency validation (internal use)
     *
     * @param string $flag Feature flag name
     *
     * @return bool True if the flag is basically enabled
     */
    private function isBasicEnabled(string $flag): bool
    {
        if (!Galette::isDebugEnabled()) {
            return false;
        }

        return in_array(strtolower($flag), $this->declaredFlags, true);
    }

    /**
     * Get missing dependencies for a flag
     *
     * @param string $flag Feature flag name
     *
     * @return array<string> List of missing/disabled dependencies
     */
    public function getMissingDependencies(string $flag): array
    {
        $dependencies = $this->getDependencies($flag);
        $missing = [];

        foreach ($dependencies as $dependency) {
            if (!$this->isBasicEnabled($dependency)) {
                $missing[] = $dependency;
            }
        }

        return $missing;
    }

    /**
     * Get all registered flags with their status and metadata
     *
     * Returns detailed information about all registered flags:
     * - enabled: Whether the flag is currently enabled
     * - declared: Whether the flag is in GALETTE_FEATURE_FLAGS
     * - accessed: Whether the flag was checked via isEnabled()
     * - description: Description from registry
     * - requires: List of dependencies
     * - dependencies_satisfied: Whether all dependencies are met
     *
     * @return array<string, array{enabled: bool, declared: bool, accessed: bool, description: string, requires: array<string>, dependencies_satisfied: bool}> Flag status details
     */
    public function getAllFlagsWithStatus(): array
    {
        $result = [];

        // Start with all registered flags
        foreach ($this->registryFlags as $flag => $entry) {
            $isDeclared = in_array($flag, $this->declaredFlags, true);
            $isAccessed = in_array($flag, $this->accessedFlags, true);
            $dependencies = $this->getDependencies($flag);
            $dependenciesSatisfied = $this->areDependenciesSatisfied($flag);
            $description = $this->getDescription($flag);

            $result[$flag] = [
                'enabled' => $isDeclared && $this->isDebugMode() && $dependenciesSatisfied,
                'declared' => $isDeclared,
                'accessed' => $isAccessed,
                'description' => $description ?? '',
                'requires' => $dependencies,
                'dependencies_satisfied' => $dependenciesSatisfied,
            ];
        }

        // Add accessed flags that are not in registry (with warning)
        foreach ($this->accessedFlags as $flag) {
            if (!isset($result[$flag])) {
                $isDeclared = in_array($flag, $this->declaredFlags, true);
                $result[$flag] = [
                    'enabled' => $isDeclared && $this->isDebugMode(),
                    'declared' => $isDeclared,
                    'accessed' => true,
                    'description' => '⚠️  NOT IN REGISTRY - Please add to feature_flags.inc.php',
                    'requires' => [],
                    'dependencies_satisfied' => true,
                ];
            }
        }

        return $result;
    }

    /**
     * Check if debug mode is currently enabled
     *
     * @return bool True if debug mode is enabled
     */
    public function isDebugMode(): bool
    {
        return Galette::isDebugEnabled();
    }
}
