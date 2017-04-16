<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Plugins database initialization
 *
 * This page can be loaded directly, or via ajax.
 * Via ajax, we do not have a full html page, but only
 * that will be displayed using javascript on another page
 *
 * PHP version 5
 *
 * Copyright © 2011-2014 The Galette Team
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
 * @category  Plugins
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2012-12-17
 */

use Analog\Analog;
use Zend\Db\Adapter\Adapter;
use Galette\Core\Install;

require_once 'includes/galette.inc.php';

if ( GALETTE_MODE === 'DEMO' ) {
    Analog::log(
        'Trying to access ajax_plugins_initdb.php in DEMO mode.',
        Analog::WARNING
    );
    die();
}
if ( !$login->isLogged() || !$login->isAdmin() ) {
    Analog::log(
        'Trying to display plugins installation without appropriate permissions',
        Analog::INFO
    );
    die();
}

// check for ajax mode
$ajax = ( isset($_POST['ajax']) && $_POST['ajax'] == 'true' ) ? true : false;
$plugid = null;
$plugin = null;
if ( isset($_GET['plugid']) ) {
    $plugid = $_GET['plugid'];
}
if ( isset($_POST['plugid']) ) {
    $plugid = $_POST['plugid'];
}
if ( $plugid !== null ) {
    $plugin = $plugins->getModules($plugid);
}

if ( $plugin === null ) {
    Analog::log(
        'Unable to load plugin `' . $plugid . '`!',
        Analog::URGENT
    );
    die();
}

$step = 1;
$istep = 1;

if ( count($error_detected) == 0 && isset($_POST['install_type']) ) {
    $tpl->assign('install_type', $_POST['install_type']);
    $istep = 2;
}

if ( count($error_detected) == 0 && isset($_POST['install_permsok']) ) {
    if ( $_POST['install_type'] === 'install' ) {
        $istep = 4;
    } else {
        $istep = 3;
    }
}

if ( count($error_detected) == 0 && isset($_POST['previous_version']) ) {
    $istep = 4;
}

if ( count($error_detected) == 0 && isset($_POST['install_dbwrite_ok']) ) {
    $istep = 5;
}

if ( $istep > 1 && $_POST['install_type'] == 'install' ) {
    $step = 'i' . $istep;
} elseif ( $istep > 1 && substr($_POST['install_type'], 0, 7) == 'upgrade' ) {
    $step = 'u' . $istep;
}

