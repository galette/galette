<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Main Galette initialisation
 *
 * PHP version 5
 *
 * Copyright © 2004-2013 The Galette Team
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
 * @category  Main
 * @package   Galette
 *
 * @author    Frédéric Jacquot <unknown@unknwown.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

//set a flag saying we work from installer
//that way, in galette.inc.php, we'll only include relevant parts
$installer = true;
$logfile = 'galette_install';
define('GALETTE_BASE_PATH', '../');

require_once '../includes/galette.inc.php';

if ( defined('PREFIX_DB') && defined('NAME_DB') ) {
    unset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]);
}

if ( !defined('GALETTE_TPL_SUBDIR') ) {
    define('GALETTE_TPL_SUBDIR', 'templates/default/');
}

$session = array();

$step = '1';
$error_detected = false;

// traitement page 1 - language
if ( isset($_POST['pref_lang']) ) {
    $step = '2';
}

if ( $error_detected == '' && isset($_POST['install_type']) ) {
    if ( $_POST['install_type'] == 'install' ) {
        $step = 'i3';
    } elseif ( substr($_POST['install_type'], 0, 7) == 'upgrade' ) {
        $step = 'u3';
    } else {
        $error_detected .= '<li>' . _T("Installation mode unknown") . '</li>';
    }
}

if ( $error_detected == '' && isset($_POST['install_permsok']) ) {
    define('GALETTE_LOGGER_CHECKED', true);
    if ( $_POST['install_type'] == 'install' ) {
        $step = 'i4';
    } elseif ( substr($_POST['install_type'], 0, 7) == 'upgrade' ) {
        $step = 'u4';
    } else {
        $error_detected .= '<li>' . _T("Installation mode unknown") . '</li>';
    }
}

if ( $error_detected == ''
    && isset($_POST['install_dbtype'])
) {
    if ( $_POST['install_dbtype'] != 'mysql'
        && $_POST['install_dbtype'] != 'pgsql'
        && $_POST['install_dbtype'] != 'sqlite'
    ) {
            $error_detected .= '<li>' . _T("Database type unknown") . '</li>';
    }

    if ($_POST['install_dbtype'] != 'sqlite') {
        if ( empty($_POST['install_dbhost']) ) {
            $error_detected .= '<li>' . _T("No host") . '</li>';
        }
        if ( empty($_POST['install_dbport']) ) {
            $error_detected .= '<li>' . _T("No port") . '</li>';
        }
        if ( empty($_POST['install_dbuser']) ) {
            $error_detected .= '<li>' . _T("No user name") . '</li>';
        }
        if ( empty($_POST['install_dbpass']) ) {
            $error_detected .= '<li>' . _T("No password") . '</li>';
        }
        if ( empty($_POST['install_dbname']) ) {
                $error_detected .= '<li>' . _T("No database name") . '</li>';
        }
    }

    if ($error_detected == '') {
        if ( isset($_POST['install_dbconn_ok']) ) {

            $dsn['TYPE_DB'] = $_POST['install_dbtype'];

            if ($dsn['TYPE_DB'] != 'sqlite') {
                $dsn['USER_DB'] = $_POST['install_dbuser'];
                $dsn['PWD_DB'] = $_POST['install_dbpass'];
                $dsn['HOST_DB'] = $_POST['install_dbhost'];
                $dsn['PORT_DB'] = $_POST['install_dbport'];
                $dsn['NAME_DB'] = $_POST['install_dbname'];
            }

            $zdb = new Galette\Core\Db($dsn);

            if ( $_POST['install_type'] == 'install' ) {
                $step = 'i6';
            } elseif ( substr($_POST['install_type'], 0, 7) == 'upgrade' ) {
                $step = 'u6';
            }

            if ( isset($_POST['install_dbperms_ok']) ) {
                if ( $_POST['install_type'] == 'install' ) {
                    $step='i7';
                } elseif ( substr($_POST['install_type'], 0, 7) == 'upgrade' ) {
                    $step = 'u7';
                }
            }

            if ( isset($_POST["install_dbwrite_ok"]) ) {
                if ( $_POST['install_type'] == 'install' ) {
                    $step = 'i8';
                } elseif ( substr($_POST['install_type'], 0, 7) == 'upgrade' ) {
                    $step = 'u8';
                }
            }

            if ( isset($_POST['install_adminlogin'])
                && isset($_POST['install_adminpass'])
            ) {
                if ( $_POST['install_adminlogin'] == '' ) {
                    $error_detected .= "<li class=\"install-bad\">" .
                        _T("No user name") . "</li>";
                }
                if ( strpos($_POST['install_adminlogin'], '@') != false ) {
                    $error_detected[] = "<li class=\"install-bad\">" .
                        _T("The username cannot contain the @ character") . "</li>";
                }
                if ( $_POST['install_adminpass'] == '' ) {
                    $error_detected .= "<li class=\"install-bad\">" .
                        _T("No password") . "</li>";
                }
                if ( ! isset($_POST['install_passwdverified'])
                    && strcmp(
                        $_POST['install_adminpass'],
                        $_POST['install_adminpass_verif']
                    )
                ) {
                    $error_detected .= "<li class=\"install-bad\">" .
                        _T("Passwords mismatch") . "</li>";
                }
                if ( $error_detected == '') {
                    if ( $_POST['install_type'] == 'install' ) {
                        $step = 'i9';
                    } elseif ( substr($_POST['install_type'], 0, 7) == 'upgrade' ) {
                        $step = 'u9';
                    }
                }

                if ( isset($_POST['install_prefs_ok']) ) {
                    if ( $_POST['install_type'] == 'install' ) {
                        $step = 'i10';
                    } elseif ( substr($_POST['install_type'], 0, 7) == 'upgrade') {
                        $step="u10";
                    }
                }
            }
        } elseif ( isset($_POST['install_dbko']) && $_POST['install_dbko'] == 1 ) {
            $step = ($_POST['install_type'] == 'install') ? 'i4' : 'u4';
        } else {
            $step = 'i5';
        }
    }
}

