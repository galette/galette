<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Composer\Autoload\ClassLoader;
use DI\Attribute\Inject;
use Exception;
use Analog\Analog;
use Galette\Exception\MissingPluginException;
use League\Event\EventDispatcher;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Safe\Exceptions\DirException;
use Safe\Exceptions\FilesystemException;
use Throwable;

use function Safe\dir;
use function Safe\file_put_contents;
use function Safe\realpath;

/**
 * Plugins class for galette
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @phpstan-type ModuleId string
 * @phpstan-type Module array{
 *      root: string,
 *      name: string,
 *      desc: string,
 *      author: string,
 *      version: string,
 *      acls: ?array<string,string>,
 *      date: ?string,
 *      priority: int,
 *      route: ?string,
 *      dbversion: ?float
 *  }
 * @phpstan-type Modules array<ModuleId, Module>
 */

class Plugins
{
    public const string TABLE = 'plugins';
    public const string PK = 'plugin_id';
    public const int DISABLED_COMPAT   = 0;
    public const int DISABLED_MISS     = 1;
    public const int DISABLED_EXPLICIT = 2;
    public const int DISABLED_DBVERSION = 3;
    public const int DISABLED_NOT_INSTALLED = 4;
    public const int DISABLED_NOT_UP2DATE = 5;

    /** @var array<string> */
    protected array $path;
    /** @var Modules */
    protected array $modules = [];
    /** @var array<ModuleId, self::DISABLED_*> */
    protected array $disabled = [];
    /** @var array<ModuleId, ?float> */
    protected array $db_existing = [];
    /** @var string[] */
    protected array $csrf_exclusions = [];

    protected ?string $id = null;
    protected ?string $mroot = null;

    #[Inject]
    protected Preferences $preferences;
    protected bool $autoload = false;

    #[Inject]
    protected Translator $translator;

    #[Inject]
    protected EventDispatcher $event_dispatcher;

    #[Inject]
    protected Db $zdb;

    private ContainerInterface $container;

    /** @var array<string, true> lib/ dirs already registered to avoid double-registration */
    private array $registeredLibDirs = [];

    /**
     * Register autoloader for all plugins
     *
     * This method must be called before session start so that plugin classes
     * stored in session can be properly deserialized. It scans plugin directories
     * and registers class loaders for each plugin's lib/ directory without
     * requiring the database or _define.php files.
     *
     * Plugin namespaces are added to the Composer PSR-4 loader already present
     * on the SPL stack so that a single autoloader handles both core and plugin
     * classes.
     *
     * @param string $path could be a separated list of paths
     *                     (path separator depends on your OS).
     */
    public function autoload(string $path): void
    {
        $this->path = explode(PATH_SEPARATOR, $path);
        $this->autoload = true;

        foreach ($this->path as $root) {
            $this->registerPluginClassLoaders($root);
        }
    }

    /**
     * Find the Composer ClassLoader from the SPL autoload stack.
     *
     * Galette requires Composer unconditionally: vendor/autoload.php is loaded
     * before any plugin code runs, so the Composer ClassLoader is always present
     * on the SPL stack. Throws a RuntimeException if it cannot be found, which
     * would indicate a misconfigured environment.
     */
    private function findClassLoader(): ClassLoader
    {
        foreach (spl_autoload_functions() as $func) {
            if (is_array($func) && $func[0] instanceof ClassLoader) {
                return $func[0];
            }
        }
        throw new RuntimeException(
            'Composer ClassLoader not found on the SPL autoload stack. '
            . 'Make sure vendor/autoload.php is loaded before calling Plugins::autoload().'
        );
    }

