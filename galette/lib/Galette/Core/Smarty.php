<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Smarty
 *
 * PHP version 5
 *
 * Copyright © 2012-2014 The Galette Team
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
 * @copyright 2012-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-05-05
 */

namespace Galette\Core;

/**
 * Smarty wrapper
 *
 * @category  Core
 * @name      Smarty
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://www.smarty.net/docs/en/installing.smarty.extended.tpl
 * @since     Available since 0.7.1dev - 2012-05-05
 */
class Smarty extends \Slim\Extras\Views\Smarty
{

    /**
     * @var string Path to smatry configuration directory
     */
    public static $smartyConfigDir = null;

    /**
     * Main constructor
     *
     * @param plugins     $plugins     Galette's plugins
     * @param I18n        $i18n        Galette's I18n
     * @param Preferences $preferences Galette's preferences
     * @param Logo        $logo        Galette's logo
     * @param Login       $login       Galette's login
     * @param array       $session     Galette's session
     */
    function __construct($plugins, $i18n, $preferences, $logo, $login, $session)
    {
        /*parent::__construct();

        //paths configuration
        self::$smartyDirectory = GALETTE_SMARTY_PATH;
        self::$smartyTemplatesDirectory = GALETTE_ROOT . GALETTE_TPL_SUBDIR;
        self::$smartyCompileDirectory = GALETTE_COMPILE_DIR;
        self::$smartyCacheDirectory = GALETTE_CACHE_DIR;
        self::$smartyConfigDir = GALETTE_CONFIG_PATH;

        self::$smartyExtensions = array(
            GALETTE_SLIM_EXTRAS_PATH . 'Views/Extension/Smarty',
            GALETTE_ROOT . 'includes/smarty_plugins'
        );*/


        /*if ( GALETTE_MODE !== 'DEV' ) {
            //enable caching
            $this->caching = \Smarty::CACHING_LIFETIME_CURRENT;
            $this->setCompileCheck(false);
        }*/

        //$this->addPluginsDir(GALETTE_ROOT . 'includes/smarty_plugins');
    }

    /**
     * Creates new Smarty object instance if it doesn't already exist,
     * and returns it.
     *
     * @throws RuntimeException If Smarty lib directory does not exist
     * @return Smarty Instance
     */
    public static function getGaletteInstance($plugins, $i18n, $preferences, $logo, $login, $session)
    {
        $instance = parent::getInstance();

        if (self::$smartyConfigDir) {
            $instance->setConfigDir(self::$smartyConfigDir);
        }

        $instance->assign('login', $login);
        $instance->assign('logo', $logo);
        $instance->assign('template_subdir', GALETTE_THEME);
        foreach ( $plugins->getTplAssignments() as $k=>$v ) {
            $instance->assign($k, $v);
        }
        $instance->assign('tpl', $instance);
        $instance->assign('headers', $plugins->getTplHeaders());
        $instance->assign('plugin_actions', $plugins->getTplAdhActions());
        $instance->assign('plugin_batch_actions', $plugins->getTplAdhBatchActions());
        $instance->assign('plugin_detailled_actions', $plugins->getTplAdhDetailledActions());
        $instance->assign('jquery_dir', 'js/jquery/');
        $instance->assign('jquery_version', JQUERY_VERSION);
        $instance->assign('jquery_migrate_version', JQUERY_MIGRATE_VERSION);
        $instance->assign('jquery_ui_version', JQUERY_UI_VERSION);
        $instance->assign('jquery_markitup_version', JQUERY_MARKITUP_VERSION);
        $instance->assign('jquery_jqplot_version', JQUERY_JQPLOT_VERSION);
        $instance->assign('scripts_dir', 'js/');
        $instance->assign('PAGENAME', basename($_SERVER['SCRIPT_NAME']));
        $instance->assign('galette_base_path', './');
        $instance->assign('GALETTE_VERSION', GALETTE_VERSION);
        $instance->assign('GALETTE_MODE', GALETTE_MODE);
        /** galette_lang should be removed and languages used instead */
        $instance->assign('galette_lang', $i18n->getAbbrev());
        $instance->assign('languages', $i18n->getList());
        $instance->assign('plugins', $plugins);
        $instance->assign('preferences', $preferences);
        $instance->assign('pref_slogan', $preferences->pref_slogan);
        $instance->assign('pref_theme', $preferences->pref_theme);
        $instance->assign('pref_editor_enabled', $preferences->pref_editor_enabled);
        $instance->assign('pref_mail_method', $preferences->pref_mail_method);
        $instance->assign('existing_mailing', isset($session['mailing']));
        $instance->assign('require_tabs', null);
        $instance->assign('require_cookie', null);
        $instance->assign('contentcls', null);
        $instance->assign('require_tabs', null);
        $instance->assign('require_cookie', false);
        $instance->assign('additionnal_html_class', null);
        $instance->assign('require_calendar', null);
        $instance->assign('head_redirect', null);
        $instance->assign('error_detected', null);
        $instance->assign('warning_detected', null);
        $instance->assign('success_detected', null);
        $instance->assign('color_picker', null);
        $instance->assign('require_sorter', null);
        $instance->assign('require_dialog', null);
        $instance->assign('require_tree', null);
        $instance->assign('html_editor', null);
        $instance->assign('require_charts', null);

        return $instance;
    }
}