switch ( $step ){
case '1':
    $title = _T("Installation mode");
    //let's look for updates scripts
    $update_scripts = Install::getUpdateScripts($plugin['root'], TYPE_DB);
    if ( count($update_scripts) > 0 ) {
        $tpl->assign('update_scripts', $update_scripts);
    }
    break;
case 'i2':
case 'u2':
    $install = new Install();
    if ( !defined('GALETTE_THEME_DIR') ) {
        define('GALETTE_THEME_DIR', GALETTE_TPL_SUBDIR);
    }
    $title = _T("Database access and permissions");
    /** FIXME: when tables already exists and DROP not allowed at this time
    the showed error is about CREATE, whenever CREATE is allowed */
    //We delete the table if exists, no error at this time
    $zdb->dropTestTable();

    $results = $zdb->grantCheck(substr($step, 0, 1));

    $result = array();
    $error = false;

    //test returned values
    if ( $results['create'] instanceof Exception ) {
        $result[] = array(
            'message'   => _T("CREATE operation not allowed"),
            'debug'     => $results['create']->getMessage(),
            'image'     => $install->getValidationImage(false)
        );
        $error = true;
    } elseif ( $results['create'] != '' ) {
        $result[] = array(
            'message'   => _T("CREATE operation allowed"),
            'image'     => $install->getValidationImage(true)
        );
    }

    if ( $results['insert'] instanceof Exception ) {
        $result[] = array(
            'message'   => _T("INSERT operation not allowed"),
            'debug'     => $results['insert']->getMessage(),
            'image'     => $install->getValidationImage(false)
        );
        $error = true;
    } elseif ( $results['insert'] != '' ) {
        $result[] = array(
            'message'   => _T("INSERT operation allowed"),
            'image'     => $install->getValidationImage(true)
        );
    }

    if ( $results['update'] instanceof Exception ) {
        $result[] = array(
            'message'   => _T("UPDATE operation not allowed"),
            'debug'     => $results['update']->getMessage(),
            'image'     => $install->getValidationImage(false)
        );
        $error = true;
    } elseif ( $results['update'] != '' ) {
        $result[] = array(
            'message'   => _T("UPDATE operation allowed"),
            'image'     => $install->getValidationImage(true)
        );
    }

    if ( $results['select'] instanceof Exception ) {
        $result[] = array(
            'message'   => _T("SELECT operation not allowed"),
            'debug'     => $results['select']->getMessage(),
            'image'     => $install->getValidationImage(false)
        );
        $error = true;
    } elseif ( $results['select'] != '' ) {
        $result[] = array(
            'message'   => _T("SELECT operation allowed"),
            'image'     => $install->getValidationImage(true)
        );
    }

    if ( $results['delete'] instanceof Exception ) {
        $result[] = array(
            'message'   => _T("DELETE operation not allowed"),
            'debug'     => $results['delete']->getMessage(),
            'image'     => $install->getValidationImage(false)
        );
        $error = true;
    } elseif ( $results['delete'] != '' ) {
        $result[] = array(
            'message'   => _T("DELETE operation allowed"),
            'image'     => $install->getValidationImage(true)
        );
    }

    if ( $results['drop'] instanceof Exception ) {
        $result[] = array(
            'message'   => _T("DROP operation not allowed"),
            'debug'     => $results['drop']->getMessage(),
            'image'     => $install->getValidationImage(false)
        );
        $error = true;
    } elseif ( $results['drop'] != '' ) {
        $result[] = array(
            'message'   => _T("DROP operation allowed"),
            'image'     => $install->getValidationImage(true)
        );
    }

    if ( $step === 'u2' ) {
        if ( $results['alter'] instanceof Exception ) {
            $result[] = array(
                'message'   => _T("ALTER operation not allowed"),
                'debug'     => $results['alter']->getMessage(),
                'image'     => $install->getValidationImage(false)
            );
            $error = true;
        } elseif ( $results['alter'] != '' ) {
            $result[] = array(
                'message'   => _T("ALTER operation allowed"),
                'image'     => $install->getValidationImage(true)
            );
        }
    }

    if ( $error === true ) {
        if ( $step === 'i2' ) {
            $error_detected[] = _T("GALETTE hasn't got enough permissions on the database to continue the installation.");
        } else {
            $error_detected[] = _T("GALETTE hasn't got enough permissions on the database to continue the update.");
        }
    } else {
        $success_detected[] = _T("Permissions to database are OK.");
    }
    $tpl->assign('result', $result);
    break;
case 'u3':
    $update_scripts = Install::getUpdateScripts($plugin['root'], TYPE_DB);
    $tpl->assign('update_scripts', $update_scripts);
    $title = _T("Previous version selection");
    break;
case 'i4':
case 'u4':
    if ( $step == 'i4' ) {
        $title = _T("Creation of the tables");
    } else {
        $title = _T("Update of the tables");
    }
    // begin : copyright (2002) the phpbb group (support@phpbb.com)
    // load in the sql parser
    include GALETTE_ROOT . 'includes/sql_parse.php';
    if ( $step == 'u4' ) {
        $update_scripts = Install::getUpdateScripts(
            $plugin['root'],
            TYPE_DB,
            $_POST['previous_version']
        );
    } else {
        $update_scripts['current'] = TYPE_DB . '.sql';
    }

    $sql_query = '';
    foreach ($update_scripts as $key => $val) {
        $sql_query .= @fread(
            @fopen($plugin['root'] . '/scripts/' . $val, 'r'),
            @filesize($plugin['root'] . '/scripts/' . $val)
        );
        $sql_query .= "\n";
    }

    $sql_query = preg_replace('/galette_/', PREFIX_DB, $sql_query);
    $sql_query = remove_remarks($sql_query);

    $sql_query = split_sql_file($sql_query, ';');

    for ( $i = 0; $i < sizeof($sql_query); $i++ ) {
        $query = trim($sql_query[$i]);
        if ( $query != '' && $query[0] != '-' ) {
            //some output infos
            @list($w1, $w2, $w3, $extra) = explode(' ', $query, 4);
            if ($extra != '') {
                $extra = '...';
            }
            try {
                $zdb->db->query(
                    $query,
                    Adapter::QUERY_MODE_EXECUTE
                );
                $success_detected[] = $w1 . ' ' . $w2 . ' ' . $w3 .
                    ' ' . $extra;
            } catch (Exception $e) {
                Analog::log(
                    'Error executing query | ' . $e->getMessage() .
                    ' | Query was: ' . $query,
                    Analog::WARNING
                );
                if ( (strcasecmp(trim($w1), 'drop') != 0)
                    && (strcasecmp(trim($w1), 'rename') != 0)
                ) {
                    $error_detected[] = $w1 . ' ' . $w2 . ' ' . $w3 . ' ' . $extra;
                    $error_detected[] = $e->getMessage() . '<br/>(' . $query  . ')';
                } else {
                    //if error are on drop, DROP, rename or RENAME we can continue
                    $warning_detected[] = $w1 . ' ' . $w2 . ' ' . $w3 . ' ' . $extra;
                    $warning_detected[] = $e->getMessage() . '<br/>(' . $query  . ')';
                }
            }
        }
    }
    break;
case 'i5':
case 'u5':
    if ( $step == 'i5' ) {
        $title = _T("Installation complete !");
    } else {
        $title = _T("Update complete !");
    }
    break;
}

$tpl->assign('ajax', $ajax);
$tpl->assign('step', $step);
$tpl->assign('istep', $istep);
$tpl->assign('plugid', $plugid);
$tpl->assign('plugin', $plugin);
$tpl->assign('page_title', $title);


$tpl->assign('error_detected', $error_detected);
$tpl->assign('success_detected', $success_detected);

if ( $ajax ) {
    $tpl->assign('mode', 'ajax');
    $tpl->display('ajax_plugins_initdb.tpl');
} else {
    $content = $tpl->fetch('ajax_plugins_initdb.tpl');
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
}
