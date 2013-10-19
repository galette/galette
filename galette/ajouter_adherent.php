<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Add a new member or modify existing one
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

use Analog\Analog as Analog;
use Galette\Core\GaletteMail as GaletteMail;
use Galette\Entity\Adherent as Adherent;
use Galette\Entity\FieldsConfig as FieldsConfig;
use Galette\Entity\Texts as Texts;
use Galette\Entity\DynamicFields as DynamicFields;
use Galette\Repository\Members as Members;
use Galette\Repository\Titles as Titles;
use Galette\Repository\Groups as Groups;
use Galette\Filters\MembersList as MembersList;

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}

$member = new Adherent();
//TODO: dynamic fields should be handled by Adherent object
$dyn_fields = new DynamicFields();

// new or edit
$adherent['id_adh'] = get_numeric_form_value('id_adh', '');

if ( $login->isAdmin() || $login->isStaff() || $login->isGroupManager() ) {
    $adherent['id_adh'] = get_numeric_form_value('id_adh', '');
    if ( $adherent['id_adh'] ) {
        $member->load($adherent['id_adh']);
        if ( !$login->isAdmin() && !$login->isStaff() && $login->isGroupManager() ) {
            //check if current logged in user can manage loaded member
            $groups = $member->groups;
            $can_manage = false;
            foreach ( $groups as $group ) {
                if ( $login->isGroupManager($group->getId()) ) {
                    $can_manage = true;
                    break;
                }
            }
            if ( $can_manage !== true ) {
                Analog::log(
                    'Logged in member ' . $login->login .
                    ' has tried to load member #' . $member->id .
                    ' but do not manage any groups he belongs to.',
                    Analog::WARNING
                );
                $member->load($login->id);
            }
        }
    }

    // disable some fields
    if ( $login->isAdmin() ) {
        $disabled = $member->adm_edit_disabled_fields;
    } elseif ( $login->isStaff() ) {
        $disabled = $member->adm_edit_disabled_fields + $member->staff_edit_disabled_fields;
    } else {
        $disabled = $member->adm_edit_disabled_fields + $member->staff_edit_disabled_fields + $member->disabled_fields;
    }

    if ( $preferences->pref_mail_method == GaletteMail::METHOD_DISABLED ) {
        $disabled['send_mail'] = 'disabled="disabled"';
    }
} else {
    $member->load($login->id);
    $adherent['id_adh'] = $login->id;
    // disable some fields
    $disabled  = $member->disabled_fields + $member->edit_disabled_fields;
}

// flagging required fields
$fc = new FieldsConfig(Adherent::TABLE, $member->fields);
$required = $fc->getRequired();
// flagging fields visibility
$visibles = $fc->getVisibilities();

// password required if we create a new member
if ( $member->id != '' ) {
    unset($required['mdp_adh']);
}

// flagging required fields invisible to members
if ( $login->isAdmin() || $login->isStaff() ) {
    $required['activite_adh'] = 1;
    $required['id_statut'] = 1;
}

$real_requireds = array_diff(array_keys($required), array_keys($disabled));