    /**
     * Scan a plugins root directory and register a class loader for every
     * namespace directory found inside each plugin's lib/ subdirectory.
     *
     * Plugin namespaces are added via Composer's addPsr4() so that all class
     * resolution stays within the single existing Composer autoload function.
     *
     * Already-registered lib/ directories are skipped to prevent duplicate
     * entries when loadModules() calls autoload() a second time.
     */
    private function registerPluginClassLoaders(string $root): void
    {
        if (!str_ends_with($root, '/')) {
            $root .= '/';
        }

        if (!is_dir($root)) {
            return;
        }

        $composerLoader = $this->findClassLoader();

        foreach (new \DirectoryIterator($root) as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            $lib_dir = $entry->getPathname() . '/lib';
            if (!is_dir($lib_dir)) {
                continue;
            }

            // Skip if already registered (second call from loadModules())
            if (isset($this->registeredLibDirs[$lib_dir])) {
                continue;
            }
            $this->registeredLibDirs[$lib_dir] = true;

            // Register every namespace directory found inside lib/
            foreach (new \DirectoryIterator($lib_dir) as $ns_entry) {
                if ($ns_entry->isDot() || !$ns_entry->isDir()) {
                    continue;
                }

                $namespace = $ns_entry->getFilename();
                $ns_dir = $ns_entry->getPathname();

                // Add to the existing Composer PSR-4 loader.
                // PSR-4 strips the namespace prefix, so the base path must
                // point to the namespace directory itself:
                //   GaletteActivities\ => lib/GaletteActivities/
                $composerLoader->addPsr4($namespace . '\\', $ns_dir);
            }
        }
    }

