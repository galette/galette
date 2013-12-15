<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Plugins handling
 *
 * PHP version 5
 *
 * Copyright © 2009-2013 The Galette Team
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
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7 - 2009-03-09
 */

namespace Galette\Core;

use Analog\Analog as Analog;
use Galette\Common\ClassLoader;

/**
 * Plugins class for galette
 *
 * @category  Core
 * @name      Plugins
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7 - 2009-03-09
 */

class Plugins
{
    protected $path;
    protected $modules = array();
    protected $disabled = array();

    protected $id;
    protected $mroot;

    /**
     * Loads modules.
     *
     * @param string $path could be a separated list of paths
     * (path separator depends on your OS).
     * @param string $lang Indicates if we need to load a lang file on plugin
     * loading.
     *
     * @return void
     */
    public function loadModules($path, $lang=null)
    {
        $this->path = explode(PATH_SEPARATOR, $path);

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
                $full_entry = $root . $entry;

                if ( $entry != '.' && $entry != '..' && is_dir($full_entry)
                    && file_exists($full_entry.'/_define.php')
                ) {
                    if (!file_exists($full_entry.'/_disabled')) {
                        $this->id = $entry;
                        $this->mroot = $full_entry;
                        include $full_entry . '/_define.php';
                        $this->id = null;
                        $this->mroot = null;
                        //set autoloader to PluginName.
                        if ( file_exists($full_entry . '/lib') ) {
                            $varname = $entry . 'Loader';
                            $$varname = new ClassLoader(
                                str_replace(' ', '', $this->modules[$entry]['name']),
                                $full_entry . '/lib'
                            );
                            $$varname->register();
                        }
                    } else {
                        $this->disabled[$entry] = array(
                            'root' => $full_entry,
                            'root_writable' => is_writable($full_entry)
                        );
                    }
                }
            }
            $d->close();
        }

        // Sort plugins
        uasort($this->modules, array($this, '_sortModules'));

        // Load translation, _prepend and ns_file
        foreach ($this->modules as $id => $m) {
            $this->loadModuleL10N($id, $lang);
            $this->loadSmarties($id);
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
     * @param string  $name        Module name
     * @param string  $desc        Module description
     * @param string  $author      Module author name
     * @param string  $version     Module version
     * @param string  $compver     Galette version compatibility
     * @param string  $date        Module release date
     * @param string  $permissions Module permissions
     * @param integer $priority    Module priority
     *
     * @return void
     */
    public function register(
        $name, $desc, $author, $version, $compver = null, $date = null,
        $permissions=null, $priority=1000
    ) {

        if ( $compver === null ) {
            //plugin compatibility missing!
            Analog::log(
                'Plugin ' . $name . ' does not contains mandatory version ' .
                'compatiblity informations. Please contact the author.',
                Analog::ERROR
            );
            $this->disabled[$this->id] = array(
                'root' => $this->mroot,
                'root_writable' => is_writable($this->mroot)
            );
        } elseif ( version_compare($compver, GALETTE_COMPAT_VERSION, '<') ) {
            //plugin is not compatible with that version of galette.
            Analog::log(
                'Plugin ' . $name . ' is known to be compatible with Galette ' .
                $compver . ' only, but you current installation require a ' .
                'plugin compatible with at least ' . GALETTE_COMPAT_VERSION,
                Analog::WARNING
            );
            $this->disabled[$this->id] = array(
                'root' => $this->mroot,
                'root_writable' => is_writable($this->mroot)
            );
        } else {
            if ($this->id) {
                $release_date = $date;
                if ( $date !== null ) {
                    //try to localize release date
                    try {
                        $release_date = new \DateTime($date);
                        $release_date = $release_date->format(_T("Y-m-d"));
                    } catch ( \Exception $e ) {
                        Analog::log(
                            'Unable to localize release date for plugin ' . $name,
                            Analog::WARNING
                        );
                    }
                }

                $this->modules[$this->id] = array(
                    'root'          => $this->mroot,
                    'name'          => $name,
                    'desc'          => $desc,
                    'author'        => $author,
                    'version'       => $version,
                    'permissions'   => $permissions,
                    'date'          => $release_date,
                    'priority'      => $priority === null ?
                                         1000 :
                                         (integer) $priority,
                    'root_writable' => is_writable($this->mroot)
                );
            }
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

        if ( @file_put_contents($this->modules[$id]['root'] . '/_disabled', '') ) {
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

        if (@unlink($this->disabled[$id]['root'].'/_disabled') === false) {
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
        global $lang;

        if (!$language || !isset($this->modules[$id])) {
            return;
        }

        $f = $this->modules[$id]['root'] . '/lang' . '/lang_' . $language . '.php';
        if ( file_exists($f) ) {
            include_once $f;
        }
    }

    /**
     * Loads smarties specific (headers, assigments and so on)
     *
     * @param string $id Module ID
     *
     * @return void
     */
    public function loadSmarties($id)
    {
        $f = $this->modules[$id]['root'] . '/_smarties.php';
        if ( file_exists($f) ) {
            include_once $f;
            if ( isset($_tpl_assignments) ) {
                $this->modules[$id]['tpl_assignments'] = $_tpl_assignments;
            }
        }
    }

    /**
     * Returns all modules associative array or only one module if <var>$id</var>
     * is present.
     *
     * @param string $id Optionnal module ID
     *
     * @return <b>array</b>
     */
    public function getModules($id=null)
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
     * @return <b>boolean</b>
     */
    public function moduleExists($id)
    {
        return isset($this->modules[$id]);
    }

    /**
     * Returns all disabled modules in an array
     *
     * @return <b>array</b>
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
     * @return <b>string</b>
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
     * @return module's informations
     */
    public function moduleInfo($id,$info)
    {
        return isset($this->modules[$id][$info]) ? $this->modules[$id][$info] : null;
    }

    /**
     * Search and load menu templates from plugins.
     * Also sets the web path to the plugin with the var "galette_[plugin-name]_path"
     *
     * @param Smarty      $tpl         Smarty template
     * @param Preferences $preferences Galette's preferences
     *
     * @return void
     */
    public function getMenus($tpl, $preferences)
    {
        $modules = $this->getModules();
        foreach ( array_keys($this->getModules()) as $r ) {
            $menu_path = $this->getTemplatesPath($r) . '/menu.tpl';
            if ( $tpl->template_exists($menu_path) ) {
                $name2path = strtolower(
                    str_replace(' ', '_', $modules[$r]['name'])
                );
                $tpl->assign(
                    'galette_' . $name2path . '_path',
                    'plugins/' . $r . '/'
                );
                $tpl->display($menu_path);
            }
        }
    }

    /**
     * Search and load public menu templates from plugins.
     * Also sets the web path to the plugin with the var "galette_[plugin-name]_path"
     *
     * @param Smarty      $tpl         Smarty template
     * @param Preferences $preferences Galette's preferences
     * @param boolean     $public_page Called from a public page
     *
     * @return void
     */
    public function getPublicMenus($tpl, $preferences, $public_page = false)
    {
        $modules = $this->getModules();
        foreach ( array_keys($this->getModules()) as $r ) {
            $menu_path = $this->getTemplatesPath($r) . '/public_menu.tpl';
            if ( $tpl->template_exists($menu_path) ) {
                $name2path = strtolower(
                    str_replace(' ', '_', $modules[$r]['name'])
                );
                $tpl->assign(
                    'galette_' . $name2path . '_path',
                    'plugins/' . $r . '/'
                );
                $tpl->assign(
                    'public_page',
                    $public_page
                );
                $tpl->display($menu_path);
            }
        }
    }

    /**
     * Sort modules
     *
     * @param array $a A module
     * @param array $b Another module
     *
     * @return 1 if a has the highest priority, -1 otherwise
     */
    private function _sortModules($a, $b)
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
     * @return Concatenated templates path for requested module
     */
    public function getTemplatesPath($id)
    {
        global $preferences;
        return $this->moduleRoot($id) . '/templates/' . $preferences->pref_theme;
    }

    /**
     * Get the templates path for a specified module name
     *
     * @param string $name Module's name
     *
     * @return Concatenated templates path for requested module
     */
    public function getTemplatesPathFromName($name)
    {
        $id = null;
        foreach ( array_keys($this->getModules()) as $r ) {
            $mod = $this->getModules($r);
            if ( $mod['name'] === $name ) {
                return $this->getTemplatesPath($r);
            }
        }
    }

    /**
     * For each module, returns the headers.tpl full path, if present.
     *
     * @return array of headers to include for all modules
     */
    public function getTplHeaders()
    {
        $_headers = array();
        foreach ( $this->modules as $key=>$module ) {
            $headers_path = $this->getTemplatesPath($key) . '/headers.tpl';
            if ( file_exists($headers_path) ) {
                $_headers[] = $headers_path;
            }
        }
        return $_headers;
    }

    /**
     * For each module, return the adh_actions.tpl full path, if present.
     *
     * @return array of adherent actions to include on member list for all modules
     */
    public function getTplAdhActions()
    {
        $_actions = array();
        foreach ( $this->modules as $key=>$module ) {
            $actions_path = $this->getTemplatesPath($key) . '/adh_actions.tpl';
            if ( file_exists($actions_path) ) {
                $_actions[] = $actions_path;
            }
        }
        return $_actions;
    }

    /**
     * For each module, return the adh_fiche_action.tpl full path, if present.
     *
     * @return array of adherent actions to include on membre detailled view for
     * all modules
     */
    public function getTplAdhDetailledActions()
    {
        $_actions = array();
        foreach ( $this->modules as $key=>$module ) {
            $actions_path = $this->getTemplatesPath($key) . '/adh_fiche_action.tpl';
            if ( file_exists($actions_path) ) {
                $_actions[] = $actions_path;
            }
        }
        return $_actions;
    }

    /**
     * For each module, gets templates assignements ; and replace some path variables
     *
     * @return array of Smarty templates assignement for all modules
     */
    public function getTplAssignments()
    {
        global $preferences;
        $_assign = array();
        foreach ( $this->modules as $key=>$module ) {
            if ( isset($module['tpl_assignments']) ) {
                foreach ( $module['tpl_assignments'] as $k=>$v ) {
                    $v = str_replace(
                        '__plugin_dir__',
                        'plugins/' . $key . '/',
                        $v
                    );
                    $v = str_replace(
                        '__plugin_include_dir__',
                        'plugins/' . $key . '/includes/',
                        $v
                    );
                    $v = str_replace(
                        '__plugin_templates_dir__',
                        'plugins/' . $key . '/templates/' .
                        $preferences->pref_theme . '/',
                        $v
                    );
                    $_assign[$k] = $v;
                }
            }
        }
        return $_assign;
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
        if ( isset($this->modules[$id]) ) {
            $d = $this->modules[$id]['root'] . '/sql/';
            if ( file_exists($d) ) {
                return true;
            } else {
                return false;
            }
        } else {
            throw new \Exception(_T("Module does not exists!"));
        }
    }

}