// Validation
if ( isset($_POST[array_shift($real_requireds)]) ) {
    $adherent['dyn'] = $dyn_fields->extractPosted($_POST, $disabled);
    $valid = $member->check($_POST, $required, $disabled);
    if ( $valid === true ) {
        //all goes well, we can proceed

        $new = false;
        if ( $member->id == '' ) {
            $new = true;
        }
        $store = $member->store();
        if ( $store === true ) {
            //member has been stored :)
            if ( $new ) {
                $success_detected[] = _T("New member has been successfully added.");
                //Send email to admin if preference checked
                if ( $preferences->pref_mail_method > GaletteMail::METHOD_DISABLED
                    && $preferences->pref_bool_mailadh
                ) {
                    $texts = new Texts(
                        $texts_fields,
                        $preferences,
                        array(
                            'name_adh'      => custom_html_entity_decode($member->sname),
                            'firstname_adh' => custom_html_entity_decode($member->surname),
                            'lastname_adh'  => custom_html_entity_decode($member->name),
                            'mail_adh'      => custom_html_entity_decode($member->email),
                            'login_adh'     => custom_html_entity_decode($member->login)
                        )
                    );
                    $mtxt = $texts->getTexts('newadh', $preferences->pref_lang);

                    $mail = new GaletteMail();
                    $mail->setSubject($texts->getSubject());
                    $mail->setRecipients(
                        array(
                            $preferences->pref_email_newadh => 'Galette admin'
                        )
                    );
                    $mail->setMessage($texts->getBody());
                    $sent = $mail->send();

                    if ( $sent == GaletteMail::MAIL_SENT ) {
                        $hist->add(
                            str_replace(
                                '%s',
                                $member->sname . ' (' . $member->email . ')',
                                _T("New account mail sent to admin for '%s'.")
                            )
                        );
                    } else {
                        $str = str_replace(
                            '%s',
                            $member->sname . ' (' . $member->email . ')',
                            _T("A problem happened while sending email to admin for account '%s'.")
                        );
                        $hist->add($str);
                        $error_detected[] = $str;
                    }
                    unset ($texts);
                }
            } else {
                $success_detected[] = _T("Member account has been modified.");
            }

            // send mail to member
            if ( isset($_POST['mail_confirm']) && $_POST['mail_confirm'] == '1' ) {
                if ( $preferences->pref_mail_method > GaletteMail::METHOD_DISABLED ) {
                    if ( $member->email == '' ) {
                        $error_detected[] = _T("- You can't send a confirmation by email if the member hasn't got an address!");
                    } else {
                        //send mail to member
                        // Get email text in database
                        $texts = new Texts(
                            $texts_fields,
                            $preferences,
                            array(
                                'name_adh'      => custom_html_entity_decode($member->sname),
                                'firstname_adh' => custom_html_entity_decode($member->surname),
                                'lastname_adh'  => custom_html_entity_decode($member->name),
                                'mail_adh'      => custom_html_entity_decode($member->email),
                                'login_adh'     => custom_html_entity_decode($member->login),
                                'password_adh'  => custom_html_entity_decode($_POST['mdp_adh'])
                            )
                        );
                        $mlang = $preferences->pref_lang;
                        if ( isset($_POST['pref_lang']) ) {
                            $mlang = $_POST['pref_lang'];
                        }
                        $mtxt = $texts->getTexts(
                            (($new) ? 'sub' : 'accountedited'),
                            $mlang
                        );

                        $mail = new GaletteMail();
                        $mail->setSubject($texts->getSubject());
                        $mail->setRecipients(
                            array(
                                $member->email => $member->sname
                            )
                        );
                        $mail->setMessage($texts->getBody());
                        $sent = $mail->send();

                        if ( $sent == GaletteMail::MAIL_SENT ) {
                            $msg = str_replace(
                                '%s',
                                $member->sname . ' (' . $member->email . ')',
                                ($new) ?
                                _T("New account mail sent to '%s'.") :
                                _T("Account modification mail sent to '%s'.")
                            );
                            $hist->add($msg);
                            $success_detected[] = $msg;
                        } else {
                            $str = str_replace(
                                '%s',
                                $member->sname . ' (' . $member->email . ')',
                                _T("A problem happened while sending account mail to '%s'")
                            );
                            $hist->add($str);
                            $error_detected[] = $str;
                        }
                    }
                } else if ( $preferences->pref_mail_method == GaletteMail::METHOD_DISABLED) {
                    //if mail has been disabled in the preferences, we should not be here ; we do not throw an error, just a simple warning that will be show later
                    $msg = _T("You asked Galette to send a confirmation mail to the member, but mail has been disabled in the preferences.");
                    $warning_detected[] = $msg;
                    $session['mail_warning'] = $msg;
                }
            }

            //store requested groups
            $add_groups = null;
            $groups_adh = null;
            $managed_groups_adh = null;

            //add/remove user from groups
            if ( isset($_POST['groups_adh']) ) {
                $groups_adh = $_POST['groups_adh'];
            }
            $add_groups = Groups::addMemberToGroups(
                $member,
                $groups_adh
            );

            if ( $add_groups === false ) {
                $error_detected[] = _T("An error occured adding member to its groups.");
            }

            //add/remove manager from groups
            if ( isset($_POST['groups_managed_adh']) ) {
                $managed_groups_adh = $_POST['groups_managed_adh'];
            }
            $add_groups = Groups::addMemberToGroups(
                $member,
                $managed_groups_adh,
                true
            );
            $member->loadGroups();

            if ( $add_groups === false ) {
                $error_detected[] = _T("An error occured adding member to its groups as manager.");
            }
        } else {
            //something went wrong :'(
            $error_detected[] = _T("An error occured while storing the member.");
        }
    } else {
        //hum... there are errors :'(
        $error_detected = $valid;
    }

    if ( count($error_detected) == 0 ) {

        // picture upload
        if ( isset($_FILES['photo']) ) {
            if ( $_FILES['photo']['error'] === UPLOAD_ERR_OK ) {
                if ( $_FILES['photo']['tmp_name'] !='' ) {
                    if ( is_uploaded_file($_FILES['photo']['tmp_name']) ) {
                        $res = $member->picture->store($_FILES['photo']);
                        if ( $res < 0 ) {
                            $error_detected[] = $member->picture->getErrorMessage($res);
                        }
                    }
                }
            } else if ($_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                Analog::log(
                    $member->picture->getPhpErrorMessage($_FILES['photo']['error']),
                    Analog::WARNING
                );
                $error_detected[] = $member->picture->getPhpErrorMessage(
                    $_FILES['photo']['error']
                );
            }
        }

        if ( isset($_POST['del_photo']) ) {
            if ( !$member->picture->delete($member->id) ) {
                $error_detected[] = _T("Delete failed");
                $str_adh = $member->id . ' (' . $member->sname  . ' ' . ')';
                Analog::log(
                    'Unable to delete picture for member ' . $str_adh,
                    Analog::ERROR
                );
            }
        }

        // dynamic fields
        $dyn_fields->setAllFields('adh', $member->id, $adherent['dyn']);
    }

    if ( count($error_detected) == 0 ) {
        $session['account_success'] = serialize($success_detected);
        if ( !isset($_POST['id_adh']) ) {
            header(
                'location: ajouter_contribution.php?id_adh=' . $member->id
            );
            die();
        } elseif ( count($error_detected) == 0 ) {
            header('location: voir_adherent.php?id_adh=' . $member->id);
            die();
        }
    }
} else {
    if ( $member->id !== false &&  $member->id !== '' ) {
        $adherent['dyn'] = $dyn_fields->getFields('adh', $member->id, false);
    }
}

