<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Add a new member or modify existing one
 *
 * PHP version 5
 *
 * Copyright © 2004-2011 The Galette Team
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
 * @copyright 2004-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.62
 */

/** @ignore */
require_once 'includes/galette.inc.php';

if ( !$login->isLogged() ) {
    header('location: index.php');
    die();
}

require_once 'classes/adherent.class.php';
require_once 'classes/status.class.php';
require_once 'classes/galette_mail.class.php';
require_once 'includes/dynamic_fields.inc.php';
require_once 'classes/texts.class.php';

$member = new Adherent();

// new or edit
$adherent['id_adh'] = '';
if ( $login->isAdmin() ) {
    $adherent['id_adh'] = get_numeric_form_value('id_adh', '');
    $id = get_numeric_form_value('id_adh', '');
    if ( $id ) {
        $member->load($adherent['id_adh']);
    }

    // disable some fields
    $disabled = $member->adm_edit_disabled_fields;
    if ( $preferences->pref_mail_method == GaletteMail::METHOD_DISABLED ) {
        $disabled['send_mail'] = 'disabled="disabled"';
    }
} else {
    $member->load($login->id);
    $adherent['id_adh'] = $login->id;
    // disable some fields
    $disabled  = $member->disabled_fields + $member->edit_disabled_fields;
}

// initialize warnings
$error_detected = array();
$warning_detected = array();
$confirm_detected = array();

// flagging required fields
require_once WEB_ROOT . 'classes/required.class.php';

$requires = new Required();
$required = $requires->getRequired();

// password required if we create a new member
if ( $member->id == '' ) {
    $required['mdp_adh'] = 1;
} else {
    unset($required['mdp_adh']);
}

// flagging required fields invisible to members
if ( $login->isAdmin() ) {
    $required['activite_adh'] = 1;
    $required['id_statut'] = 1;
}

// Validation
if ( isset($_POST['nom_adh']) ) {
    $adherent['dyn'] = extract_posted_dynamic_fields($_POST, $disabled);
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
                //Send email to admin if preference checked
                if ( $preferences->pref_bool_mailadh ) {
                    $texts = new texts();
                    $mtxt = $texts->getTexts('newadh', $preferences->pref_lang);

                    $patterns = array(
                        '/{NAME_ADH}/',
                        '/{SURNAME_ADH}/'
                    );

                    $replace = array(
                        custom_html_entity_decode($member->name),
                        custom_html_entity_decode($member->surname)
                    );

                    $mtxt->tsubject = preg_replace(
                        $patterns,
                        $replace,
                        $mtxt->tsubject
                    );

                    $patterns[] = '/{LOGIN}/';
                    $replace[] = custom_html_entity_decode($member->login);

                    $mtxt->tbody = preg_replace(
                        $patterns,
                        $replace,
                        $mtxt->tbody
                    );

                    $mail = new GaletteMail();
                    $mail->setSubject($mtxt->tsubject);
                    $mail->setRecipients(
                        array(
                            $preferences->pref_email_newadh => 'Galette admin'
                        )
                    );
                    $mail->setMessage($mtxt->tbody);
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
            }

            // send mail to member
            if ( isset($_POST['mail_confirm']) && $_POST['mail_confirm'] == '1' ) {
                if ( $preferences->pref_mail_method > GaletteMail::METHOD_DISABLED ) {
                    if ( $member->email == '' ) {
                        $error_detected[] = _T("- You can't send a confirmation by email if the member hasn't got an address!");
                    } else {
                        //send mail to member
                        // Get email text in database
                        $texts = new texts();
                        $mtxt = $texts->getTexts('sub', $preferences->pref_lang);

                        $patterns = array(
                            '/{NAME}/',
                            '/{LOGIN_URI}/',
                            '/{LOGIN}/',
                            '/{PASSWORD}/'
                        );

                        $replace = array(
                            $preferences->pref_nom,
                            'http://' . $_SERVER['SERVER_NAME'] .
                            dirname($_SERVER['REQUEST_URI']),
                            custom_html_entity_decode($member->login),
                            custom_html_entity_decode($_POST['mdp_adh'])
                        );

                        // Replace Tokens
                        $mtxt->tbody = preg_replace(
                            $patterns,
                            $replace,
                            $mtxt->tbody
                        );

                        $mail = new GaletteMail();
                        $mail->setSubject($mtxt->tsubject);
                        $mail->setRecipients(
                            array(
                                $member->email => $member->sname
                            )
                        );
                        $mail->setMessage($mtxt->tbody);
                        $sent = $mail->send();

                        if ( $sent == GaletteMail::MAIL_SENT ) {
                            $hist->add(
                                str_replace(
                                    '%s',
                                    $member->sname . ' (' . $member->email . ')',
                                    _T("New account mail sent to '%s'.")
                                )
                            );
                        } else {
                            $str = str_replace(
                                '%s',
                                $member->sname . ' (' . $member->email . ')',
                                _T("A problem happened while sending new account mail to '%s'")
                            );
                            $hist->add($str);
                            $error_detected[] = $str;
                        }
                    }
                } else if ( $preferences->pref_mail_method == GaletteMail::METHOD_DISABLED) {
                    //if mail has been disabled in the preferences, we should not be here ; we do not throw an error, just a simple warning that will be show later
                    $_SESSION['galette']['mail_warning'] = _T("You asked Galette to send a confirmation mail to the member, but mail has been disabled in the preferences.");
                }
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
            if ( $_FILES['photo']['tmp_name'] !='' ) {
                if ( is_uploaded_file($_FILES['photo']['tmp_name']) ) {
                    $res = $member->picture->store($_FILES['photo']);
                    if ( $res < 0 ) {
                        $error_detected[] = Picture::getErrorMessage($res);
                    }
                }
            }
        }

        if ( isset($_POST['del_photo']) ) {
            if ( !$member->picture->delete($member->id) ) {
                $error_detected[] = _T("Delete failed");
            }
        }

        // dynamic fields
        set_all_dynamic_fields('adh', $member->id, $adherent['dyn']);

        if ( !isset($_POST['id_adh']) ) {
            header(
                'location: ajouter_contribution.php?id_adh=' . $member->id
            );
        } elseif ( count($error_detected) == 0 ) {
            header('location: voir_adherent.php?id_adh=' . $member->id);
        }
    }
}

$adherent['dyn'] = get_dynamic_fields('adh', $member->id, false);

// - declare dynamic fields for display
$disabled['dyn'] = array();
if ( !isset($adherent['dyn']) ) {
    $adherent['dyn'] = array();
}

$dynamic_fields = prepare_dynamic_fields_for_display(
    'adh',
    $adherent['dyn'],
    $disabled['dyn'],
    1
);
// template variable declaration
$tpl->assign('required', $required);
$tpl->assign('disabled', $disabled);
$tpl->assign('member', $member);
$tpl->assign('data', $adherent);
$tpl->assign('self_adh', false);
$tpl->assign('dynamic_fields', $dynamic_fields);
$tpl->assign('error_detected', $error_detected);
$tpl->assign('warning_detected', $warning_detected);
$tpl->assign('languages', $i18n->getList());
$tpl->assign('require_calendar', true);
// pseudo random int
$tpl->assign('time', time());
// genre
$tpl->assign('radio_titres', Politeness::getList());

//Status
$statuts = Status::getList();
$tpl->assign('statuts', $statuts);

// page generation
$content = $tpl->fetch('member.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
