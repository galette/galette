<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Plugins handling
 *
 * PHP version 5
 *
 * Copyright © 2009-2022 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7 - 2009-03-09
 */

namespace Galette\Core;

use Throwable;
use Analog\Analog;
use Galette\Common\ClassLoader;
use Galette\Core\Preferences;

/**
 * Plugins class for galette
 *
 * @category  Core
 * @name      Plugins
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7 - 2009-03-09
 */

class Plugins
{
    public const DISABLED_COMPAT   = 0;
    public const DISABLED_MISS     = 1;
    public const DISABLED_EXPLICIT = 2;

    protected $path;
    protected $modules = array();
    protected $disabled = array();
    protected $csrf_exclusions = array();

    protected $id;
    protected $mroot;

    protected $preferences;
    protected $autoload = false;

    /**
     * Register autoloaders for all plugins
     *
     * @param string $path could be a separated list of paths
     *                     (path separator depends on your OS).
     *
     * @return void
     */
    public function autoload($path)
    {
        $this->path = explode(PATH_SEPARATOR, $path);
        $this->autoload = true;
        $this->parseModules();
    }

    /**
     * Parse modules in current path
     *
     * @return void
     */
    protected function parseModules()
    {
        foreach ($this->path as $root) {
            if (!is_dir($root) || !is_readable($root)) {
                    continue;
            }

            if (substr($root, -1) != '/') {
                $root .= '/';
            }

            if (($d = @dir($root)) === false) {
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
                                'Plugin ' . $entry . ' is missing a _define.php and/or _routes.php ' .
                                'files that are required.',
                                Analog::WARNING
                            );
                            $this->setDisabled(self::DISABLED_MISS);
                        } elseif (!file_exists($full_entry . '/_disabled')) {
                            include $full_entry . '/_define.php';
                            $this->id = null;
                            $this->mroot = null;
                            //set autoloader to PluginName.
                            if (isset($this->modules[$entry]) && file_exists($full_entry . '/lib')) {
                                $varname = $entry . 'Loader';
                                $$varname = new ClassLoader(
                                    $this->getNamespace($entry),
                                    $full_entry . '/lib'
                                );
                                $$varname->register();
                            }
                        } else {
                            //plugin is not compatible with that version of galette.
                            Analog::log(
                                'Plugin ' . $entry . ' is explicitly disabled',
                                Analog::INFO
                            );
                            $this->setDisabled(self::DISABLED_EXPLICIT);
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
     * @param string      $lang        Indicates if we need to load a lang file on plugin
     *                                 loading.
     *
     * @return void
     */
    public function loadModules(Preferences $preferences, $path, $lang = null)
    {
        $this->preferences = $preferences;
        $this->path = explode(PATH_SEPARATOR, $path);

        $this->parseModules();

        // Sort plugins
        uasort($this->modules, array($this, 'sortModules'));

        // Load translation, _prepend and ns_file
        foreach ($this->modules as $id => $m) {
            $this->loadModuleL10N($id, $lang);
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
     * @param string  $name     Module name
     * @param string  $desc     Module description
     * @param string  $author   Module author name
     * @param string  $version  Module version
     * @param string  $compver  Galette version compatibility
     * @param string  $route    Module route name
     * @param string  $date     Module release date
     * @param string  $acls     Module routes ACLs
     * @param integer $priority Module priority
     *
     * @return void
     */
    public function register(
        $name,
        $desc,
        $author,
        $version,
        $compver = null,
        $route = null,
        $date = null,
        $acls = null,
        $priority = 1000
    ) {
        if ($compver === null) {
            //plugin compatibility missing!
            Analog::log(
                'Plugin ' . $name . ' does not contains mandatory version ' .
                'compatiblity information. Please contact the author.',
                Analog::ERROR
            );
            $this->setDisabled(self::DISABLED_COMPAT);
        } elseif (version_compare($compver, GALETTE_COMPAT_VERSION, '<')) {
            //plugin is not compatible with that version of galette.
            Analog::log(
                'Plugin ' . $name . ' is known to be compatible with Galette ' .
                $compver . ' only, but you current installation require a ' .
                'plugin compatible with at least ' . GALETTE_COMPAT_VERSION,
                Analog::WARNING
            );
            $this->setDisabled(self::DISABLED_COMPAT);
        } else if ($this->id) {
            $this->modules[$this->id] = array(
                'root'          => $this->mroot,
                'name'          => $name,
                'desc'          => $desc,
                'author'        => $author,
                'version'       => $version,
                'acls'          => $acls,
                'date'          => $date,
                'priority'      => $priority === null ?
                                     1000 : (int)$priority,
                'root_writable' => is_writable($this->mroot),
                'route'         => $route
            );
        }
    }

    /**
     * Reset modules list
     *
     * @return void
     */
    public function resetModulesList()
    {
        $this->modules = array();
    }

    /**
     * Deactivate specified module
     *
     * @param string $id Module's ID
     *
     * @return void|exception
     */
    public function deactivateModule($id)
    {
        if (!isset($this->modules[$id])) {
            throw new \Exception(_T("No such module."));
        }

        if (!$this->modules[$id]['root_writable']) {
            throw new \Exception(_T("Cannot deactivate plugin."));
        }

        if (@file_put_contents($this->modules[$id]['root'] . '/_disabled', '')) {
            throw new \Exception(_T("Cannot deactivate plugin."));
        }
    }

    /**
     * Activate specified module
     *
     * @param string $id Module's ID
     *
     * @return void|exception
     */
    public function activateModule($id)
    {
        if (!isset($this->disabled[$id])) {
            throw new \Exception(_T("No such module."));
        }

        if (!$this->disabled[$id]['root_writable']) {
            throw new \Exception(_T("Cannot activate plugin."));
        }

        if (@unlink($this->disabled[$id]['root'] . '/_disabled') === false) {
            throw new \Exception(_T("Cannot activate plugin."));
        }
    }

    /**
     * This method will search for file <var>$file</var> in language
     * <var>$lang</var> for module <var>$id</var>.
     * <var>$file</var> should not have any extension.
     *
     * @param string $id       Module ID
     * @param string $language Language code
     *
     * @return void
     */
    public function loadModuleL10N($id, $language)
    {
        global $translator;

        if (!$language || !isset($this->modules[$id])) {
            return;
        }

        $domains = [
            $this->modules[$id]['route']
        ];
        foreach ($domains as $domain) {
            //load translation file for domain
            $translator->addTranslationFilePattern(
                'gettext',
                $this->modules[$id]['root'] . '/lang/',
                '/%s/LC_MESSAGES/' . $domain . '.mo',
                $domain
            );

            //check if a local lang file exists and load it
            $translator->addTranslationFilePattern(
                'phparray',
                $this->modules[$id]['root'] . '/lang/',
                $domain . '_%s_local_lang.php',
                $domain
            );
        }
    }

    /**
     * Loads event provider
     *
     * @param string $id Module ID
     *
     * @return void
     */
    public function loadEventProviders($id)
    {
        global $emitter;

        $providerClassName = '\\' . $this->getNamespace($id) . '\\' . 'PluginEventProvider';
        if (
            class_exists($providerClassName)
            && method_exists($providerClassName, 'provideListeners')
        ) {
            $emitter->useListenerProvider(new $providerClassName());
        }
    }

    /**
     * Returns all modules associative array or only one module if <var>$id</var>
     * is present.
     *
     * @param string $id Optional module ID
     *
     * @return array
     */
    public function getModules($id = null)
    {
        if ($id && isset($this->modules[$id])) {
            return $this->modules[$id];
        }
        return $this->modules;
    }

    /**
     * Returns true if the module with ID <var>$id</var> exists.
     *
     * @param string $id Module ID
     *
     * @return boolean
     */
    public function moduleExists($id)
    {
        return isset($this->modules[$id]);
    }

    /**
     * Returns all disabled modules in an array
     *
     * @return array
     */
    public function getDisabledModules()
    {
        return $this->disabled;
    }

    /**
     * Returns root path for module with ID <var>$id</var>.
     *
     * @param string $id Module ID
     *
     * @return string
     */
    public function moduleRoot($id)
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
     * @return module's information
     */
    public function moduleInfo($id, $info)
    {
        return isset($this->modules[$id][$info]) ? $this->modules[$id][$info] : null;
    }

    /**
     * Sort modules
     *
     * @param array $a A module
     * @param array $b Another module
     *
     * @return 1|-1 1 if a has the highest priority, -1 otherwise
     */
    private function sortModules($a, $b)
    {
        if ($a['priority'] == $b['priority']) {
            return strcasecmp($a['name'], $b['name']);
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
    public function getTemplatesPath($id)
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
    public function getTemplatesPathFromName($name)
    {
        $id = null;
        foreach (array_keys($this->getModules()) as $r) {
            $mod = $this->getModules($r);
            if ($mod['name'] === $name) {
                return $this->getTemplatesPath($r);
            }
        }
    }

    /**
     * For each module, returns the headers template file namespaced path, if present.
     *
     * @return array of headers to include for all modules
     */
    public function getTplHeaders(): array
    {
        $_headers = array();
        foreach ($this->modules as $key => $module) {
            $headers_path = $this->getTemplatesPath($key) . '/headers.html.twig';
            if (file_exists($headers_path)) {
                $_headers[$key] = sprintf('@%s/%s.html.twig', $this->getClassName($key), 'headers');
            }
        }
        return $_headers;
    }

    /**
     * Does module needs a database?
     *
     * @param string $id Module's ID
     *
     * @return boolean
     */
    public function needsDatabase($id)
    {
        if (isset($this->modules[$id])) {
            $d = $this->modules[$id]['root'] . '/scripts/';
            if (file_exists($d)) {
                return true;
            } else {
                return false;
            }
        } else {
            throw new \Exception(_T("Module does not exists!"));
        }
    }

    /**
     * Override preferences from plugin
     *
     * @param string $id Module ID
     *
     * @return void
     */
    public function overridePrefs($id)
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
     * Get plugins routes ACLs
     *
     * @return array
     */
    public function getAcls()
    {
        $acls = [];
        foreach ($this->modules as $module) {
            $acls = array_merge($acls, $module['acls']);
        }
        return $acls;
    }

    /**
     * Retrieve a file that should be publicly exposed
     *
     * @param int    $id   Module id
     * @param string $path File path
     *
     * @return string
     */
    public function getFile($id, $path)
    {
        if (isset($this->modules[$id])) {
            $file = $this->modules[$id]['root'] . '/webroot/' . $path;
            if (file_exists($file)) {
                return $file;
            } else {
                throw new \RuntimeException(_T("File not found!"));
            }
        } else {
            throw new \Exception(_T("Module does not exists!"));
        }
    }

    /**
     * Set a module as disabled
     *
     * @param integer $cause Cause (one of Plugins::DISABLED_* constants)
     *
     * @return void
     */
    private function setDisabled($cause)
    {
        $this->disabled[$this->id] = array(
            'root'          => $this->mroot,
            'root_writable' => is_writable($this->mroot),
            'cause'         => $cause
        );
        $this->id = null;
        $this->mroot = null;
    }

    /**
     * Get module namespace
     *
     * @param integer $id Module ID
     *
     * @return string
     */
    public function getNamespace($id)
    {
        return str_replace(' ', '', $this->modules[$id]['name']);
    }

    /**
     * Get module class name
     *
     * @param integer $id   Module ID
     * @param bool    $full Include namespace, defaults to false
     *
     * @return string
     */
    public function getClassName($id, $full = false)
    {
        $class = sprintf('PluginGalette%1$s', ucfirst($this->modules[$id]['route']));
        if ($full === true) {
            return sprintf('%s\%s', $this->getNamespace($id), $class);
        }
        return $class;
    }

    /**
     * Set CRSF excluded routes
     *
     * @param array $exclusions Array of regular expressions patterns to be excluded
     *
     * @return $this
     */
    public function setCsrfExclusions(array $exclusions): self
    {
        $this->csrf_exclusions = $exclusions;
        return $this;
    }

    /**
     * Get CSRF excluded routes patterns
     *
     * @return array
     */
    public function getCsrfExclusions(): array
    {
        return $this->csrf_exclusions;
    }
}