// - declare dynamic fields for display
$disabled['dyn'] = array();
if ( !isset($adherent['dyn']) ) {
    $adherent['dyn'] = array();
}

$dynamic_fields = $dyn_fields->prepareForDisplay(
    'adh',
    $adherent['dyn'],
    $disabled['dyn'],
    1
);
// template variable declaration
$title = _T("Member Profile");
if ( $member->id != '' ) {
    $title .= ' (' . _T("modification") . ')';
} else {
    $title .= ' (' . _T("creation") . ')';
}

$navigate = array();

if ( isset($session['filters']['members']) ) {
    $filters =  unserialize($session['filters']['members']);
} else {
    $filters = new MembersList();
}

if ( ($login->isAdmin() || $login->isStaff()) && count($filters) > 0 ) {
    $m = new Members();
    $ids = $m->getList(false, array(Adherent::PK, 'nom_adh', 'prenom_adh'));
    //print_r($ids);
    foreach ( $ids as $k=>$m ) {
        if ( $m->id_adh == $member->id ) {
            $navigate = array(
                'cur'  => $m->id_adh,
                'count' => count($ids),
                'pos' => $k+1
            );
            if ( $k > 0 ) {
                $navigate['prev'] = $ids[$k-1]->id_adh;
            }
            if ( $k < count($ids)-1 ) {
                $navigate['next'] = $ids[$k+1]->id_adh;
            }
            break;
        }
    }
}

$tpl->assign('navigate', $navigate);
$tpl->assign('require_dialog', true);
$tpl->assign('page_title', $title);
$tpl->assign('required', $required);
$tpl->assign('visibles', $visibles);
$tpl->assign('disabled', $disabled);
$tpl->assign('member', $member);
$tpl->assign('data', $adherent);
$tpl->assign('self_adh', false);
$tpl->assign('dynamic_fields', $dynamic_fields);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('success_detected', $success_detected);
if ( isset($session['mail_warning']) ) {
    //warning will be showed here, no need to keep it longer into session
    unset($session['mail_warning']);
}
$tpl->assign('warning_detected', $warning_detected);
$tpl->assign('languages', $i18n->getList());
$tpl->assign('require_calendar', true);
// pseudo random int
$tpl->assign('time', time());
// genre
$tpl->assign('titles_list', Titles::getList($zdb));

//Status
$statuts = new Galette\Entity\Status();
$tpl->assign('statuts', $statuts->getList());

//Groups
$groups = new Groups();
$groups_list = $groups->getSimpleList(true);
$tpl->assign('groups', $groups_list);

// page generation
$content = $tpl->fetch('member.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
