<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Preferences choice.
 *
 * Préferences contains the following groups:
 * - Association: global parameters (logo, adress, etc.)
 * - Galette: software usage preferences
 * - Email: sender and responseto email adresses
 * - Labels: labels format (margins, sizes, etc.)
 * - Members cards: format for member cards
 * - Admin account: super-admin parameters : only available for super-admin user
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
 * @author    Frédéric Jaqcuot <unknown@unknow.com>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2004-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

use Galette\Core;
use Analog\Analog as Analog;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() ) {
    header('location: voir_adherent.php');
    die();
} elseif ( !$login->isAdmin() ) {
    header('location: gestion_adherents.php');
    die();
}

$print_logo = new Core\PrintLogo();

// flagging required fields
$required = array(
    'pref_nom' => 1,
    'pref_lang' => 1,
    'pref_numrows' => 1,
    'pref_log' => 1,
    'pref_etiq_marges_v' => 1,
    'pref_etiq_marges_h' => 1,
    'pref_etiq_hspace' => 1,
    'pref_etiq_vspace' => 1,
    'pref_etiq_hsize' => 1,
    'pref_etiq_vsize' => 1,
    'pref_etiq_cols' => 1,
    'pref_etiq_rows' => 1,
    'pref_etiq_corps' => 1,
    'pref_card_abrev' => 1,
    'pref_card_strip' => 1,
    'pref_card_marges_v' => 1,
    'pref_card_marges_h' => 1,
    'pref_card_hspace' => 1,
    'pref_card_vspace' => 1
);

if ( $login->isSuperAdmin() ) {
    $required['pref_admin_login'] = 1;
}

if ( GALETTE_MODE === 'DEMO' ) {
    unset($required['pref_admin_login']);
}

$prefs_fields = $preferences->getFieldsNames();

