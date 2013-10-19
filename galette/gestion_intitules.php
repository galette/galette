<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Lists configuration
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
 * @category  Main
 * @package   Galette
 *
 * @author    Manuel Menal <mmenal@hurdfr.org>
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-07-19
 */

require_once 'includes/galette.inc.php';

use Galette\Entity\Entitled;

if ( !$login->isLogged() ) {
    header("location: index.php");
    die();
}
if ( !$login->isAdmin() && !$login->isStaff() ) {
    header("location: voir_adherent.php");
    die();
} elseif ( !$login->isAdmin() ) {
    header("location: gestion_adherents.php");
    die();
}

$fields = array(
    'Status' => array(
        'id'    => 'id_statut',
        'name'  => 'libelle_statut',
        'field' => 'priorite_statut'
    ),
    'ContributionsTypes' => array(
        'id'    => 'id_type_cotis',
        'name'  => 'libelle_type_cotis',
        'field' => 'cotis_extension'
    )
);
$forms = array(
    'ContributionsTypes' => _T("Contribution types"),
    'Status'             => _T("User statuses")
);

/**
* Validate an input
*
* @param string $class current class name
* @param string $key   field key
* @param string $value field value
*
* @return boolean
*/
function checkFieldValue ($class, $key, $value)
{
    global $fields, $error_detected, $className;

    switch ($key) {
    case ($fields[$className]['name']):
        if ( trim($value) == '' || !is_string($value) ) {
            $error_detected[] =_T("- Mandatory field empty.")." ($key)";
            return false;
        }
        break;
    case ($fields[$className]['field']):
        if ( $className == 'Status' ) {
            if ( !is_numeric($value) ) {
                $error_detected[] = _T("- Priority must be an integer!");
                return false;
            }
        } elseif ( $className == 'ContributionsTypes' ) {
            // Value must be either 0 or 1.
            if ( !is_numeric($value) || (($value != 0) && ($value != 1)) ) {
                $error_detected[] = preg_replace(
                    '/%s/',
                    $value,
                    _T("- 'Extends membership?' field must be either 0 or 1! (current value: %s)")
                );
                return false;
            }
        }
        break;
    }
    return true;
}

/**
* Modify an entry
*
* @param integer $id    Entry's id
* @param string  $class current class name
*
* @return void
*/
function modifyEntry ($id, $class)
{
    global $zdb, $error_detected, $fields, $className;

    $label = '';
    $oldlabel = $class->getLabel($id, false);
    if ( !$oldlabel ) {
        $error_detected[] = _T("- Label does not exist");
        return;
    }

    $toup = array();
    /* Check field values. */
    foreach ( $fields[$className] as $field ) {
        $value = null;
        if ( !isset($_POST[$field]) ) {
            if ($field == $fields['ContributionsTypes']['field']) {
                $value = 0;
            } else {
                continue;
            }
        } else {
            $value = $_POST[$field];
        }

        if ( $field == $fields[$className]['name'] ) {
            $label = $value;
        }

        checkFieldValue($class, $field, $value);

        $toup[$field] = trim($value);
    }

    /* Update only if all fields are OK. */
    if ( count($error_detected) > 0 ) {
        return;
    }

    foreach ( $toup as $field => $value ) {
        $ret = $class->update($id, $field, $value);
        if ( $ret !== true ) {
            if ( $ret == $class::ID_NOT_EXITS ) {
                $error_detected[] = _T("- Label does not exist");
            } elseif ($ret == -1) {
                $error_detected[] = _T("- Database error: ") .
                    $class->getErrorMessage();
            }
        }
    }

    if ( isset($label) && ($oldlabel != $label) ) {
        deleteDynamicTranslation($oldlabel, $error_detected);
        addDynamicTranslation($label, $error_detected);
    }

    return;
}

/**
* Add a new entry
*
* @param string $class current class name
*
* @return void
*/
function addEntry ($class)
{
    global $error_detected, $fields, $className;

    $label = trim($_POST[$fields[$className]['name']]);
    $field = trim($_POST[$fields[$className]['field']]);

    checkFieldValue(
        $class,
        $fields[$className]['name'],
        $label
    );
    checkFieldValue(
        $class,
        $fields[$className]['field'],
        $field
    );

    if ( count($error_detected) ) {
        return;
    }

    $ret = $class->add($label, $field);
    if ( $ret < 0 ) {
        if ($ret == -1) {
            $error_detected[] = _T("- Database error: ").$class->getErrorMessage();
        }
        if ($ret == -2) {
            $error_detected[] = _T("- This label is already used!");
        }
        return;
    }

    // User should be able to translate the new labels dynamically.
    addDynamicTranslation($label, $error_detected);

    return;
}

$className = null;
$class = null;

//Show statuses list by default, instead of an empty table.
if ( !isset($_REQUEST['class']) ) {
    $className = 'Status';
} else {
    $className = $_REQUEST['class'];
}

$tpl->assign('class', $className);

if ( $className == 'Status' ) {
    $class = new Galette\Entity\Status;
} elseif ( $className == 'ContributionsTypes' ) {
    $class = new Galette\Entity\ContributionsTypes;
}

// Display a specific form to edit a label.
// Otherwise, display a list of entries.
if ( isset($_GET['id']) ) {
    //editEntry(trim($_GET['id']), $class);
    $entry = $class->get($_GET['id']);
    $tpl->assign('entry', $entry);
    if ($className == 'Status') {
        $tpl->assign('page_title', _T("Edit status"));
    } elseif ( $className == 'ContributionsTypes' ) {
        $tpl->assign('page_title', _T("Edit contribution type"));
    }
} else {
    if ( $className == 'Status' ) {
        $tpl->assign('page_title', _T("User statuses"));
    } elseif ( $className == 'ContributionsTypes' ) {
        $tpl->assign('page_title', _T("Contribution types"));
    }

    if ( isset($_GET['del']) ) {
        try {
            $label = $class->getLabel((int)$_GET['del']);
            if ( $label !== Entitled::ID_NOT_EXITS ) {
                $ret = $class->delete((int)$_GET['del']);

                if ( $ret === true ) {
                    deleteDynamicTranslation($label, $error_detected);
                    $success_detected[] = str_replace(
                        '%label',
                        $label,
                        _T("Entitled %label was successfully removed")
                    );
                }
            }
        } catch (RuntimeException $re) {
            $error_detected[] = $re->getMessage();
        }
    } elseif ( isset($_POST['new']) ) {
        addEntry($class);
    } elseif ( isset($_POST['mod']) ) {
        modifyEntry(trim($_POST['mod']), $class);
    }
    // Show the list.
    $list = $class->getCompleteList();
    $tpl->assign('entries', $list);

}

if ( count($class->errors) > 0 ) {
    $error_detected = array_merge($error_detected, $class->errors);
}

/* Set template parameters and print. */
$tpl->assign('require_tabs', true);
$tpl->assign('fields', $fields);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('success_detected', $success_detected);
if ( $className == 'Status' ) {
    $tpl->assign(
        'non_staff_priority',
        Galette\Repository\Members::NON_STAFF_MEMBERS
    );
}
if ( isset($_GET['ajax']) && $_GET['ajax'] == 'true' ) {
    $tpl->display('gestion_intitule_content.tpl');
} else {
    if ( isset($_GET['id']) ) {
        $content = $tpl->fetch('editer_intitule.tpl');
    } else {
        $tpl->assign('all_forms', $forms);
        $content = $tpl->fetch('gestion_intitules.tpl');
    }
    $tpl->assign('content', $content);
    $tpl->display('page.tpl');
}
