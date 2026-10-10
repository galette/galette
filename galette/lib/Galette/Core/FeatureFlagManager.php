<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Analog\Analog;
use Throwable;

/**
 * Feature Flag Manager
 *
 * Manages feature flags for experimental/development features.
 *
 * Each registered flag has a stage:
 * - dev: unfinished work. Only enabled in debug mode, when declared in
 *   GALETTE_FEATURE_FLAGS (behavior.inc.php) or a GALETTE_FEATURE_<FLAG>
 *   environment variable.
 * - preview: finished but not mature yet. Enabled in any mode, either from
 *   the same declarations or by the super administrator from the advanced
 *   configuration page, which stores it in the pref_feature_flags preference.
 *
 * Declaring GALETTE_FEATURE_FLAGS takes precedence over what is stored, like
 * any other constant superseding a preference.
 *
 * The manager also keeps track of the flags accessed at runtime when
 * isEnabled() is called.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @phpstan-type FlagId string
 * @phpstan-type Flag array{
 *     description: string,
 *     requires?: array<FlagId>,
 *     stage?: string,
 *     label?: \Closure(): string,
 *     risk?: \Closure(): string
 * }
 */
class FeatureFlagManager
{
    /** Unfinished feature, debug mode only */
    public const string STAGE_DEV = 'dev';
    /** Finished feature the super administrator may turn on */
    public const string STAGE_PREVIEW = 'preview';

    /** Declared by the GALETTE_FEATURE_FLAGS constant */
    public const string SOURCE_CONSTANT = 'constant';
    /** Declared by a GALETTE_FEATURE_<FLAG> environment variable */
    public const string SOURCE_ENV = 'env';
    /** Turned on from the advanced configuration page or the console */
    public const string SOURCE_DATABASE = 'database';

    /** Refused: GALETTE_FEATURE_FLAGS is declared */
    public const int ERR_LOCKED = 1;
    /** Refused: the flag is not a preview feature */
    public const int ERR_NOT_PREVIEW = 2;
    /** Refused: the flag requires a feature still in development */
    public const int ERR_DEV_DEPENDENCY = 3;

    /** @var array<string, string> Declared flags => where they are declared */
    private array $declaredFlags = [];

    /** @var array<string, Flag> Registry of known feature flags with descriptions */
    private array $registryFlags = [];

    /** @var array<string> List of flags accessed during runtime */
    private array $accessedFlags = [];

    /** @var array<string> Preview flags stored as turned on */
    private array $storedFlags = [];

    /**
     * Constructor
     *
     * Loads feature flags
     *
     * @param ?Preferences $preferences Preferences holding the stored flags;
     *                                  the global instance when omitted
     */
    public function __construct(private readonly ?Preferences $preferences = null)
    {
        $this->load();
    }

    /**
     * Loads feature flags
     */
    public function load(): void
    {
        $this->registryFlags = [];
        $this->declaredFlags = [];
        $this->storedFlags = [];
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
        $feature_flags_registry = $this->getConfiguration();
        // Normalize keys to lowercase and preserve structure
        foreach ($feature_flags_registry as $flag => $entry) {
            if (is_string($entry)) {
                $entry = ['description' => $entry];
            }
            $this->registryFlags[strtolower((string)$flag)] = $entry;
        }
    }