// Validation
if ( isset($_POST['valid']) && $_POST['valid'] == '1' ) {
    // verification de champs
    $insert_values = array();

    // obtain fields
    foreach ($prefs_fields as $fieldname ) {
        if (isset($_POST[$fieldname])) {
            $value=trim($_POST[$fieldname]);
        } else {
            $value="";
        }

        // now, check validity
        if ( $value != '' ) {
            switch ( $fieldname ) {
            case 'pref_email':
                if ( GALETTE_MODE === 'DEMO' ) {
                    Analog::log(
                        'Trying to set pref_email while in DEMO.',
                        Analog::WARNING
                    );
                } else {
                    if ( !Core\GaletteMail::isValidEmail($value) ) {
                        $error_detected[] = _T("- Non-valid E-Mail address!");
                    }
                }
                break;
            case 'pref_admin_login':
                if ( GALETTE_MODE === 'DEMO' ) {
                    Analog::log(
                        'Trying to set superadmin login while in DEMO.',
                        Analog::WARNING
                    );
                } else {
                    if ( strlen($value) < 4 ) {
                        $error_detected[] = _T("- The username must be composed of at least 4 characters!");
                    } else {
                        //check if login is already taken
                        if ( $login->loginExists($value) ) {
                            $error_detected[] = _T("- This username is already in use, please choose another one!");
                        }
                    }
                }
                break;
            case 'pref_numrows':
                if ( !is_numeric($value) || $value < 0 ) {
                    $error_detected[] = "<li>"._T("- The numbers and measures have to be integers!")."</li>";
                }
                break;
            case 'pref_etiq_marges_h':
            case 'pref_etiq_marges_v':
            case 'pref_etiq_hspace':
            case 'pref_etiq_vspace':
            case 'pref_etiq_hsize':
            case 'pref_etiq_vsize':
            case 'pref_etiq_cols':
            case 'pref_etiq_rows':
            case 'pref_etiq_corps':
            case 'pref_card_marges_v':
            case 'pref_card_marges_h':
            case 'pref_card_hspace':
            case 'pref_card_vspace':
                // prevent division by zero
                if ( $fieldname=='pref_numrows' && $value=='0' ) {
                    $value = '10';
                }
                if ( !is_numeric($value) || $value < 0 ) {
                    $error_detected[] = _T("- The numbers and measures have to be integers!");
                }
                break;
            case 'pref_card_tcol':
                // Set strip text color to white
                if ( ! preg_match("/#([0-9A-F]{6})/i", $value) ) {
                    $value = '#FFFFFF';
                }
                break;
            case 'pref_card_scol':
            case 'pref_card_bcol':
            case 'pref_card_hcol':
                // Set strip background colors to black
                if ( !preg_match("/#([0-9A-F]{6})/i", $value) ) {
                    $value = '#000000';
                }
                break;
            case 'pref_admin_pass':
                if ( GALETTE_MODE == 'DEMO' ) {
                    Analog::log(
                        'Trying to set superadmin pass while in DEMO.',
                        Analog::WARNING
                    );
                } else {
                    if ( strlen($value) < 4 ) {
                        $error_detected[] = _T("- The password must be of at least 4 characters!");
                    }
                }
                break;
            case 'pref_membership_ext':
                if ( !is_numeric($value) || $value < 0 ) {
                    $error_detected[] = _T("- Invalid number of months of membership extension.");
                }
                break;
            case 'pref_beg_membership':
                $beg_membership = explode("/", $value);
                if ( count($beg_membership) != 2 ) {
                    $error_detected[] = _T("- Invalid format of beginning of membership.");
                } else {
                    $now = getdate();
                    if ( !checkdate($beg_membership[1], $beg_membership[0], $now['year']) ) {
                        $error_detected[] = _T("- Invalid date for beginning of membership.");
                    }
                }
                break;
            }
        }

        // fill up pref structure (after $value's modifications)
        $pref[$fieldname] = stripslashes($value);

        $insert_values[$fieldname] = $value;
    }

    // missing relations
    if ( GALETTE_MODE !== 'DEMO' && isset($insert_values['pref_mail_method']) ) {
        if ( $insert_values['pref_mail_method'] > Core\GaletteMail::METHOD_DISABLED ) {
            if ( !isset($insert_values['pref_email_nom'])
                || $insert_values['pref_email_nom'] == ''
            ) {
                $error_detected[] = _T("- You must indicate a sender name for emails!");
            }
            if ( !isset($insert_values['pref_email'])
                || $insert_values['pref_email'] == ''
            ) {
                $error_detected[] = _T("- You must indicate an email address Galette should use to send emails!");
            }
            if ( $insert_values['pref_mail_method'] == Core\GaletteMail::METHOD_SMTP ) {
                if ( !isset($insert_values['pref_mail_smtp_host'])
                    || $insert_values['pref_mail_smtp_host'] == ''
                ) {
                    $error_detected[] = _T("- You must indicate the SMTP server you want to use!");
                }
            }
            if ( $insert_values['pref_mail_method'] == Core\GaletteMail::METHOD_GMAIL
                || ( $insert_values['pref_mail_method'] == Core\GaletteMail::METHOD_SMTP
                && $insert_values['pref_mail_smtp_auth'] )
            ) {
                if ( !isset($insert_values['pref_mail_smtp_user'])
                    || trim($insert_values['pref_mail_smtp_user']) == ''
                ) {
                    $error_detected[] = _T("- You must provide a login for SMTP authentication.");
                }
                if ( !isset($insert_values['pref_mail_smtp_password'])
                    || ($insert_values['pref_mail_smtp_password']) == ''
                ) {
                    $error_detected[] = _T("- You must provide a password for SMTP authentication.");
                }
            }
        }
    }

    if ( isset($insert_values['pref_beg_membership'])
        && $insert_values['pref_beg_membership'] != ''
        && isset($insert_values['pref_membership_ext'])
        && $insert_values['pref_membership_ext'] != ''
    ) {
        $error_detected[] = _T("- Default membership extention and beginning of membership are mutually exclusive.");
    }

    // missing required fields?
    while ( list($key, $val) = each($required) ) {
        if ( !isset($pref[$key]) ) {
            $error_detected[] = _T("- Mandatory field empty.")." ".$key;
        } elseif ( isset($pref[$key]) ) {
            if (trim($pref[$key])=='') {
                $error_detected[] = _T("- Mandatory field empty.")." ".$key;
            }
        }
    }

    if (GALETTE_MODE !== 'DEMO' ) {
        // Check passwords. MD5 hash will be done into the Preferences class
        if (strcmp($insert_values['pref_admin_pass'], $_POST['pref_admin_pass_check']) != 0) {
            $error_detected[] = _T("Passwords mismatch");
        }
    }

    //postal adress
    if ( isset($insert_values['pref_postal_adress']) ) {
        $value = $insert_values['pref_postal_adress'];
        if ( $value == Core\Preferences::POSTAL_ADRESS_FROM_PREFS ) {
            if ( isset($insert_values['pref_postal_staff_member']) ) {
                unset($insert_values['pref_postal_staff_member']);
            }
        } else if ( $value == Core\Preferences::POSTAL_ADRESS_FROM_STAFF ) {
            if ( !isset($value) || $value < 1 ) {
                $error_detected[] = _T("You have to select a staff member");
            }
        }
    }

    if ( count($error_detected) == 0 ) {
        // update preferences
        while ( list($champ,$valeur) = each($insert_values) ) {
            if ( $login->isSuperAdmin()
                || (!$login->isSuperAdmin()
                && ($champ != 'pref_admin_pass' && $champ != 'pref_admin_login'))
            ) {
                if ( ($champ == "pref_admin_pass" && $_POST['pref_admin_pass'] !=  '')
                    || ($champ != "pref_admin_pass")
                ) {
                    $preferences->$champ = $valeur;
                }
            }
        }
        //once all values has been updated, we can store them
        if ( !$preferences->store() ) {
            $error_detected[] = _T("An SQL error has occured while storing preferences. Please try again, and contact the administrator if the problem persists.");
        } else {
            $success_detected[] = _T("Preferences has been saved.");
        }

        // picture upload
        if ( GALETTE_MODE !== 'DEMO' &&  isset($_FILES['logo']) ) {
            if ( $_FILES['logo']['error'] === UPLOAD_ERR_OK ) {
                if ( $_FILES['logo']['tmp_name'] !='' ) {
                    if ( is_uploaded_file($_FILES['logo']['tmp_name']) ) {
                        $res = $logo->store($_FILES['logo']);
                        if ( $res < 0 ) {
                            $error_detected[] = $logo->getErrorMessage($res);
                        } else {
                            $logo = new Core\Logo();
                        }
                        $session['logo'] = serialize($logo);
                        $tpl->assign('logo', $logo);
                    }
                }
            } else if ($_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                Analog::log(
                    $logo->getPhpErrorMessage($_FILES['logo']['error']),
                    Analog::WARNING
                );
                $error_detected[] = $logo->getPhpErrorMessage(
                    $_FILES['logo']['error']
                );
            }
        }

        if ( GALETTE_MODE !== 'DEMO' && isset($_POST['del_logo']) ) {
            if ( !$logo->delete() ) {
                $error_detected[] = _T("Delete failed");
            } else {
                $logo = new Core\Logo(); //get default Logo
                $session['logo'] = serialize($logo);
                $tpl->assign('logo', $logo);
            }
        }

        // Card logo upload
        if ( GALETTE_MODE !== 'DEMO' && isset($_FILES['card_logo']) ) {
            if ( $_FILES['card_logo']['error'] === UPLOAD_ERR_OK ) {
                if ( $_FILES['card_logo']['tmp_name'] !='' ) {
                    if ( is_uploaded_file($_FILES['card_logo']['tmp_name']) ) {
                        $res = $print_logo->store($_FILES['card_logo']);
                        if ( $res < 0 ) {
                            $error_detected[] = $print_logo->getErrorMessage($res);
                        } else {
                            $print_logo = new Core\PrintLogo();
                        }
                    }
                }
            } else if ($_FILES['card_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                Analog::log(
                    $print_logo->getPhpErrorMessage($_FILES['card_logo']['error']),
                    Analog::WARNING
                );
                $error_detected[] = $print_logo->getPhpErrorMessage(
                    $_FILES['card_logo']['error']
                );
            }
        }

        if ( GALETTE_MODE !== 'DEMO' && isset($_POST['del_card_logo']) ) {
            if ( !$print_logo->delete() ) {
                $error_detected[] = _T("Delete failed");
            } else {
                $print_logo = new Core\PrintLogo();
            }
        }
    }
} else {
    // collect data
    foreach ( $prefs_fields as $fieldname ) {
        $pref[$fieldname] = $preferences->$fieldname;
    }
}

