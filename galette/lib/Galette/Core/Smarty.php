<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Smarty
 *
 * PHP version 5
 *
 * Copyright © 2012-2013 The Galette Team
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
 * @copyright 2012-2013 The Galette Team
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
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://www.smarty.net/docs/en/installing.smarty.extended.tpl
 * @since     Available since 0.7.1dev - 2012-05-05
 */
class Smarty extends \SmartyBC
{

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
        parent::__construct();

        //paths configuration
        $this->setTemplateDir(GALETTE_ROOT . GALETTE_TPL_SUBDIR);
        $this->setCompileDir(GALETTE_COMPILE_DIR);
        $this->setConfigDir(GALETTE_CONFIG_PATH);
        $this->setCacheDir(GALETTE_CACHE_DIR);

        /*if ( GALETTE_MODE !== 'DEV' ) {
            //enable caching
            $this->caching = \Smarty::CACHING_LIFETIME_CURRENT;
            $this->setCompileCheck(false);
        }*/

        $this->addPluginsDir(GALETTE_ROOT . 'includes/smarty_plugins');

        $this->assign('login', $login);
        $this->assign('logo', $logo);
        $this->assign('template_subdir', GALETTE_BASE_PATH . GALETTE_TPL_SUBDIR);
        foreach ( $plugins->getTplAssignments() as $k=>$v ) {
            $this->assign($k, $v);
        }
        $this->assign('tpl', $this);
        $this->assign('headers', $plugins->getTplHeaders());
        $this->assign('plugin_actions', $plugins->getTplAdhActions());
        $this->assign('plugin_detailled_actions', $plugins->getTplAdhDetailledActions());
        $this->assign('jquery_dir', GALETTE_BASE_PATH . 'includes/jquery/');
        $this->assign('jquery_version', JQUERY_VERSION);
        $this->assign('jquery_migrate_version', JQUERY_MIGRATE_VERSION);
        $this->assign('jquery_ui_version', JQUERY_UI_VERSION);
        $this->assign('jquery_markitup_version', JQUERY_MARKITUP_VERSION);
        $this->assign('jquery_jqplot_version', JQUERY_JQPLOT_VERSION);
        $this->assign('scripts_dir', GALETTE_BASE_PATH . 'includes/');
        $this->assign('PAGENAME', basename($_SERVER['SCRIPT_NAME']));
        $this->assign('galette_base_path', GALETTE_BASE_PATH);
        /** FIXME: on certains pages PHP notice that GALETTE_VERSION does not exists
        although it appears correctly*/
        $this->assign('GALETTE_VERSION', GALETTE_VERSION);
        $this->assign('GALETTE_MODE', GALETTE_MODE);
        /** galette_lang should be removed and languages used instead */
        $this->assign('galette_lang', $i18n->getAbbrev());
        $this->assign('languages', $i18n->getList());
        $this->assign('plugins', $plugins);
        $this->assign('preferences', $preferences);
        $this->assign('pref_slogan', $preferences->pref_slogan);
        $this->assign('pref_theme', $preferences->pref_theme);
        $this->assign('pref_editor_enabled', $preferences->pref_editor_enabled);
        $this->assign('pref_mail_method', $preferences->pref_mail_method);
        $this->assign('existing_mailing', isset($session['mailing']));
        $this->assign('require_tabs', null);
        $this->assign('require_cookie', null);
        $this->assign('contentcls', null);
        $this->assign('require_tabs', null);
        $this->assign('require_cookie', false);
        $this->assign('additionnal_html_class', null);
        $this->assign('require_calendar', null);
        $this->assign('head_redirect', null);
        $this->assign('error_detected', null);
        $this->assign('warning_detected', null);
        $this->assign('success_detected', null);
        $this->assign('color_picker', null);
        $this->assign('require_sorter', null);
        $this->assign('require_dialog', null);
        $this->assign('require_tree', null);
        $this->assign('html_editor', null);
        $this->assign('require_charts', null);
    }
}