    /**
     * Load modules from database
     */
    protected function loadDbModules(): void
    {
        try {
            $select = $this->zdb->select(self::TABLE, 'p');
            $results = $this->zdb->execute($select);
            foreach ($results as $result) {
                $this->db_existing[$result['plugin_id']] = $result['version'] !== null ? (float)$result['version'] : null;
            }
        } catch (Throwable $e) {
            //Laminas wraps PDOException in InvalidQueryException (which does not extend PDOException),
            //so catching Throwable and delegating recognition to Db::isMissingTableException() is required.
            if (!$this->zdb->isMissingTableException($e)) {
                throw $e;
            }
            Analog::log(
                'Cannot load plugins from database: ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }

    /**
     * Parse modules in current path
     */
    protected function parseModules(): void
    {
        foreach ($this->path as $root) {
            if (!str_ends_with($root, '/')) {
                $root .= '/';
            }

            try {
                $d = dir($root);
            } catch (DirException $e) {
                continue;
            }

            while (($entry = $d->read()) !== false) {
                $full_entry = realpath($root . $entry);
                if ($entry != '.' && $entry != '..' && is_dir($full_entry)) {
                    $this->id = $entry;
                    $this->mroot = $full_entry;
                    if ($this->autoload === true) {
                        if (
                            !file_exists($full_entry . '/_define.php')
                            || !file_exists($full_entry . '/_routes.php')
                        ) {
                            //plugin is not compatible with that version of galette.
                            Analog::log(
                                sprintf('Plugin "%s" is missing a _define.php and/or _routes.php '
                                    . 'files that are required.', $entry),
                                Analog::WARNING
                            );
                            // Ensure plugin appears in modules list even if required files are missing.
                            if (!isset($this->modules[$entry])) {
                                $this->modules[$entry] = [
                                    'name'      => $entry,
                                    'desc'      => '',
                                    'author'    => '',
                                    'version'   => '',
                                    'date'      => '',
                                    'priority'  => 0,
                                    'root'      => $full_entry,
                                    'route'     => null,
                                    'dbversion' => null,
                                ];
                            }
                            $this->setDisabled(self::DISABLED_MISS);
                        } else {
                            //Will call $this->register()
                            include $full_entry . '/_define.php';
                            if ($this->moduleExists($entry)) {
                                if ($this->isDisabled($entry)) {
                                    //register() already disabled the plugin (e.g. DISABLED_COMPAT):
                                    //do not let isExplicitlyDisabled() overwrite the root cause.
                                    continue;
                                }
                                if ($this->isExplicitlyDisabled()) {
                                    Analog::log(
                                        sprintf('Plugin "%s" is explicitly disabled', $entry),
                                        Analog::INFO
                                    );
                                    $this->setDisabled(self::DISABLED_EXPLICIT);
                                    continue;
                                }
                                $this->postRegistrationChecks();

                                if ($this->isDisabled($entry)) {
                                    continue;
                                }
                                if (file_exists($full_entry . '/_config.inc.php')) {
                                    //include plugin configuration file if exists; it often declares constants that may be used in self::check()
                                    require_once $full_entry . '/_config.inc.php';
                                }
                                $this->check();
                            } else {
                                Analog::log(
                                    sprintf('Plugin "%s" is not loaded', $entry),
                                    Analog::ERROR
                                );
                            }
                            $this->id = null;
                            $this->mroot = null;
                        }
                    }
                }
            }
            $d->close();
        }
    }

    /**
     * Loads modules.
     *
     * @param Preferences $preferences Galette's Preferences
     * @param string      $path        could be a separated list of paths
     *                                 (path separator depends on your OS).
     * @param ?string     $lang        Indicates if we need to load a lang file on plugin
     *                                 loading.
     */
    public function loadModules(Preferences $preferences, string $path, ?string $lang = null): void
    {
        $this->preferences = $preferences;
        $this->autoload($path);
        $this->loadDbModules();
        $this->parseModules();

        // Sort plugins
        uasort($this->modules, $this->sortModules(...));

        // Load translation, _prepend and ns_file
        foreach (array_keys($this->getActiveModules()) as $id) {
            if ($lang !== null) {
                $this->loadModuleL10N($id, $lang);
            }
            $this->loadEventProviders($id);
            $this->overridePrefs($id);
        }
    }

    /**
     * This method registers a module in modules list. You should use this to
     * register a new module.
     *
     * <var>$permissions</var> is a comma separated list of permissions for your
     * module. If <var>$permissions</var> is null, only super admin has access to
     * this module.
     *
     * <var>$priority</var> is an integer. Modules are sorted by priority and name.
     * Lowest priority comes first.
     *
     * @param string                $name     Module name
     * @param string                $desc     Module description
     * @param string                $author   Module author name
     * @param string                $version  Module version
     * @param ?string               $compver  Galette version compatibility
     * @param ?string               $route    Module route name
     * @param ?string               $date     Module release date
     * @param ?array<string,string> $acls     Module routes ACLs
     * @param ?int                  $priority Module priority
     * @param ?float                $dbver    Module database version
     */
    public function register(
        string $name,
        string $desc,
        string $author,
        string $version,
        ?string $compver = null,
        ?string $route = null,
        ?string $date = null,
        ?array $acls = null,
        ?int $priority = 1000,
        ?float $dbver = null
    ): void {
        //store module information
        $this->modules[$this->id] = [
            'root'          => $this->mroot,
            'name'          => $name,
            'desc'          => $desc,
            'author'        => $author,
            'version'       => $version,
            'acls'          => $acls,
            'date'          => $date,
            'priority'      => $priority ?? 1000,
            'route'         => $route,
            'dbversion'     => $dbver
        ];

        //check compatibility
        if ($compver === null) {
            //plugin compatibility missing!
            Analog::log(
                sprintf(
                    'Plugin "%s" does not contain mandatory version compatibility information. Please contact the author.',
                    $name
                ),
                Analog::ERROR
            );
            $this->setDisabled(self::DISABLED_COMPAT);
            return;
        }

        if (version_compare($compver, GALETTE_COMPAT_VERSION, '<')) {
            //plugin is not compatible with that version of galette.
            Analog::log(
                sprintf(
                    'Plugin "%s" is known to be compatible with Galette %s only, but you current installation requires a plugin compatible with at least %s',
                    $name,
                    $compver,
                    GALETTE_COMPAT_VERSION
                ),
                Analog::WARNING
            );
            $this->setDisabled(self::DISABLED_COMPAT);
        }
    }

    /**
     * Perform post plugin registration checks
     */
    private function postRegistrationChecks(): void
    {
        if ($this->isDisabled($this->id)) {
            //already disabled; ignore
            return;
        }
        if ($this->modules[$this->id]['dbversion'] === null && $this->needsDatabase($this->id)) {
            //plugin needs a database but no version is provided
            Analog::log(
                sprintf(
                    'Plugin "%s" needs a database but no version is provided.',
                    $this->modules[$this->id]['name']
                ),
                Analog::ERROR
            );
            $this->setDisabled(self::DISABLED_DBVERSION);
            return;
        }

        if (
            $this->needsDatabase($this->id)
            && isset($this->db_existing[$this->id])
            && $this->modules[$this->id]['dbversion'] != $this->db_existing[$this->id]
        ) {
            //plugin database needs an update
            Analog::log(
                sprintf(
                    'Plugin "%s" database needs to be updated.',
                    $this->modules[$this->id]['name']
                ),
                Analog::WARNING
            );
            $this->markToUpdate();
        }
    }

    /**
     * Post plugin initialization checks
     */
    private function check(): void
    {
        $plugin_class = $this->getClassName($this->id, true);
        if (
            !class_exists($plugin_class)
            || !is_subclass_of($plugin_class, GalettePlugin::class)
        ) {
            //plugin is missing its mandatory class or does not extend GalettePlugin
            Analog::log(
                sprintf(
                    'Plugin "%s" class "%s" is missing or it does not extend GalettePlugin.',
                    $this->modules[$this->id]['name'],
                    $plugin_class
                ),
                Analog::ERROR
            );
            $this->setDisabled(self::DISABLED_MISS);
            return;
        }

        /** @var GalettePlugin $plugin */
        $plugin = $this->container->get($plugin_class);
        $is_installed = $plugin->isInstalled();
        $needs_database = $this->needsDatabase($this->id);

        if (!$is_installed && $needs_database) {
            //FIXME: plugin may not be installed again if it's just missing in db_existing, creation script may remove existing tables!
            //plugin database has not been installed
            Analog::log(
                sprintf(
                    'Plugin "%s" has not been installed.',
                    $this->modules[$this->id]['name']
                ),
                Analog::WARNING
            );
            $this->markDbMissing();
        } elseif ($is_installed && $needs_database && !array_key_exists($this->id, $this->db_existing)) {
            $this->autoMigratePluginVersion();
        }
    }

    /**
     * Reset modules list
     */
    public function resetModulesList(): void
    {
        $this->modules = [];
    }

    /**
     * Deactivate specified module
     *
     * @param string $id Module's ID
     *
     * @throws Exception
     */
    public function deactivateModule(string $id): void
    {
        if (!isset($this->modules[$id])) {
            throw new Exception(_T("No such module."));
        }

        try {
            $this->createDisabledFile($id);
        } catch (Exception $e) {
            throw new Exception(_T("Cannot deactivate plugin."), $e->getCode(), $e);
        }
    }

    /**
     * Create the disabled file for a specified module
     *
     * @param string $id Module's ID
     *
     * @throws Exception
     */
    protected function createDisabledFile(string $id): void
    {
        try {
            file_put_contents($this->getDisabledPath($id), '');
        } catch (FilesystemException $e) {
            throw new Exception("Cannot create disabled file for plugin " . $id, 0, $e);
        }
    }

    /**
     * Remove the disabled file for a specified module
     *
     * @param string $id Module's ID
     *
     * @throws Exception
     */
    protected function removeDisabledFile(string $id): void
    {
        $module = $this->getModule($id);
        $legacy_file = $module['root'] . '/_disabled';
        //try to remove the old file
        if (file_exists($legacy_file) && @unlink($legacy_file) === false) { //@phpstan-ignore theCodingMachineSafe.function
            Analog::log(
                sprintf(
                    'Plugin %1$s was disabled from its own directory, that is deprecated. Migration has been done, please remove the file %2$s manually.',
                    $this->id,
                    $legacy_file
                ),
                Analog::WARNING
            );
            throw new Exception("Cannot unlink legacy disabled file for plugin " . $id);
        }

        if (file_exists($this->getDisabledPath($id)) && @unlink($this->getDisabledPath($id)) === false) { //@phpstan-ignore theCodingMachineSafe.function
            throw new Exception("Cannot unlink disabled file for plugin " . $id);
        }
    }

    /**
     * Activate specified module
     *
     * @param string $id Module's ID
     *
     * @throws Exception
     */
    public function activateModule(string $id): void
    {
        if (!isset($this->disabled[$id])) {
            throw new Exception(_T("No such module."));
        }

        try {
            $this->removeDisabledFile($id);
        } catch (Exception $e) {
            throw new Exception(_T("Cannot activate plugin."), $e->getCode(), $e);
        }
    }

    /**
     * This method will search for file <var>$file</var> in language
     * <var>$lang</var> for module <var>$id</var>.
     * <var>$file</var> should not have any extension.
     *
     * @param string $id       Module ID
     * @param string $language Language code
     */
    public function loadModuleL10N(string $id, string $language): void
    {
        if (empty($language) || !isset($this->modules[$id])) {
            return;
        }

        $domains = [
            $this->modules[$id]['route']
        ];
        foreach ($domains as $domain) {
            //load translation file for domain
            $this->translator->addTranslationFilePattern(
                type: 'gettext',
                baseDir: $this->modules[$id]['root'] . '/lang/',
                pattern: '/%s/LC_MESSAGES/' . $domain . '.mo',
                textDomain: $domain
            );

            //check if a local lang file exists and load it
            $this->translator->addTranslationFilePattern(
                type: 'phparray',
                baseDir: $this->modules[$id]['root'] . '/lang/',
                pattern: $domain . '_%s_local_lang.php',
                textDomain: $domain
            );
        }
    }

    /**
     * Loads event provider
     *
     * @param string $id Module ID
     */
    public function loadEventProviders(string $id): void
    {
        $providerClassName = '\\' . $this->getNamespace($id) . '\\' . 'PluginEventProvider';
        if (
            class_exists($providerClassName)
            && method_exists($providerClassName, 'provideListeners')
        ) {
            $this->event_dispatcher->subscribeListenersFrom(new $providerClassName());
        }
    }

    /**
     * Returns requested module
     *
     * @param string $id Module ID
     *
     * @return Module
     */
    public function getModule(string $id): array
    {
        if (isset($this->modules[$id])) {
            return $this->modules[$id];
        }
        throw new MissingPluginException($id);
    }

    /**
     * List of all modules
     *
     * @return Modules
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * List of all active modules
     *
     * @return Modules
     */
    public function getActiveModules(): array
    {
        $active_modules = $this->modules;
        foreach (array_keys($active_modules) as $id) {
            if ($this->isDisabled($id)) {
                unset($active_modules[$id]);
            }
        }

        return $active_modules;
    }

    /**
     * Check if a module exists
     *
     * @param string $id Module ID
     */
    public function moduleExists(string $id): bool
    {
        return isset($this->modules[$id]);
    }

    /**
     * List of all disabled modules
     *
     * @return Modules
     */
    public function getDisabledModules(): array
    {
        return array_filter($this->modules, $this->isDisabled(...), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Returns one disabled module
     *
     * @return Module
     */
    public function getDisabledModule(string $id): array
    {
        if (!$this->moduleExists($id)) {
            throw new MissingPluginException($id);
        }
        if (!isset($this->disabled[$id])) {
            throw new \LogicException(
                sprintf('Module "%s" is not disabled!', $id)
            );
        }
        return $this->modules[$id];
    }

    /**
     * Get installed database version for a plugin
     *
     * Returns the version stored in the database for the given plugin ID,
     * or null if the plugin has no database entry yet.
     *
     * @param string $id Plugin identifier
     */
    public function getInstalledDbVersion(string $id): ?string
    {
        if (!$this->moduleExists($id)) {
            throw new MissingPluginException($id);
        }
        $version = $this->db_existing[$id] ?? null;
        return $version !== null ? (string)$version : null;
    }

    /**
     * Get cause for a plugin to be disabled
     */
    public function getDisabledCause(string $id): int
    {
        if (!$this->moduleExists($id)) {
            throw new MissingPluginException($id);
        }
        if (!isset($this->disabled[$id])) {
            throw new \LogicException(
                sprintf('Module "%s" is not disabled!', $id)
            );
        }
        return $this->disabled[$id];
    }

    /**
     * Is module disabled
     *
     * @param string $id Module ID
     */
    public function isDisabled(string $id): bool
    {
        return isset($this->disabled[$id]);
    }

    /**
     * Get a module root path
     *
     * @param string $id Module ID
     */
    public function moduleRoot(string $id): ?string
    {
        return $this->moduleInfo($id, 'root');
    }

    /**
     * Returns a module information that could be:
     * - root
     * - name
     * - desc
     * - author
     * - version
     * - date
     * - permissions
     * - priority
     *
     * @param string $id   Module ID
     * @param string $info Information to retrieve
     *
     * @return mixed module's information
     */
    public function moduleInfo(string $id, string $info): mixed
    {
        return $this->modules[$id][$info] ?? null;
    }

    /**
     * Sort modules by priority, then name
     *
     * @param array<string, mixed> $a A module
     * @param array<string, mixed> $b Another module
     *
     * @return int 1|-1 1 if "a" has the highest priority, -1 otherwise
     */
    private function sortModules(array $a, array $b): int
    {
        if ($a['priority'] == $b['priority']) {
            return strcasecmp((string)$a['name'], (string)$b['name']);
        }

        return ($a['priority'] < $b['priority']) ? -1 : 1;
    }

    /**
     * Get the templates path for a specified module
     *
     * @param string $id Module's ID
     *
     * @return string  Concatenated templates path for requested module
     */
    public function getTemplatesPath(string $id): string
    {
        return $this->moduleRoot($id) . '/templates/' . $this->preferences->pref_theme;
    }

    /**
     * Get the templates path for a specified module name
     *
     * @param string $name Module's name
     *
     * @return string Concatenated templates path for requested module
     */
    public function getTemplatesPathFromName(string $name): string
    {
        foreach ($this->getActiveModules() as $r => $mod) {
            if ($mod['name'] === $name) {
                return $this->getTemplatesPath($r);
            }
        }
        return '';
    }

    /**
     * For each module, returns the headers template file namespaced path, if present.
     *
     * @return array<string> of headers to include for all modules
     */
    public function getTplHeaders(): array
    {
        $_headers = [];
        foreach (array_keys($this->getActiveModules()) as $key) {
            $headers_path = $this->getTemplatesPath($key) . '/headers.html.twig';
            if (file_exists($headers_path)) {
                $_headers[$key] = sprintf('@%s/%s.html.twig', $this->getClassName($key), 'headers');
            }
        }
        return $_headers;
    }

    /**
     * For each module, returns the scripts template file namespaced path, if present.
     *
     * @return array<string> of scripts to include for all modules
     */
    public function getTplScripts(): array
    {
        $_scripts = [];
        foreach (array_keys($this->getActiveModules()) as $key) {
            $scripts_path = $this->getTemplatesPath($key) . '/scripts.html.twig';
            if (file_exists($scripts_path)) {
                $_scripts[$key] = sprintf('@%s/%s.html.twig', $this->getClassName($key), 'scripts');
            }
        }
        return $_scripts;
    }

    /**
     * Does module need a database?
     *
     * @param string $id Module's ID
     */
    public function needsDatabase(string $id): bool
    {
        if ($this->moduleExists($id)) {
            $d = $this->modules[$id]['root'] . '/scripts/';
            return file_exists($d);
        } else {
            throw new MissingPluginException($id);
        }
    }

    /**
     * Override preferences from plugin
     *
     * @param string $id Module ID
     */
    public function overridePrefs(string $id): void
    {
        $overridables = ['pref_adhesion_form'];

        $f = $this->modules[$id]['root'] . '/_preferences.php';
        if (file_exists($f)) {
            include_once $f;
            if (isset($_preferences)) {
                foreach ($_preferences as $k => $v) {
                    if (in_array($k, $overridables)) {
                        $this->preferences->$k = $v;
                    }
                }
            }
        }
    }

    /**
     * Automatically migrate plugin version to core table; sets version to plugin version.
     */
    private function autoMigratePluginVersion(): void
    {
        try {
            $module = $this->getModule($this->id);
            $insert = $this->zdb->insert(self::TABLE);
            $insert->values([
                'plugin_id' => $this->id,
                'version' => $module['dbversion'],
            ]);
            $this->zdb->execute($insert);
            Analog::log(
                sprintf(
                    'Plugin "%s" automatically migrated to core table.',
                    $this->modules[$this->id]['name']
                ),
                Analog::INFO
            );
            $this->db_existing[$this->id] = $module['dbversion'];
        } catch (Throwable $e) {
            if (!$this->zdb->isMissingTableException($e)) {
                //plugins table may be missing while updating
                throw $e;
            }
        }
    }

    /**
     * Get plugins routes ACLs
     *
     * @return array<string>
     */
    public function getAcls(): array
    {
        $acls = [];
        foreach ($this->getActiveModules() as $module) {
            $acls = array_merge($acls, $module['acls'] ?? []);
        }
        return $acls;
    }

    /**
     * Retrieve a file that should be publicly exposed
     *
     * @param string $id   Module id
     * @param string $path File path
     */
    public function getFile(string $id, string $path): string
    {
        if (!$this->moduleExists($id)) {
            throw new MissingPluginException($id);
        }

        if ($this->isDisabled($id)) {
            throw new RuntimeException(
                sprintf('Trying to access file "%s" from module "%s" that is disabled.', $path, $id)
            );
        }

        $file = $this->modules[$id]['root'] . '/webroot/' . $path;
        if (file_exists($file)) {
            return $file;
        } else {
            throw new RuntimeException(_T("File not found!"));
        }
    }

    /**
     * Set a module as disabled
     *
     * @param self::DISABLED_* $cause Disabling cause
     */
    private function setDisabled(int $cause): void
    {
        $this->disabled[$this->id] = $cause;
    }

    /**
     * Mark a module as needing an update
     */
    private function markToUpdate(): void
    {
        $this->setDisabled(self::DISABLED_NOT_UP2DATE);
    }

    /**
     * Mark a module as not installed
     */
    private function markDbMissing(): void
    {
        $this->setDisabled(self::DISABLED_NOT_INSTALLED);
    }

    /**
     * Get module namespace
     *
     * @param string $id Module ID
     */
    public function getNamespace(string $id): string
    {
        return str_replace(' ', '', $this->modules[$id]['name']);
    }

    /**
     * Get module class name
     *
     * @param string $id   Module ID
     * @param bool   $full Include namespace, defaults to false
     */
    public function getClassName(string $id, bool $full = false): string
    {
        $class = sprintf('PluginGalette%1$s', ucfirst((string)$this->modules[$id]['route']));
        if ($full === true) {
            return sprintf('%s\%s', $this->getNamespace($id), $class);
        }
        return $class;
    }

    /**
     * Set CSRF excluded routes for one plugin
     *
     * @param array<string> $exclusions Array of regular expressions patterns to be excluded
     */
    public function setCsrfExclusions(array $exclusions): self
    {
        $this->csrf_exclusions = array_merge($this->csrf_exclusions, $exclusions);
        return $this;
    }

    /**
     * Get CSRF excluded routes patterns
     *
     * @return array<string>
     */
    public function getCsrfExclusions(): array
    {
        return $this->csrf_exclusions;
    }

    /**
     * Is the current module explicitly disabled?
     */
    public function isExplicitlyDisabled(): bool
    {
        if (file_exists($this->getDisabledPath($this->id))) {
            return true;
        }

        //keep the old way of disabling a plugin for backward compatibility
        $legacy_file = $this->mroot . '/_disabled';
        if (file_exists($legacy_file)) {
            try {
                //disable module the new way
                $this->createDisabledFile($this->id);
                //try to remove the old file
                if (@unlink($legacy_file) === false) { //@phpstan-ignore theCodingMachineSafe.function
                    Analog::log(
                        sprintf(
                            'Plugin %1$s was disabled from its own directory, that is deprecated. Migration has been done, please remove the file %2$s manually.',
                            $this->id,
                            $legacy_file
                        ),
                        Analog::WARNING
                    );
                }
            } catch (Exception) {
                //empty catch
            }

            return true;
        }

        return false;
    }

    /**
     * Get path for disabled file
     *
     * @param string $id Module ID
     */
    public function getDisabledPath(string $id): string
    {
        return sprintf(
            '%1$s/plugin_%2$s_disabled',
            GALETTE_PLUGINS_DATA_PATH,
            $id
        );
    }

    /**
     * Set translator
     *
     * @param Translator $translator Translator instance
     */
    public function setTranslator(Translator $translator): self
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * Set event dispatcher
     *
     * @param EventDispatcher $dispatcher Event dispatcher instance
     */
    public function setEventDispatcher(EventDispatcher $dispatcher): self
    {
        $this->event_dispatcher = $dispatcher;
        return $this;
    }

    /**
     * Set container, and required dependencies
     *
     * Automatic injection is not possible since Plugins must be initialized
     * before the dependency injection.
     */
    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;
        $this->setTranslator($container->get(Translator::class));
        $this->setEventDispatcher($container->get(EventDispatcher::class));
        $this->setDb($container->get(Db::class));
        return $this;
    }

    /**
     * Set database instance
     *
     * @param Db $db Database instance
     */
    public function setDb(Db $db): self
    {
        $this->zdb = $db;
        return $this;
    }
}