//List available themes
$themes = array();
$d = dir(GALETTE_TEMPLATES_PATH);
while ( ($entry = $d->read()) !== false ) {
    $full_entry = GALETTE_TEMPLATES_PATH . $entry;
    if ($entry != '.'
        && $entry != '..'
        && is_dir($full_entry)
        && file_exists($full_entry.'/page.tpl')
    ) {
        $themes[] = $entry;
    }
}
$d->close();

$m = new Galette\Repository\Members();
$tpl->assign('staff_members', $m->getStaffMembersList(true));

$tpl->assign('time', time());
$tpl->assign('pref', $pref);
$tpl->assign(
    'pref_numrows_options',
    array(
        10 => '10',
        20 => '20',
        50 => '50',
        100 => '100',
        0 => _T("All")
    )
);
$tpl->assign('page_title', _T("Settings"));
$tpl->assign('print_logo', $print_logo);
$tpl->assign('required', $required);
$tpl->assign('languages', $i18n->getList());
$tpl->assign('themes', $themes);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('warning_detected', $warning_detected);
$tpl->assign('success_detected', $success_detected);
$tpl->assign('require_tabs', true);
$tpl->assign('color_picker', true);
// page generation
$content = $tpl->fetch('preferences.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');

if ( isset($profiler) ) {
    $profiler->stop();
}
