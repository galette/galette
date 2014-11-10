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
class Smarty extends \Slim\Views\Smarty
{

    public $parserClassName = '\SmartyBC';

    /**
     * @var string Path to smatry configuration directory
     */
    public $parserConfigDir = null;

    private $_plugins;
    private $_i18n;
    private $_preferences;
    private $_logo;
    private $_login;
    private $_session;

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
    public function __construct($plugins, $i18n, $preferences,
        $logo, $login, $session
    ) {
        $this->_plugins = $plugins;
        $this->_i18n = $i18n;
        $this->_preferences = $preferences;
        $this->_logo = $logo;
        $this->_login = $login;
        $this->_session = $session;

        parent::__construct();
        $this->parserDirectory = rtrim(GALETTE_SMARTY_PATH, DIRECTORY_SEPARATOR);
        $this->templatesDirectory = rtrim(
            GALETTE_ROOT . GALETTE_TPL_SUBDIR,
            DIRECTORY_SEPARATOR
        );
        $this->parserCompileDirectory = rtrim(
            GALETTE_COMPILE_DIR,
            DIRECTORY_SEPARATOR
        );
        $this->parserCacheDirectory = rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR);
        $this->parserConfigDir = GALETTE_CONFIG_PATH;

        $this->parserExtensions = array(
            GALETTE_SLIM_VIEWS_PATH . 'SmartyPlugins',
            GALETTE_ROOT . 'includes/smarty_plugins'
        );
    }

    /**
     * Creates new Smarty object instance if it doesn't already exist,
     * and returns it.
     *
     * @throws RuntimeException If Smarty lib directory does not exist
     * @return Smarty Instance
     */
    public function getInstance()
    {
        $instance = parent::getInstance();

        if ($this->parserConfigDir) {
            $instance->setConfigDir($this->parserConfigDir);
        }

        $instance->assign('login', $this->_login);
        $instance->assign('logo', $this->_logo);
        $instance->assign('template_subdir', GALETTE_THEME);
        foreach ( $this->_plugins->getTplAssignments() as $k=>$v ) {
            $instance->assign($k, $v);
        }
        $instance->assign('tpl', $instance);
        $instance->assign('headers', $this->_plugins->getTplHeaders());
        $instance->assign('plugin_actions', $this->_plugins->getTplAdhActions());
        $instance->assign(
            'plugin_batch_actions',
            $this->_plugins->getTplAdhBatchActions()
        );
        $instance->assign(
            'plugin_detailled_actions',
            $this->_plugins->getTplAdhDetailledActions()
        );
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
        $instance->assign('galette_lang', $this->_i18n->getAbbrev());
        $instance->assign('languages', $this->_i18n->getList());
        $instance->assign('plugins', $this->_plugins);
        $instance->assign('preferences', $this->_preferences);
        $instance->assign('pref_slogan', $this->_preferences->pref_slogan);
        $instance->assign('pref_theme', $this->_preferences->pref_theme);
        $instance->assign(
            'pref_editor_enabled',
            $this->_preferences->pref_editor_enabled
        );
        $instance->assign('pref_mail_method', $this->_preferences->pref_mail_method);
        $instance->assign('existing_mailing', isset($this->_session['mailing']));
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
