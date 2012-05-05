<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Smarty
 *
 * PHP version 5
 *
 * Copyright © 2012 The Galette Team
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
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.1dev - 2012-05-05
 */

namespace Galette\Core;

/**
 * Zend_Db wrapper
 *
 * @category  Classes
 * @name      Smarty
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://www.smarty.net/docs/en/installing.smarty.extended.tpl
 * @since     Available since 0.7.1dev - 2012-05-05
 */
class Smarty extends \SmartyBC
{

    /**
    * Main constructor
    */
    function __construct($base_path)
    {
        global $plugins, $i18n, $preferences, $logo, $login, $template_subdir;

        parent::__construct();

        $this->muteExpectedErrors();

        //paths configuration
        $this->setTemplateDir(WEB_ROOT . $template_subdir);
        $this->setCompileDir(GALETTE_COMPILE_DIR);
        $this->setConfigDir(WEB_ROOT . 'config/');
        $this->setCacheDir(GALETTE_CACHE_DIR);

        /*if ( GALETTE_MODE !== 'DEV' ) {
            //enable caching
            $this->caching = \Smarty::CACHING_LIFETIME_CURRENT;
            $this->setCompileCheck(false);
        }*/

        $this->addPluginsDir(WEB_ROOT . 'includes/smarty_plugins');

        $this->assign('login', $login);
        $this->assign('logo', $logo);
        $this->assign('template_subdir', $base_path . $template_subdir);
        foreach ( $plugins->getTplAssignments() as $k=>$v ) {
            $this->assign($k, $v);
        }
        $this->assign('headers', $plugins->getTplHeaders());
        $this->assign('plugin_actions', $plugins->getTplAdhActions());
        $this->assign('plugin_detailled_actions', $plugins->getTplAdhDetailledActions());
        $this->assign('jquery_dir', $base_path . 'includes/jquery/');
        $this->assign('jquery_version', JQUERY_VERSION);
        $this->assign('jquery_ui_version', JQUERY_UI_VERSION);
        $this->assign('jquery_markitup_version', JQUERY_MARKITUP_VERSION);
        $this->assign('scripts_dir', $base_path . 'includes/');
        $this->assign('PAGENAME', basename($_SERVER['SCRIPT_NAME']));
        $this->assign('galette_base_path', $base_path);
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
        if ( isset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['mailing']) ) {
            $this->assign('existing_mailing', true);
        }
    }
}
?>