    /**
     * Get configuration
     *
     * @return array<string, string|Flag>
     */
    protected function getConfiguration(): array
    {
        $registryFile = GALETTE_ROOT . 'includes/sys_config/feature_flags.inc.php';
        if (!file_exists($registryFile)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The feature flags registry file "%s" is missing. Please create it based on the template and add your flags.',
                    $registryFile
                )
            );
        }

        return include $registryFile;
    }

    /**
     * Load feature flags from configuration, environment and database
     */
    private function loadFeatureFlags(): void
    {
        foreach ($this->getDeclarations() as $flag) {
            $this->declaredFlags[strtolower($flag)] = self::SOURCE_CONSTANT;
        }

        // Also check individual environment variables (GALETTE_FEATURE_<FLAG>=1)
        $envFlags = array_filter($_ENV, fn(string $key): bool => str_starts_with($key, 'GALETTE_FEATURE_'), ARRAY_FILTER_USE_KEY);

        foreach ($envFlags as $key => $value) {
            if ($value === '1' || $value === 'true' || $value === 'on') {
                $flagName = strtolower(str_replace('GALETTE_FEATURE_', '', $key));
                $this->declaredFlags[$flagName] ??= self::SOURCE_ENV;
            }
        }

        //the constant wins over what is stored, as it does for preferences
        if ($this->isLockedByConstant()) {
            return;
        }

        foreach ($this->getStoredDeclarations() as $flag) {
            if ($this->getStage($flag) !== self::STAGE_PREVIEW) {
                //the flag went back to development, or left the registry
                Analog::log(
                    sprintf(
                        'Feature flag "%s" is stored as enabled but is not a preview feature; it is ignored.',
                        $flag
                    ),
                    Analog::WARNING
                );
                continue;
            }
            $this->storedFlags[] = $flag;
            $this->declaredFlags[$flag] ??= self::SOURCE_DATABASE;
        }
    }

    /**
     * Get features declarations
     *
     * @return FlagId[]
     */
    protected function getDeclarations(): array
    {
        // Load from GALETTE_FEATURE_FLAGS constant (array of flag names)
        // @phpstan-ignore function.alreadyNarrowedType
        if (defined('GALETTE_FEATURE_FLAGS') && is_array(GALETTE_FEATURE_FLAGS)) {
            return GALETTE_FEATURE_FLAGS;
        }
        return [];
    }

    /**
     * Get the flags the super administrator turned on
     *
     * Read through the preferences when there are some. Some callers run
     * before or outside of them (the session's Login): the row is then read
     * from database straight away, so a stored flag cannot silently read as
     * off there.
     *
     * @return FlagId[]
     */
    protected function getStoredDeclarations(): array
    {
        global $preferences, $zdb;

        $prefs = $this->preferences ?? ($preferences instanceof Preferences ? $preferences : null);
        if ($prefs !== null) {
            return $prefs->getFeatureFlags();
        }

        if (!$zdb instanceof Db) {
            return [];
        }

        try {
            $select = $zdb->select(Preferences::TABLE);
            $select->columns(['val_pref'])->where(['nom_pref' => 'pref_feature_flags'])->limit(1);
            $results = $zdb->execute($select);
            if ($results->count() === 0) {
                return [];
            }
            $stored = (string)$results->current()->val_pref;
        } catch (Throwable $e) {
            Logs::exception($e, 'Cannot read stored feature flags', Analog::WARNING);
            return [];
        }

        return array_values(array_filter(array_map(
            fn(string $flag): string => strtolower(trim($flag)),
            explode(',', $stored)
        )));
    }

    /**
     * Is GALETTE_FEATURE_FLAGS declared?
     *
     * Stored flags are then ignored, and cannot be changed from the interface.
     */
    public function isLockedByConstant(): bool
    {
        return defined('GALETTE_FEATURE_FLAGS');
    }

    /**
     * Get the preview flags stored as turned on
     *
     * @return array<string>
     */
    public function getStoredFlags(): array
    {
        return $this->storedFlags;
    }

    /**
     * Compute the stored flags once a preview feature is turned on
     *
     * The preview features it requires are turned on along with it. Nothing
     * is written: the caller stores the result.
     *
     * @param string $flag Feature flag name
     *
     * @return array{flags: array<string>, added: array<string>}
     *
     * @throws \DomainException with one of the ERR_* codes when refused
     */
    public function computeTurnOn(string $flag): array
    {
        $flag = strtolower($flag);
        $this->checkChangeable($flag);

        $added = [];
        $pending = [$flag];
        while ($pending !== []) {
            $current = array_shift($pending);
            if (in_array($current, $added, strict: true)) {
                continue;
            }
            if ($this->getStage($current) !== self::STAGE_PREVIEW) {
                throw new \DomainException(
                    sprintf('Feature flag "%s" requires "%s", which is still in development.', $flag, $current),
                    self::ERR_DEV_DEPENDENCY
                );
            }
            $added[] = $current;
            $pending = array_merge($pending, $this->getDependencies($current));
        }

        $added = array_values(array_diff($added, $this->storedFlags));
        return [
            'flags' => array_values(array_unique(array_merge($this->storedFlags, $added))),
            'added' => $added,
        ];
    }

    /**
     * Compute the stored flags once a preview feature is turned off
     *
     * The stored features requiring it are turned off along with it: they
     * would not work without it anyway. Nothing is written: the caller stores
     * the result.
     *
     * @param string $flag Feature flag name
     *
     * @return array{flags: array<string>, removed: array<string>}
     *
     * @throws \DomainException with ERR_LOCKED when refused
     */
    public function computeTurnOff(string $flag): array
    {
        $flag = strtolower($flag);
        if ($this->isLockedByConstant()) {
            throw new \DomainException(
                'GALETTE_FEATURE_FLAGS is declared in behavior.inc.php and takes precedence.',
                self::ERR_LOCKED
            );
        }

        $removed = [];
        $pending = [$flag];
        while ($pending !== []) {
            $current = array_shift($pending);
            if (in_array($current, $removed, strict: true)) {
                continue;
            }
            $removed[] = $current;
            foreach ($this->storedFlags as $stored) {
                if (in_array($current, $this->getDependencies($stored), strict: true)) {
                    $pending[] = $stored;
                }
            }
        }

        $removed = array_values(array_intersect($removed, $this->storedFlags));
        return [
            'flags' => array_values(array_diff($this->storedFlags, $removed)),
            'removed' => $removed,
        ];
    }

    /**
     * Can that flag be turned on or off from the interface?
     *
     * @param string $flag Feature flag name
     *
     * @throws \DomainException with one of the ERR_* codes when it cannot
     */
    private function checkChangeable(string $flag): void
    {
        if ($this->isLockedByConstant()) {
            throw new \DomainException(
                'GALETTE_FEATURE_FLAGS is declared in behavior.inc.php and takes precedence.',
                self::ERR_LOCKED
            );
        }

        if ($this->getStage($flag) !== self::STAGE_PREVIEW) {
            throw new \DomainException(
                sprintf('Feature flag "%s" is not a preview feature.', $flag),
                self::ERR_NOT_PREVIEW
            );
        }
    }

    /**
     * Check if a feature flag is enabled
     *
     * A feature is only enabled if:
     * 1. The flag is declared (configuration, environment, or database for
     *    a preview feature)
     * 2. Debug mode is enabled (GALETTE_DEBUG === true), unless the flag is
     *    a preview feature
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
        if (!in_array($flag, $this->accessedFlags, strict: true)) {
            $this->accessedFlags[] = $flag;
        }

        // Warn if flag is used but not in registry
        if (!isset($this->registryFlags[$flag]) && $this->isDebugMode()) {
            Analog::log(
                sprintf(
                    'Feature flag "%s" is used in code but not registered in feature_flags.inc.php. '
                    . 'Please add it to the registry.',
                    $flag
                ),
                Analog::WARNING
            );
        }

        if (!$this->isBasicEnabled($flag)) {
            // Log warning if someone tries to use a development flag in production
            if (isset($this->declaredFlags[$flag]) && !$this->isDebugMode()) {
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

        if (!$this->areDependenciesSatisfied($flag)) {
            $missing = $this->getMissingDependencies($flag);
            Analog::log(
                sprintf(
                    'Feature flag "%s" is enabled but has unsatisfied dependencies: %s',
                    $flag,
                    implode(', ', $missing)
                ),
                Analog::WARNING
            );
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
        return array_keys($this->declaredFlags);
    }

    /**
     * Where a flag is declared
     *
     * @param string $flag Feature flag name
     *
     * @return ?string One of the SOURCE_* constants, null when not declared
     */
    public function getSource(string $flag): ?string
    {
        return $this->declaredFlags[strtolower($flag)] ?? null;
    }

    /**
     * Get all registered feature flags with descriptions
     *
     * @return array<string, Flag> Associative array of flag names => descriptions
     */
    public function getRegistryFlags(): array
    {
        return $this->registryFlags;
    }

    /**
     * Get registered flags the super administrator may turn on
     *
     * @return array<string, Flag>
     */
    public function getPreviewFlags(): array
    {
        return array_filter(
            $this->registryFlags,
            fn(string $flag): bool => $this->getStage($flag) === self::STAGE_PREVIEW,
            ARRAY_FILTER_USE_KEY
        );
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
        return $entry['description'];
    }

    /**
     * Get stage of a feature flag
     *
     * @param string $flag Feature flag name
     *
     * @return ?string One of the STAGE_* constants, null if not in registry
     */
    public function getStage(string $flag): ?string
    {
        $flag = strtolower($flag);

        if (!isset($this->registryFlags[$flag])) {
            return null;
        }

        $stage = $this->registryFlags[$flag]['stage'] ?? self::STAGE_DEV;
        return $stage === self::STAGE_PREVIEW ? self::STAGE_PREVIEW : self::STAGE_DEV;
    }

    /**
     * Get the name of a feature flag, as the interface shows it
     *
     * Translated strings are wrapped in closures in the registry: it is read
     * every time a manager is built, and translating there would cost a
     * lookup for each string on each build.
     *
     * @param string $flag Feature flag name
     *
     * @return ?string Label, description when none is given, null if not in registry
     */
    public function getLabel(string $flag): ?string
    {
        $label = $this->registryFlags[strtolower($flag)]['label'] ?? null;
        return $label !== null ? $label() : $this->getDescription($flag);
    }

    /**
     * Get what turning a feature flag on puts at stake
     *
     * @param string $flag Feature flag name
     *
     * @return ?string Risk, null if none is documented
     */
    public function getRisk(string $flag): ?string
    {
        $risk = $this->registryFlags[strtolower($flag)]['risk'] ?? null;
        return $risk !== null ? $risk() : null;
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
        return array_map(strtolower(...), $entry['requires'] ?? []);
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

        if ($dependencies === []) {
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
        $flag = strtolower($flag);

        if (!isset($this->declaredFlags[$flag])) {
            return false;
        }

        //a preview feature works the same with or without debug mode
        if ($this->getStage($flag) === self::STAGE_PREVIEW) {
            return true;
        }

        return $this->isDebugMode();
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
     * - declared: Whether the flag is declared (configuration, environment or database)
     * - accessed: Whether the flag was checked via isEnabled()
     * - description: Description from registry
     * - requires: List of dependencies
     * - dependencies_satisfied: Whether all dependencies are met
     * - stage: dev or preview, null if not in registry
     * - source: where the flag is declared, null if it is not
     *
     * @return array<string, array{enabled: bool, declared: bool, accessed: bool, description: string, requires: array<string>, dependencies_satisfied: bool, stage: ?string, source: ?string}> Flag status details
     */
    public function getAllFlagsWithStatus(): array
    {
        $result = [];

        // Start with all registered flags
        foreach (array_keys($this->registryFlags) as $flag) {
            $dependenciesSatisfied = $this->areDependenciesSatisfied($flag);

            $result[$flag] = [
                'enabled' => $this->isBasicEnabled($flag) && $dependenciesSatisfied,
                'declared' => isset($this->declaredFlags[$flag]),
                'accessed' => in_array($flag, $this->accessedFlags, strict: true),
                'description' => $this->getDescription($flag) ?? '',
                'requires' => $this->getDependencies($flag),
                'dependencies_satisfied' => $dependenciesSatisfied,
                'stage' => $this->getStage($flag),
                'source' => $this->getSource($flag),
            ];
        }

        // Add accessed flags that are not in registry (with warning)
        foreach ($this->accessedFlags as $flag) {
            $result[$flag] ??= [
                'enabled' => $this->isBasicEnabled($flag),
                'declared' => isset($this->declaredFlags[$flag]),
                'accessed' => true,
                'description' => '⚠️  NOT IN REGISTRY - Please add to feature_flags.inc.php',
                'requires' => [],
                'dependencies_satisfied' => true,
                'stage' => null,
                'source' => $this->getSource($flag),
            ];
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