//we set current step title
switch ( $step ) {
case '1':
    $step_title = _T("Language");
    break;
case '2':
    $step_title = _T("Installation mode");
    break;
case 'i3':
case 'u3':
    $step_title = _T("Checks");
    break;
case 'i4':
case 'u4':
    $step_title = _T("Database");
    break;
case 'i5':
case 'u5':
    $step_title = _T("Access to the database");
    break;
case 'i6':
case 'u6':
    $step_title = _T("Access permissions to database");
    break;
case 'i7':
case 'u7':
    $step_title = _T("Tables Creation/Update");
    break;
case 'i8':
case 'u8':
    $step_title = _T("Admin parameters");
    break;
case 'i9':
case 'u9':
    $step_title = _T("Saving the parameters");
    break;
case 'i10':
case 'u10':
    $step_title = _T("End!");
    break;
}
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $i18n->getAbbrev(); ?>">
    <head>
        <title><?php echo _T("Galette Installation") . ' - ' . $step_title; ?></title>
        <meta charset="UTF-8"/>
        <link rel="stylesheet" type="text/css" href="<?php echo GALETTE_BASE_PATH; ?>templates/default/galette.css"/>
        <link rel="stylesheet" type="text/css" href="<?php echo GALETTE_BASE_PATH; ?>templates/default/install.css"/>
        <link rel="stylesheet" type="text/css" href="<?php echo GALETTE_BASE_PATH; ?>templates/default/jquery-ui/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>.custom.css"/>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery-<?php echo JQUERY_VERSION; ?>.min.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery-migrate-<?php echo JQUERY_MIGRATE_VERSION; ?>.min.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.widget.min.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.button.min.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery-ui-<?php echo JQUERY_UI_VERSION; ?>/jquery.ui.tooltip.min.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/jquery/jquery.bgFade.js"></script>
        <script type="text/javascript" src="<?php echo GALETTE_BASE_PATH; ?>includes/common.js"></script>
        <link rel="shortcut icon" href="<?php echo GALETTE_BASE_PATH; ?>templates/default/images/favicon.png" />
        <script type="text/javascript">
            $(function() {
<?php
if ($step == '1') { ?>
                $('#pref_lang').change(function() {
                    this.form.submit();
                });
    <?php
}
?>
            });
        </script>
        <!--[if lt IE9]>
            <script type="text/javascript" src="{$scripts_dir}html5-ie.js"></script>
        <!endif]-->
    </head>
    <body>
        <section>
            <header>
                <h1 id="titre">
                    <img src="<?php echo GALETTE_BASE_PATH; ?>templates/default/images/galette.png" alt="[ Galette ]" />
                    <?php echo _T("Galette installation") . ' - ' . $step_title ?>
                </h1>
            </header>
            <div>
    <?php
