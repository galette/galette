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
 * Copyright © 2011-2013 The Galette Team
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
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2012-12-17
 */

use Analog\Analog as Analog;
use Galette\Core\Db as Db;

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
        'Trying to display ajax_members.php without appropriate permissions',
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
    $istep = 3;
}

if ( count($error_detected) == 0 && isset($_POST['install_dbwrite_ok']) ) {
    $istep = 4;
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
    $update_scripts = Db::getUpdateScripts($plugin['root'], TYPE_DB);
    if ( count($update_scripts) > 0 ) {
        $tpl->assign('update_scripts', $update_scripts);
    }
    break;
case 'i2':
case 'u2':
    $title = _T("Permissions on the base");
    /** FIXME: when tables already exists and DROP not allowed at this time
    the showed error is about CREATE, whenever CREATE is allowed */
    //We delete the table if exists, no error at this time
    $zdb->dropTestTable();

    $results = $zdb->grantCheck(substr($step, 0, 1));

    $result = '';
    $error = false;
    //test returned values
    if ( $results['create'] instanceof Exception ) {
        $error_detected[] = _T("CREATE operation not allowed");
        $error_detected[] = $results['create']->getMessage();
    } elseif ( $results['create'] != '' ) {
        $success_detected[] = _T("CREATE operation allowed");
    }

    if ( $results['insert'] instanceof Exception ) {
        $error_detected[] = _T("INSERT operation not allowed");
        $error_detected[] = $results['insert']->getMessage();
    } elseif ( $results['insert'] != '' ) {
        $success_detected[] = _T("INSERT operation allowed");
    }

    if ( $results['update'] instanceof Exception ) {
        $error_detected[] = _T("UPDATE operation not allowed");
        $error_detected[] = $results['update']->getMessage();
    } elseif ( $results['update'] != '' ) {
        $success_detected[] = _T("UPDATE operation allowed");
    }

    if ( $results['select'] instanceof Exception ) {
        $error_detected[] = _T("SELECT operation not allowed");
        $error_detected[] = $results['select']->getMessage();
    } elseif ( $results['select'] != '' ) {
        $success_detected[] = _T("SELECT operation allowed");
    }

    if ( $results['delete'] instanceof Exception ) {
        $error_detected[] = _T("DELETE operation not allowed");
        $error_detected[] = $results['delete']->getMessage();
    } elseif ( $results['delete'] != '' ) {
        $success_detected[] = _T("DELETE operation allowed");
    }

    if ( $results['drop'] instanceof Exception ) {
        $error_detected[] = _T("DROP operation not allowed");
        $error_detected[] = $results['drop']->getMessage();
    } elseif ( $results['drop'] != '' ) {
        $success_detected[] = _T("DROP operation allowed");
    }

    if ( $step == 'u2' ) {
        if (  $results['alter'] instanceof Exception ) {
            $error_detected[] = _T("ALTER Operation not allowed");
            $error_detected[] = $results['alter']->getMessage();
        } elseif ( $results['alter'] != '' ) {
            $success_detected[] = _T("ALTER Operation allowed");
        }
    }
    break;
case 'i3':
case 'u3':
    if ( $step == 'i3' ) {
        $title = _T("Creation of the tables");
    } else {
        $title = _T("Update of the tables");
    }
    // begin : copyright (2002) the phpbb group (support@phpbb.com)
    // load in the sql parser
    include GALETTE_ROOT . 'includes/sql_parse.php';
    if ( $step == 'u3' ) {
        $update_scripts = Db::getUpdateScripts(
            $plugin['root'],
            TYPE_DB,
            substr($_POST['install_type'], 8)
        );
    } else {
        $update_scripts['current'] = TYPE_DB . '.sql';
    }

    $sql_query = '';
    while (list($key, $val) = each($update_scripts) ) {
        $sql_query .= @fread(
            @fopen($plugin['root'] . '/sql/' . $val, 'r'),
            @filesize($plugin['root'] . '/sql/' . $val)
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
                $result = $zdb->db->getConnection()->exec($query);
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
case 'i4':
case 'u4':
    if ( $step == 'i4' ) {
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