switch ( $step ) {
case '1':
    ?>
            <h2><?php echo _T("Welcome to the Galette Install!"); ?></h2>
            <form action="index.php" method="post">
                <p><label for="pref_lang"><?php echo _T("Please select your administration language"); ?></label></p>
                <p>
                    <select name="pref_lang" id="pref_lang" required>
                    <option value=""><?php echo _T("Choose language"); ?></option>
    <?php
    foreach ( $i18n->getList() as $langue ) {
        echo "\t\t\t\t\t<option value=\"" . $langue->getID() .
            "\" style=\"background:url(" . $langue->getFlag() .
            ") no-repeat;padding-left:30px;\"" .
            (($i18n->getID()  == $langue->getID())?" selected=\"selected\"":"") .
            ">" . ucwords($langue->getName()) . "</option>\n";
    }
    ?>
                    </select>
                </p>
                <p id="btn_box"><input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/></p>
            </form>
    <?php
    break; //ends first step
case '2':
    ?>
            <h2><?php echo _T("Installation mode"); ?></h2>
            <p><?php echo _T("Select installation mode to launch"); ?></p>
            <form action="index.php" method="post">
                <p>
                    <input type="radio" name="install_type" value="install" checked="checked" id="install"/> <label for="install"><?php echo _T("New installation:"); ?></label><br />
                    <?php echo _T("You're installing Galette for the first time, or you wish to erase an older version of Galette without keeping your data"); ?>
                </p>
    <?php
    $update_scripts = Galette\Core\Db::getUpdateScripts('./');

    $dh = opendir("sql");
    $last = "0.00";
    if ( count($update_scripts) > 0 ) {
        echo "<p>" . _T("Update") . '<br/><span id="warningbox">' .
            _T("Warning: Don't forget to backup your current database.") .
            "</span></p>\n\t\t\t\t<ul class=\"list\">";
    }
    while ( list($key, $val) = each($update_scripts) ) {
        ?>
                <li>
                    <input type="radio" name="install_type" value="upgrade-<?php echo $val; ?>" id="upgrade-<?php echo $val; ?>"/> <label for="upgrade-<?php echo $val; ?>">
        <?php
        if ( $last != number_format($val - 0.01, 2) ) {
            echo _T("Your current Galette version is comprised between") . " " .
                $last . " " . _T("and") . " " . number_format($val - 0.01, 2) .
                "</label><br />";
        } else {
            echo _T("Your current Galette version is") . " " .
                number_format($val - 0.01, 2) . "</label>";
        }
        $last = $val;
        ?>
                </li>
    <?php
    }
    if ( count($update_scripts) > 0 ) {
        echo "\t\t\t\t</ul>";
    }
    ?>
                <p id="btn_box">
                    <input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/>
                </p>
            </form>
    <?php
    break; //ends second step
case 'i3':
case 'u3':
    $php_ok = true;
    $class = 'install-';
    $php_class = '';
    $php_modules_class = '';
    $php_date_class = '';
    $files_perms_class = '';

    // check required PHP version...
    if ( version_compare(PHP_VERSION, GALETTE_PHP_MIN, '<') ) {
        $php_ok = false;
        $php_class .= $class . 'bad';
    } else {
        $php_class .= $class . 'ok';
    }
    ?>
            <article id="php_version" class="<?php echo $php_class; ?>">
                <header>
                    <h2><?php echo _T("PHP Version"); ?></h2>
                </header>
                <?php
    if ( $php_ok !== true ) {
        $msg = '<p class="error">';
        $msg .= str_replace(
            '%ver',
            GALETTE_PHP_MIN,
            _T("Galette requires at least PHP version %ver!")
        );
        $msg .= '</p>';
        echo $msg;
    }

    echo str_replace('%version', PHP_VERSION, _T("PHP version %version"));
                ?>
            </article>
    <?php
    // check date settings
    $date_ok = false;
    if ( !version_compare(PHP_VERSION, '5.2.0', '<') ) {
        try {
            $test_date = new DateTime();
            $date_ok = true;
        } catch ( Exception $e ) {
            //do nothing
        }
        if ( $date_ok !== true ) {
            $php_date_class = $class . 'bad';
        } else {
            $php_date_class = $class . 'ok';
        }
    }
    ?>
            <article id="php_date" class="<?php echo $php_date_class; ?>">
                <header>
                    <h2><?php echo _T("Date settings"); ?></h2>
                </header>
                <div>
    <?php
    if ( !$date_ok ) {
        echo '<p class="error">' . _T("Your PHP date settings are not correct. Maybe you've missed the timezone settings that is mandatory since PHP 5.3?") . '</p>';
    } else {
        echo '<p>' . _T("Your PHP date settings seem correct.") . '</p>';
    }
    ?>
                </div>
            </article>
    <?php
    // check PHP modules
    $cm = new Galette\Core\CheckModules();
    $modules_ok = $cm->isValid();

    if ( $modules_ok !== true ) {
        $php_modules_class = $class . 'bad';
    } else {
        $php_modules_class = $class . 'ok';
    }
    ?>
            <article id="php_modules" class="<?php echo $php_modules_class; ?>">
                <header>
                    <h2><?php echo _T("PHP Modules"); ?></h2>
                </header>
                <div>
    <?php
    if ( !$modules_ok ) {
        echo '<p class="error">' . _T("Some PHP modules are missing. Please install them or contact your support.<br/>More informations on required modules may be found in the documentation.")  . '</p>';
    }
    echo $cm->toHtml();
    ?>
                </div>
           </article>
    <?php
    // check file permissions
    $perms_ok = true;
    $files_need_rw = array (
        GALETTE_COMPILE_DIR,
        GALETTE_PHOTOS_PATH,
        GALETTE_CACHE_DIR,
        GALETTE_TEMPIMAGES_PATH,
        GALETTE_CONFIG_PATH,
        GALETTE_EXPORTS_PATH,
        GALETTE_IMPORTS_PATH,
        GALETTE_LOGS_PATH
    );

    $files_perms_class = $class . 'ok';
    $files = '';
    foreach ($files_need_rw as $file) {
        if ( !is_writable($file) ) {
            $perms_ok = false;
            $files_perms_class = $class . 'bad';
            $files .= '<li class="install-bad">' . $file . '</li>';
        } else {
            $files .= '<li class="install-ok">' . $file . '</li>';
        }
    }
    ?>
        <article id="files_perms" class="<?php echo $files_perms_class; ?>">
            <header>
                <h2><?php echo _T("Files permissions"); ?></h2>
            </header>
            <div>
    <?php
    if ( !$perms_ok ) {
        ?>
            <h3 class="error"><?php echo _T("Files permissions are not OK!"); ?></h3>
            <p><?php
        if ($step == 'i3') echo _T("To work as excpected, Galette needs write permission on files listed above.");
        if ($step == 'u3') echo _T("In order to be updated, Galette needs write permission on files listed above."); 
            ?></p>
            <p><?php echo _T("Under UNIX/Linux, you can give the permissions using those commands"); ?><br />
                <code>chown <em><?php echo _T("apache_user"); ?></em> <em><?php echo _T("file_name"); ?></em><br />chmod 700 <em><?php echo _T("directory_name"); ?></em></code>
            </p>
            <p><?php echo _T("Under Windows, check these directories are not in Read-Only mode in their property panel."); ?></p>
       <?php
    }
    ?>
                <ul class="list" id="paths"><?php echo $files; ?></ul>
            </div>
        </article>
    <?php
    if ( !$perms_ok || !$modules_ok || !$php_ok || !$date_ok ) {
        ?>
                <form action="index.php" method="post">
                    <p id="btn_box">
                        <input type="submit" id="retry_btn" value="<?php echo _T("Retry"); ?>"/>
                        <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    </p>
                </form>
    <?php
    } else {
        ?>
            <form action="index.php" method="POST">
                <p id="btn_box">
                    <input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/>
                    <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    <input type="hidden" name="install_permsok" value="1"/>
                </p>
            </form>
        <?php
    }
    break; //ends third step
case 'i4':
case 'u4':
    ?>
            <h2><?php echo _T("Database"); ?></h2>
    <?php
    if ( $error_detected != '' ) {
        ?>
            <div id="errorbox">
                <h1><?php echo _T("- ERROR -"); ?></h1>
                <ul><?php echo $error_detected; ?></ul>
            </div>
        <?php
    }
    ?>
            <p><?php
    if ( $step == 'i4' ) {
        echo _T("If it hadn't been made, create a database and a user for Galette.");
    }
    if ( $step == 'u4' ) {
        echo _T("Enter connection data for the existing database.");

        if ( file_exists(GALETTE_CONFIG_PATH . 'config.inc.php') ) {
            $conf = file_get_contents(GALETTE_CONFIG_PATH . 'config.inc.php');
            if ( $conf !== false ) {
                if ( !isset($_POST['install_dbtype']) ) {
                    $res = preg_match(
                        '/TYPE_DB", "(.*)"\);/',
                        $conf,
                        $matches
                    );
                    if ( $matches[1] ) {
                        $_POST['install_dbtype'] = $matches[1];
                    }
                }
                if ( !isset($_POST['install_dbhost']) ) {
                    $res = preg_match(
                        '/HOST_DB", "(.*)"\);/',
                        $conf,
                        $matches
                    );
                    if ( $matches[1] ) {
                        $_POST['install_dbhost'] = $matches[1];
                    }
                }
                if ( !isset($_POST['install_dbuser']) ) {
                    $res = preg_match(
                        '/USER_DB", "(.*)"\);/',
                        $conf,
                        $matches
                    );
                    $_POST['install_dbuser'] = $matches[1];
                }
                if ( !isset($_POST['install_dbname']) ) {
                    $res = preg_match(
                        '/NAME_DB", "(.*)"\);/',
                        $conf,
                        $matches
                    );
                    if ( $matches[1] ) {
                        $_POST['install_dbname'] = $matches[1];
                    }
                }
                if ( !isset($_POST['install_dbprefix']) ) {
                    $res = preg_match(
                        '/PREFIX_DB", "(.*)"\);/',
                        $conf,
                        $matches
                    );
                    if ( $matches[1] ) {
                        $_POST['install_dbprefix'] = $matches[1];
                    }
                }
            }
        }
    }

    //define default database port
    $default_dbport = Galette\Core\Db::MYSQL_DEFAULT_PORT;
    if ( !isset($_POST['install_dbtype']) || $_POST['install_dbtype'] == 'mysql' ) {
        $default_dbport = Galette\Core\Db::MYSQL_DEFAULT_PORT;
    } else if ( $_POST['install_dbtype'] == 'pgsql' ) {
        $default_dbport = Galette\Core\Db::PGSQL_DEFAULT_PORT;
    }
    ?><br />
            <?php echo _T("The needed permissions are CREATE, DROP, DELETE, UPDATE, SELECT and INSERT."); ?></p>
            <form action="index.php" method="post">
                <fieldset class="cssform">
                    <legend class="ui-state-active ui-corner-top"><?php echo _T("Database"); ?></legend>
                    <p>
                        <label class="bline" for="install_dbtype"><?php echo _T("Database type:"); ?></label>
                        <select name="install_dbtype" id="install_dbtype">
                            <option value="mysql"<?php if ( isset($_POST['install_dbtype']) && $_POST['install_dbtype'] == 'mysql' ) {echo ' selected="selected"';} ?>>Mysql</option>
                            <option value="pgsql"<?php if ( isset($_POST['install_dbtype']) && $_POST['install_dbtype'] == 'pgsql' ) {echo ' selected="selected"';} ?>>Postgresql</option>
                            <option value="sqlite"<?php if ( isset($_POST['install_dbtype']) && $_POST['install_dbtype'] == 'sqlite' ) {echo ' selected="selected"';} ?>>SQLite</option>
                        </select>
                    </p>
                    <div id="install_dbconfig">
                        <p>
                            <label class="bline" for="install_dbhost"><?php echo _T("Host:"); ?></label>
                            <input type="text" name="install_dbhost" id="install_dbhost" value="<?php echo (isset($_POST['install_dbhost']))?$_POST['install_dbhost']:'localhost'; ?>" required/>
                        </p>
                        <p>
                            <label class="bline" for="install_dbport"><?php echo _T("Port:"); ?></label>
                            <input type="text" name="install_dbport" id="install_dbport" value="<?php echo (isset($_POST['install_dbport']))?$_POST['install_dbport']:$default_dbport; ?>" required/>
                        </p>
                        <p>
                            <label class="bline" for="install_dbuser"><?php echo _T("User:"); ?></label>
                            <input type="text" name="install_dbuser" id="install_dbuser" value="<?php if(isset($_POST['install_dbuser'])) echo $_POST['install_dbuser']; ?>" required/>
                        </p>
                        <p>
                            <label class="bline" for="install_dbpass"><?php echo _T("Password:"); ?></label>
                            <input type="password" name="install_dbpass" id="install_dbpass" value="<?php if(isset($_POST['install_dbpass'])) echo $_POST['install_dbpass']; ?>" required/>
                        </p>
                        <p>
                            <label class="bline" for="install_dbname"><?php echo _T("Database:"); ?></label>
                            <input type="text" name="install_dbname" id="install_dbname" value="<?php if(isset($_POST['install_dbname'])) echo $_POST['install_dbname']; ?>" required/>
                        </p>
                        <p>
        <?php
    if ( substr($_POST['install_type'], 0, 8) == 'upgrade-' ) {
        echo '<span class="required">' .
            _T("(Indicate the CURRENT prefix of your Galette tables)") .
            '</span><br/>';
    }
        ?>
                            <label class="bline" for="install_dbprefix"><?php echo _T("Table prefix:"); ?></label>
                            <input type="text" name="install_dbprefix" id="install_dbprefix" value="<?php echo (isset($_POST['install_dbprefix']))?$_POST['install_dbprefix']:'galette_'; ?>" required/>
                        </p>
                    </div>
                </fieldset>
                <p id="btn_box">
                    <input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/>
                    <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    <input type="hidden" name="install_permsok" value="1"/>
                </p>
            </form>
            <script type="text/javascript">
                $(function(){
                    function changeDbType(type)
                    {
                        if (type == 'sqlite') {
                            $('#install_dbconfig').css('display', 'none');
                            $('#install_dbconfig input').each(function () {
                                $(this).removeAttr('required');
                            })
                        } else {
                            $('#install_dbconfig').css('display', 'block');
                            $('#install_dbconfig input').each(function () {
                                $(this).attr('required', 'required');
                            })
                        }
                    }

                    $('#install_dbtype').change(function(){
                        var _db = $(this).val();
                        var _port = null;
                        if ( _db === 'pgsql' ) {
                            _port = <?php echo Galette\Core\Db::PGSQL_DEFAULT_PORT; ?>;
                        } else if ( _db === 'mysql' ) {
                            _port = <?php echo Galette\Core\Db::MYSQL_DEFAULT_PORT; ?>;
                        }
                        $('#install_dbport').val(_port);
                        changeDbType($(this).val());
                    });

                    changeDbType($('#install_dbtype').val());

                });
            </script>
    <?php
    break; //ends fourth step
case 'i5':
case 'u5':
    ?>
            <h2><?php echo _T("Check of the database"); ?></h2>
            <p><?php echo _T("Check the parameters and the existence of the database"); ?></p>
    <?php
    $permsdb_ok = true;

    if ($_POST['install_dbtype'] == 'sqlite') {
        $test = Galette\Core\Db::testConnectivity(
            $_POST['install_dbtype']
        );
    } else {
        $test = Galette\Core\Db::testConnectivity(
            $_POST['install_dbtype'],
            $_POST['install_dbuser'],
            $_POST['install_dbpass'],
            $_POST['install_dbhost'],
            $_POST['install_dbport'],
            $_POST['install_dbname']
        );
    }

    if ( $test === true ) {
        echo '<p id="infobox">' . _T("Connection to database successfull") . '</p>';
    } else {
        $permsdb_ok = false;
        echo '<div id="errorbox">';
        echo '<h1>' . _T("Unable to connect to the database") . '</h1>';
        echo '<p class="debuginfos">' . $test->getMessage() . '<span>' . $test->getTraceAsString() . '</span></p>';
        echo '</div>';
    }

    if ( !$permsdb_ok ) {
        ?>
            <p><?php echo _T("Database can't be reached. Please go back to enter the connection parameters again."); ?></p>
            <form action="index.php" method="POST">
                <p id="btn_box">
                    <input id="back_btn" type="submit" value="<?php echo _T("Go back"); ?>"/>
                    <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    <input type="hidden" name="install_permsok" value="1"/>
                    <input type="hidden" name="install_dbko" value="1"/>
                    <input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>"/>
                    <input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>"/>
                    <input type="hidden" name="install_dbport" value="<?php echo $_POST['install_dbport']; ?>"/>
                    <input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>"/>
                    <input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>"/>
                    <input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>"/>
                    <input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>"/>
                </p>
            </form>
        <?php
    } else {
        ?>
            <p><?php echo _T("Database exists and connection parameters are OK."); ?></p>
            <form action="index.php" method="POST">
                <p id="btn_box">
                    <input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/>
                    <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    <input type="hidden" name="install_permsok" value="1"/>
                    <input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>"/>
                    <input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>"/>
                    <input type="hidden" name="install_dbport" value="<?php echo $_POST['install_dbport']; ?>"/>
                    <input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>"/>
                    <input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>"/>
                    <input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>"/>
                    <input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>"/>
                    <input type="hidden" name="install_dbconn_ok" value="1"/>
                </p>
            </form>
        <?php
    }
    break; //ends 5th step
case 'i6':
case 'u6':
    ?>
            <h2><?php echo _T("Permissions on the base"); ?></h2>
            <p><?php
    if ( $step == 'i6' ) {
        echo _T("To run, Galette needs a number of rights on the database (CREATE, DROP, DELETE, UPDATE, SELECT and INSERT)");
    }
    if ( $step == 'u6' ) {
        echo _T("In order to be updated, Galette needs a number of rights on the database (CREATE, DROP, DELETE, UPDATE, SELECT and INSERT)");
    }
    ?></p>
    <?php
    /** FIXME: when tables already exists and DROP not allowed at this time
    the showed error is about CREATE, whenever CREATE is allowed */
    //We delete the table if exists, no error at this time
    $zdb->dropTestTable();

    $results = $zdb->grantCheck(substr($step, 0, 1));

    $result = '';
    $error = false;
    //test returned values
    if ( $results['create'] instanceof Exception ) {
        $result .= '<li class="install-bad debuginfos">' .
            _T("CREATE operation not allowed") . '<span>' .
            $results['create']->getMessage() . '</span></li>';
        $error = true;
    } elseif ( $results['create'] != '' ) {
        $result .= '<li class="install-ok">' .
            _T("CREATE operation allowed") . '</li>';
    }

    if ( $results['insert'] instanceof Exception ) {
        $result .= '<li class="install-bad debuginfos">' .
            _T("INSERT operation not allowed") . '<span>' .
            $results['insert']->getMessage() . '</span></li>';
        $error = true;
    } elseif ( $results['insert'] != '' ) {
        $result .= '<li class="install-ok">' .
            _T("INSERT operation allowed") . '</li>';
    }

    if ( $results['update'] instanceof Exception ) {
        $result .= '<li class="install-bad debuginfos">' .
            _T("UPDATE operation not allowed") . '<span>' .
            $results['update']->getMessage() . '</span></li>';
        $error = true;
    } elseif ( $results['update'] != '' ) {
        $result .= '<li class="install-ok">' .
            _T("UPDATE operation allowed") . '</li>';
    }

    if ( $results['select'] instanceof Exception ) {
        $result .= '<li class="install-bad debuginfos">' .
            _T("SELECT operation not allowed") . '<span>' .
            $results['select']->getMessage() . '</span></li>';
        $error = true;
    } elseif ( $results['select'] != '' ) {
        $result .= '<li class="install-ok">' .
            _T("SELECT operation allowed") . '</li>';
    }

    if ( $results['delete'] instanceof Exception ) {
        $result .= '<li class="install-bad debuginfos">' .
            _T("DELETE operation not allowed") . '<span>' .
            $results['delete']->getMessage() . '</span></li>';
        $error = true;
    } elseif ( $results['delete'] != '' ) {
        $result .= '<li class="install-ok">' .
            _T("DELETE operation allowed") . '</li>';
    }

    if ( $results['drop'] instanceof Exception ) {
        $result .= '<li class="install-bad debuginfos">' .
            _T("DROP operation not allowed") . '<span>' .
            $results['drop']->getMessage() . '</span></li>';
        $error = true;
    } elseif ( $results['drop'] != '' ) {
        $result .= '<li class="install-ok">' .
            _T("DROP operation allowed") . '</li>';
    }

    if ($step == 'u6') {
        if ( $results['alter'] instanceof Exception ) {
            $result .= '<li class="install-bad debuginfos">' .
                _T("ALTER Operation not allowed") . '<span>' .
                $results['alter']->getMessage() . '</span></li>';
            $error = true;
        } elseif ( $results['alter'] != '' ) {
            $result .= '<li class="install-ok">' .
                _T("ALTER Operation allowed") . '</li>';
        }
    }

    if ( $error ) {
        echo "<ul>" . $result . "</ul>\n";
        echo '<div id="errorbox">';
        echo '<h1>';
        if ( $step == 'i6' ) {
            echo _T("GALETTE hasn't got enough permissions on the database to continue the installation.");
        }
        if ($step == 'u6') {
            echo _T("GALETTE hasn't got enough permissions on the database to continue the update.");
        }
        echo '</h1>';
        echo '</div>';
        ?>
            <form action="index.php" method="post">
                <p id="btn_box">
                    <input id="retry_btn" type="submit" value="<?php echo _T("Retry"); ?>"/>
                    <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    <input type="hidden" name="install_permsok" value="1"/>
                    <input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>"/>
                    <input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>"/>
                    <input type="hidden" name="install_dbport" value="<?php echo $_POST['install_dbport']; ?>"/>
                    <input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>"/>
                    <input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>"/>
                    <input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>"/>
                    <input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>"/>
                    <input type="hidden" name="install_dbconn_ok" value="1"/>
                </p>
            </form>
        <?php
    } else {
        ?>
            <ul><?php echo $result; ?></ul>
            <p id="infobox"><?php echo _T("Permissions to database are OK."); ?></p>
            <form action="index.php" method="POST">
                <p id="btn_box">
                    <input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/>
                    <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    <input type="hidden" name="install_permsok" value="1"/>
                    <input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>"/>
                    <input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>"/>
                    <input type="hidden" name="install_dbport" value="<?php echo $_POST['install_dbport']; ?>"/>
                    <input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>"/>
                    <input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>"/>
                    <input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>"/>
                    <input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>"/>
                    <input type="hidden" name="install_dbconn_ok" value="1"/>
                    <input type="hidden" name="install_dbperms_ok" value="1"/>
                </p>
            </form>
        <?php
    }
    break; //ends 6th step
case 'i7':
case 'u7':
    ?>
            <h2><?php
    if ( $step == 'i7' ) {
        echo _T("Creation of the tables");
    }
    if ( $step == 'u7' ) {
        echo _T("Update of the tables");
    }
    ?></h2>
            <p><?php
    if ( $step == 'i7' ) {
        echo _T("Installation Report");
    }
    if ( $step == 'u7' ) {
        echo _T("Update Report");
    }
    ?></p>
    <ul>
    <?php
    $table_prefix = $_POST['install_dbprefix'];

    if ( $step == 'u7' ) {
        //before doing anything else, we'll have to convert data to UTF-8
        //required since 0.7dev (if we're upgrading, 'f course)
        $_to_ver = substr($_POST['install_type'], 8);
        if ( (float)$_to_ver <= 0.70 ) {
            $zdb->convertToUTF($table_prefix);
        }
    }

    // begin : copyright (2002) the phpbb group (support@phpbb.com)
    // load in the sql parser
    include '../includes/sql_parse.php';

    if ( $step == 'u7' ) {
        $update_scripts = Galette\Core\Db::getUpdateScripts(
            '.',
            $_POST['install_dbtype'],
            substr($_POST['install_type'], 8)
        );
    } else {
        $update_scripts['current'] = $_POST['install_dbtype'] . '.sql';
    }

    $sql_query = '';
    while (list($key, $val) = each($update_scripts) ) {
        $sql_query .= @fread(@fopen('sql/' . $val, 'r'), @filesize('sql/' . $val)) . "\n";
    }

    $sql_query = preg_replace('/galette_/', $table_prefix, $sql_query);
    $sql_query = remove_remarks($sql_query);

    $sql_query = split_sql_file($sql_query, ';');

    $db = $zdb->db->getConnection();
    $db->beginTransaction();

    for ( $i = 0; $i < sizeof($sql_query); $i++ ) {
        $query = trim($sql_query[$i]);
        if ( $query != '' && $query[0] != '-' ) {
            //some output infos
            $ws = explode(' ', $query, 4);
            $w1 = $ws[0];
            $w2 = '';
            $w3 = '';
            $extra = '';
            if ( isset($ws[1]) ) {
                $w2 = $ws[1];
            }
            if ( isset($ws[2]) ) {
                $w3 = $ws[2];
            }
            if ( isset($ws[3]) ) {
                $extra = $ws[3];
            }
            if ( $extra != '') {
                $extra = '...';
            }
            try {
                $result = $db->exec($query);
                echo '<li class="install-ok">' . $w1 . ' ' . $w2 . ' ' . $w3 .
                    ' ' . $extra . '</li>';
            } catch (Exception $e) {
                \Analog\Analog::log(
                    'Error executing query | ' . $e->getMessage() .
                    ' | Query was: ' . $query,
                    \Analog\Analog::WARNING
                );
                echo '<li class="install-bad debuginfos">' . $w1 . ' ' . $w2 .
                    ' ' . $w3 . ' ' . $extra . '<span>' . $e->getMessage() .
                    '<br/>(' . $query . ')</span></li>';

                //if error are on drop, DROP, rename or RENAME we can continue
                if ( (strcasecmp(trim($w1), 'drop') != 0)
                    && (strcasecmp(trim($w1), 'rename') != 0)
                ) {
                    $error = true;
                }
            }
        }
    }

    if (!empty($error)) {
        $db->rollBack();
    } else {
        $db->commit();
    }

    echo "</ul>\n";
    /**
    * FIXME: is this code util ?
    * shouldn't overlapping fess catched when stored ?
    */
    /** TODO !!!!!!!! */
    // begin: fix overlapping fees
    /*$adh_list = array();
    $query = 'SELECT id_adh from ' . $table_prefix . 'adherents';
    $result = $DB->Execute($query);
    if ( !$result ) {
        print $query . ': ' . $DB->ErrorMsg();
    } else {
        while ( !$result->EOF ) {
            //FIXME Fields deprecated
            $adh_list[] = $result->Fields('id_adh');
            $result->MoveNext();
        }
    }

    foreach ( $adh_list as $id_adh ) {
        $cotis = array();
        $query = "SELECT id_cotis, date_enreg, date_debut_cotis, date_fin_cotis
                from ".$table_prefix."cotisations, ".$table_prefix."types_cotisation
                where ".$table_prefix."cotisations.id_type_cotis = ".$table_prefix."types_cotisation.id_type_cotis
                and ".$table_prefix."types_cotisation.cotis_extension = '1'
                and ".$table_prefix."cotisations.id_adh = '".$id_adh."'
                order by date_enreg;";
        $result = $DB->Execute($query);
        if ( !$result ) {
            print $query . ': ' . $DB->ErrorMsg();
        } else {
            while (!$result->EOF) {
                $c = $result->FetchRow();
                $newc = array('id_cotis' => $c['id_cotis']);
                list($by, $bm, $bd) = explode('-', $c['date_debut_cotis']);
                list($ey, $em, $ed) = explode('-', $c['date_fin_cotis']);
                $newc['start_date'] = mktime(0, 0, 0, $bm, $bd, $by);
                $newc['end_date'] = mktime(0, 0, 0, $em, $ed, $ey);
                if ($bm > $em) {
                    $em += 12;
                    $ey--;
                }
                $newc['duration'] = ($ey -$by)*12 + $em - $bm;
                $cotis[] = $newc;
            }
            $result->Close();
        }
        if ( count($cotis) > 0 ) {
            unset($cprev);
            foreach ( $cotis as $c ) {
                if ( isset($cprev) && $c['start_date'] < $cprev['end_date'] ) {
                    $c['start_date'] = $cprev['end_date'];
                    $start_date = $DB->DBDate($c['start_date']);
                    $new_start_date = localtime($c['start_date'], 1);
                    $c['end_date'] = mktime(0, 0, 0, $new_start_date['tm_mon'] + $c['duration'] + 1, $new_start_date['tm_mday'], $new_start_date['tm_year']);
                    $end_date = $DB->DBDate($c['end_date']);
                    $query = "UPDATE ".$table_prefix."cotisations
                                    SET date_debut_cotis = ".$start_date.",
                                date_fin_cotis = ".$end_date."
                                    WHERE id_cotis = ".$c['id_cotis'];
                    $result = $DB->Execute($query);
                    if ( !$result ) {
                        print $query . ': ' . $DB->ErrorMsg();
                    } else {
                        $result->Close();
                    }
                }
                $cprev = $c;
            }
        }
    }*/
    ?>
            <p><?php echo _T("(Errors on DROP and RENAME operations can be ignored)"); ?></p>
    <?php
    if ( isset($error) ) {
        ?>
            <p id="errorbox"><?php
        if ( $step == 'i7' ) {
            echo _T("The tables are not totally created, it may be a permission problem.");
        }
        if ( $step == 'u7' ) {
            echo _T("The tables have not been totally created, it may be a permission problem.");
            echo '<br/>';
            echo _T("Your database is maybe not usable, try to restore the older version.");
        }
        ?>
            </p>
            <form action="index.php" method="POST">
                <p id="btn_box">
                    <input id="retry_btn" type="submit" value="<?php echo _T("Retry"); ?>"/>
                    <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    <input type="hidden" name="install_permsok" value="1"/>
                    <input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>"/>
                    <input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>"/>
                    <input type="hidden" name="install_dbport" value="<?php echo $_POST['install_dbport']; ?>"/>
                    <input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>"/>
                    <input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>"/>
                    <input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>"/>
                    <input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>"/>
                    <input type="hidden" name="install_dbconn_ok" value="1"/>
                    <input type="hidden" name="install_dbperms_ok" value="1"/>
                </p>
            </form>
    <?php
    } else {
        ?>
            <p id="infobox"><?php
        if ($step=="i7") {
            echo _T("The tables has been correctly created.");
        }
        if ($step=="u7") {
                echo _T("The tables has been correctly updated.");
        } ?></p>
            <form action="index.php" method="POST">
                <p id="btn_box">
                    <input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/>
                    <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    <input type="hidden" name="install_permsok" value="1"/>
                    <input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>"/>
                    <input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>"/>
                    <input type="hidden" name="install_dbport" value="<?php echo $_POST['install_dbport']; ?>"/>
                    <input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>"/>
                    <input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>"/>
                    <input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>"/>
                    <input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>"/>
                    <input type="hidden" name="install_dbconn_ok" value="1"/>
                    <input type="hidden" name="install_dbperms_ok" value="1"/>
                    <input type="hidden" name="install_dbwrite_ok" value="1"/>
                </p>
            </form>
    <?php
    }
    break; //ends 7th step
case 'i8':
case 'u8':
    ?>
            <h2><?php echo _T("Admin settings"); ?></h2>
    <?php
    if ( $error_detected != '' ) {
        echo '<div id="errorbox"><h1>' .
            _T("- ERROR -") . '</h1><ul>' .
            $error_detected . '</ul></div>';
    }
    ?>
            <form action="index.php" method="post">
                <fieldset class="cssform">
                    <legend class="ui-state-active ui-corner-top"><?php echo _T("Please chose the parameters of the admin account on Galette"); ?></legend>
                    <p>
                        <label for="install_adminlogin" class="bline"><?php echo _T("Username:"); ?></label>
                        <input type="text" name="install_adminlogin" id="install_adminlogin" value="<?php if(isset($_POST['install_adminlogin'])) echo $_POST['install_adminlogin']; ?>" required/>
                    </p>
                    <p>
                        <label for="install_adminpass" class="bline"><?php echo _T("Password:"); ?></label>
                        <input type="password" name="install_adminpass" id="install_adminpass" value="" required/>
                    </p>
                    <p>
                        <label for="install_adminpass_verif" class="bline"><?php echo _T("Retype password:"); ?></label>
                        <input type="password" name="install_adminpass_verif" id="install_adminpass_verif" value="" required/>
                    </p>
                </fieldset>
                <p id="btn_box">
                    <input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/>
                    <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    <input type="hidden" name="install_permsok" value="1"/>
                    <input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>"/>
                    <input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>"/>
                    <input type="hidden" name="install_dbport" value="<?php echo $_POST['install_dbport']; ?>"/>
                    <input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>"/>
                    <input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>"/>
                    <input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>"/>
                    <input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>"/>
                    <input type="hidden" name="install_dbconn_ok" value="1"/>
                    <input type="hidden" name="install_dbperms_ok" value="1"/>
                    <input type="hidden" name="install_dbwrite_ok" value="1"/>
                </p>
            </form>
    <?php
    break; //ends 8th step
case 'i9';
case 'u9';
    define('PREFIX_DB', $_POST['install_dbprefix']);

    $oks = array();
    $errs = array();
    ?>
            <h2><?php echo _T("Save the parameters"); ?></h2>
    <?php
            // création du fichier de configuration

    if ( $fd = @fopen(GALETTE_CONFIG_PATH . 'config.inc.php', 'w') ) {
        $data = '<?php
define("TYPE_DB", "' . $_POST['install_dbtype'] . '");
define("HOST_DB", "' . $_POST['install_dbhost'] . '");
define("PORT_DB", "' . $_POST['install_dbport'] . '");
define("USER_DB", "' . $_POST['install_dbuser'] . '");
define("PWD_DB", "' . $_POST['install_dbpass'] . '");
define("NAME_DB", "' . $_POST['install_dbname'] . '");
define("PREFIX_DB", "' . $_POST['install_dbprefix'] . '");
define("STOCK_FILES", "tempimages");
?>';
        fwrite($fd, $data);
        fclose($fd);
        $oks[] =  '<li class="install-ok">' .
            _T("Configuration file created!") . '</li>';
    } else {
        $str = str_replace(
            '%path',
            GALETTE_CONFIG_PATH . 'config.inc.php',
            _T("Unable to create configuration file (%path)")
        );
        $errs[] =  '<li class="install-bad">' . $str . '</li>';
        $error = true;
    }

    $preferences = null;
    if ( $step=='i9' ) {
        $preferences = new Galette\Core\Preferences(false);
        $ct = new Galette\Entity\ContributionsTypes();
        $status = new Galette\Entity\Status();
        include_once '../includes/fields_defs/members_fields.php';
        $fc = new Galette\Entity\FieldsConfig(
            Galette\Entity\Adherent::TABLE,
            $members_fields,
            true
        );
        $titles = new Galette\Repository\Titles();

        //init default values
        $admpass = password_hash($_POST['install_adminpass'], PASSWORD_BCRYPT);

        $res = $preferences->installInit(
            $i18n->getID(),
            $_POST['install_adminlogin'],
            $admpass
        );
        if ( $res !== true ) {
            $errs[] = '<li class="install-bad">' .
                _T("Default preferences cannot be initialized.") .
                '<span>' . $res->getMessage() . '</span></li>';
        } else {
            $oks[] = '<li class="install-ok">' .
                _T("Default preferences were successfully stored.") . '</li>';
        }

        $res = $ct->installInit();
        if ( $res !== true ) {
            $errs[] = '<li class="install-bad">' .
                _T("Default contributions types cannot be initialized.") .
                '<span>' . $res->getMessage() . '</span></li>';
        } else {
            $oks[] = '<li class="install-ok">' .
                _T("Default contributions types were successfully stored.") .
                '</li>';
        }

        $res = $status->installInit();
        if ( $res !== true ) {
            $errs[] = '<li class="install-bad">' .
                _T("Default status cannot be initialized.") .
                '<span>' . $res->getMessage() . '</span></li>';
        } else {
            $oks[] = '<li class="install-ok">' .
                _T("Default status were successfully stored.") . '</li>';
        }

        //proceed fields configuration reinitialization
        $res = $fc->installInit($zdb);
        if ( $res !== true ) {
            $errs[] = '<li class="install-bad">' .
                _T("Default fields configuration cannot be initialized.") .
                '</li>';
        } else {
            $oks[] = '<li class="install-ok">' .
                _T("Default fields configuration was successfully stored.") .
                '</li>';
        }

        $res = $titles->installInit($zdb);
        if ( $res !== true ) {
            $errs[] = '<li class="install-bad">' .
                _T("Titles cannot be initialized.") .
                '<span>' . $res->getMessage() . '</span></li>';
        } else {
            $oks[] = '<li class="install-ok">' .
                _T("Titles were successfully stored.") .
                '</li>';
        }
    } else if ($step=='u9') {
        $_to_ver = substr($_POST['install_type'], 8);
        if ( (float)$_to_ver <= 0.74 ) {
            include_once '../includes/fields_defs/members_fields.php';
            $fc = new Galette\Entity\FieldsConfig(
                Galette\Entity\Adherent::TABLE,
                $members_fields,
                true
            );

            //titles has been initialized by SQL upgrade script
            //but in english. If install language is not english,
            //we have to translate those values.
            $titles = new Galette\Repository\Titles();
            if ( $i18n->getID() != 'en_US' ) {
                $titles_list = $titles->getList($zdb);
                $res = true;
                $zdb->db->beginTransaction();
                foreach ( $titles_list as $title ) {
                    if ( $res == true ) {
                        switch ( $title->short ) {
                        case 'Mr.':
                            $title->short = _T("Mr.");
                            break;
                        case 'Mrs.':
                            $title->short = _T("Mrs.");
                            break;
                        case 'Miss':
                            $title->short = _T("Miss");
                            break;
                        }
                        $res = $title->store($zdb);
                    }
                }
                if ( $res == true ) {
                    $zdb->db->commit();
                } else {
                    $zdb->db->rollBack();
                }
            }

            if ( $res !== true ) {
                $errs[] = '<li class="install-bad">' .
                    _T("Titles cannot be initialized.") .
                    '<span>' . $res->getMessage() . '</span></li>';
            } else {
                $oks[] = '<li class="install-ok">' .
                    _T("Titles were successfully stored.") .
                    '</li>';
            }

            //proceed fields configuration reinitialization
            $res = $fc->installInit($zdb);
            if ( $res !== true ) {
                $errs[] = '<li class="install-bad">' .
                    _T("Default fields configuration cannot be initialized.") .
                    '</li>';
            } else {
                $oks[] = '<li class="install-ok">' .
                    _T("Default fields configuration was successfully stored.") .
                    '</li>';
            }

            if ( (float)$_to_ver >= 0.70 ) {
                //once fields configuration defaults has been stored, we'll
                //report galette_required values, and we remove that table
                $res = $fc->migrateRequired($zdb);
                if ( $res !== true ) {
                    $errs[] = '<li class="install-bad">' .
                        _T("Required fields upgrade has failed :(") .
                        '<span>' . $res->getMessage() . '</span></li>';
                } else {
                    $oks[] = '<li class="install-ok">' .
                        _T("Required fields have been upgraded successfully.") .
                        '</li>';
                }
            }
        }

        $preferences = new Galette\Core\Preferences();
        $preferences->pref_admin_login = $_POST['install_adminlogin'];
        $preferences->pref_admin_pass = $_POST['install_adminpass'];
        $preferences->store();
    }

    include_once GALETTE_ROOT . 'includes/fields_defs/texts_fields.php';
    $texts = new Galette\Entity\Texts($texts_fields, $preferences);
    $res = $texts->installInit();
    if ( $res !== false ) {
        if ( $res !== true ) {
            $errs[] = '<li class="install-bad">' .
                _T("Default texts cannot be initialized.") .
                '<span>' . $res->getMessage() . '</span></li>';
        } else {
            $oks[] = '<li class="install-ok">' .
                _T("Default texts were successfully stored.") .
                '</li>';
        }
    }

    $models = new Galette\Repository\PdfModels($zdb, $preferences);
    include_once GALETTE_ROOT . 'includes/fields_defs/pdfmodels_fields.php';
    $res = $models->installInit($pdfmodels_fields);
    if ( $res === true ) {
        $oks[] = '<li class="install-ok">' .
            _T("PDF models were successfully stored.") .
            '</li>';
    } else {
        if ( $res !== false ) {
            //false is returned when table has already been filled
            $errs[] = '<li class="install-bad">' .
                _T("PDF models cannot be initialized.") .
                '<span>' . $res->getMessage() . '</span></li>';
        }
    }

    ?>
            <div<?php if( count($errs) == 0) echo ' id="infobox"'; ?>>
                <ul>
    <?php
    foreach ( $oks as $o ) {
        echo "\t\t\t\t\t" . $o . "\n";
    }
    ?>
                </ul>
            </div>
    <?php
    if ( count($errs) ==0 ) {
        ?>
            <form action="index.php" method="POST">
                <p id="btn_box">
                    <input id="next_btn" type="submit" value="<?php echo _T("Next step"); ?>"/>
                    <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    <input type="hidden" name="install_permsok" value="1"/>
                    <input type="hidden" name="install_dbtype" value="<?php echo $_POST['install_dbtype']; ?>"/>
                    <input type="hidden" name="install_dbhost" value="<?php echo $_POST['install_dbhost']; ?>"/>
                    <input type="hidden" name="install_dbport" value="<?php echo $_POST['install_dbport']; ?>"/>
                    <input type="hidden" name="install_dbuser" value="<?php echo $_POST['install_dbuser']; ?>"/>
                    <input type="hidden" name="install_dbpass" value="<?php echo $_POST['install_dbpass']; ?>"/>
                    <input type="hidden" name="install_dbname" value="<?php echo $_POST['install_dbname']; ?>"/>
                    <input type="hidden" name="install_dbprefix" value="<?php echo $_POST['install_dbprefix']; ?>"/>
                    <input type="hidden" name="install_dbconn_ok" value="1"/>
                    <input type="hidden" name="install_dbperms_ok" value="1"/>
                    <input type="hidden" name="install_dbwrite_ok" value="1"/>
                    <input type="hidden" name="install_adminlogin" value="<?php echo $_POST['install_adminlogin']; ?>"/>
                    <input type="hidden" name="install_adminpass" value="<?php echo $_POST['install_adminpass']; ?>"/>
                    <input type="hidden" name="install_passwdverified" value="1"/>
                    <input type="hidden" name="install_prefs_ok" value="1"/>
                </p>
            </form>
    <?php
    } else {
        ?>
            <div id="errorbox">
                <h1><?php echo _T("- ERROR -"); ?></h1>
                <p><?php echo _T("Parameters couldn't be saved."); ?><br/><?php echo _T("This can come from the permissions on the configuration file or the impossibility to make an INSERT into the database."); ?></p>
                <p><?php echo _T("Check above errors to know what went wrong."); ?></p>
                <ul><?php echo implode("\n", $errs); ?></ul>
            </div>
            <form action="index.php" method="POST">
                <p id="btn_box">
                    <input id="retry_btn" type="submit" value="<?php echo _T("Retry"); ?>"/>
                    <input type="hidden" name="install_type" value="<?php echo $_POST['install_type']; ?>"/>
                    <input type="hidden" name="install_permsok" value="1"/>
                    <input type="hidden" name="install_dbtype" value="<?php echo $_POST["install_dbtype"]; ?>"/>
                    <input type="hidden" name="install_dbhost" value="<?php echo $_POST["install_dbhost"]; ?>"/>
                    <input type="hidden" name="install_dbport" value="<?php echo $_POST["install_dbport"]; ?>"/>
                    <input type="hidden" name="install_dbuser" value="<?php echo $_POST["install_dbuser"]; ?>"/>
                    <input type="hidden" name="install_dbpass" value="<?php echo $_POST["install_dbpass"]; ?>"/>
                    <input type="hidden" name="install_dbname" value="<?php echo $_POST["install_dbname"]; ?>"/>
                    <input type="hidden" name="install_dbprefix" value="<?php echo $_POST["install_dbprefix"]; ?>"/>
                    <input type="hidden" name="install_dbconn_ok" value="1"/>
                    <input type="hidden" name="install_dbperms_ok" value="1"/>
                    <input type="hidden" name="install_dbwrite_ok" value="1"/>
                    <input type="hidden" name="install_adminlogin" value="<?php echo $_POST['install_adminlogin']; ?>"/>
                    <input type="hidden" name="install_adminpass" value="<?php echo $_POST['install_adminpass']; ?>"/>
                    <input type="hidden" name="install_passwdverified" value="1"/>
                </p>
            </form>
    <?php
    }
    break; //ends 9th step
case 'i10':
case 'u10':
    ?>
            <h2><?php
    if ( $step == 'i10' ) {
        echo _T("Installation complete !");
    }
    if ( $step == 'u10' ) {
        echo _T("Update complete !");
    }
    ?></h2>
            <p><?php
    if ( $step == 'i10' ) {
        echo _T("Galette has been successfully installed!");
    }
    if ( $step == 'u10' ) {
        echo _T("Galette has been successfully updated!");
    }
    ?></p>
            <div id="errorbox"><?php echo _T("To secure the system, please delete the install directory"); ?></div>
            <form action="<?php echo GALETTE_BASE_PATH; ?>index.php" method="get">
                <p id="btn_box">
                    <input type="submit" value="<?php echo _T("Homepage"); ?>"/>
                </p>
            </form>
    <?php
    break; //ends 10th step and finish install
} // switch
?>
            </div>
            <footer>
                <p><?php echo _T("Steps:"); ?></p>
                <ol>
                    <li<?php if( $step == '1') echo ' class="current"'; ?>><?php echo _T("Language"); ?> - </li>
                    <li<?php if( $step == '2') echo ' class="current"'; ?>><?php echo _T("Installation mode"); ?> - </li>
                    <li<?php if( $step == 'i3' || $step == 'u3' ) echo ' class="current"'; ?>><?php echo _T("Checks"); ?> - </li>
                    <li<?php if( $step == 'i4' || $step == 'u4') echo ' class="current"'; ?>><?php echo _T("Database"); ?> - </li>
                    <li<?php if( $step == 'i5' || $step == 'u5' ) echo ' class="current"'; ?>><?php echo _T("Access to the database"); ?> - </li>
                    <li<?php if( $step == 'i6' || $step == 'u6' ) echo ' class="current"'; ?>><?php echo _T("Access permissions to database"); ?> - </li>
                    <li<?php if( $step == 'i7' || $step == 'u7' ) echo ' class="current"'; ?>><?php echo _T("Tables Creation/Update"); ?> - </li>
                    <li<?php if( $step == 'i8' || $step == 'u8' ) echo ' class="current"'; ?>><?php echo _T("Admin parameters"); ?> - </li>
                    <li<?php if( $step == 'i9' || $step == 'u9' ) echo ' class="current"'; ?>><?php echo _T("Saving the parameters"); ?> - </li>
                    <li<?php if( $step == 'i10' || $step == 'u10' ) echo ' class="current"'; ?>><?php echo _T("End!"); ?></li>
                </ol>
            </footer>
        </section>
        <a id="copyright" href="http://galette.tuxfamily.org/">Galette <?php echo GALETTE_VERSION; ?></a>
    </body>
</html>
