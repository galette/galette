<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Add a new member or modify existing one
 *
 * PHP version 5
 *
 * Copyright © 2004-2012 The Galette Team
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
 * @copyright 2004-2012 The Galette Team
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
require_once 'includes/powalib/formz-base.php';
require_once 'includes/powalib/formz-fields.php';
require_once 'includes/powalib/formz-validators.php';

$member = new Adherent();

// new or edit
$adherent['id_adh'] = '';
if ( $login->isAdmin() || $login->isStaff() ) {
    $adherent['id_adh'] = get_numeric_form_value('id_adh', '');
    $id = get_numeric_form_value('id_adh', '');
    if ( $id ) {
        $member->load($adherent['id_adh']);
    }

    // disable some fields
    if ( $login->isAdmin() ) {
        $disabled = $member->adm_edit_disabled_fields;
    } else {
        $disabled = $member->adm_edit_disabled_fields + $member->staff_edit_disabled_fields;
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
if ( $login->isAdmin() || $login->isStaff() ) {
    $required['activite_adh'] = 1;
    $required['id_statut'] = 1;
}

$real_requireds = array_diff(array_keys($required), array_keys($disabled));

function getLangList() {
    global $i18n;
    $lang = array();
    foreach($i18n->getList() as $key => $obj) {
        $lang[$key] = $obj->getName();
    }
    return $lang;
}

// FIXME you MUST prep the dynamic fields before the form

$form = new FormBase(
    'ajout_adherent',
    ($login->isLogged()) ? 'ajouter_adherent.php' : 'self_adherent.php', // I don't know what this means, I just got it from the tpl
    false,
    array(
        _T("Identity:"),
        new FormFieldUpload('photo', false,
            new FormValidatorUpload(
                array(
#                    'filename'     => FormValidatorPerlRegexp(), // todo
                    'content_type' => array('regex' => '#^image/.*$#'),
                    'max_size'     => 512*1024, // en octets
                    'image'        => array(
                        'content_type'  => array('regex' => '#^image/(png|jpeg|jpg|gif)$#'),
                        'width_max'     => 200,
                        'height_max'    => 200
                    ),
                )
            ), _T('Picture:'), null, "Image de max 200*200 et de 512Ko"
        ),
        new FormFieldDomain('title', isset($required['titre_adh']),
            false, _T('Title:'), $member->politness,
            'helptext', Politeness::getList()
        ),
        new FormFieldString('nom_adh', isset($required['nom_adh']),
            new FormValidatorString(1, 20),
            _T('Name:'),
            $member->name,
            "helptext"),
        new FormFieldString('prenom_adh', isset($required['prenom_adh']),
            new FormValidatorString(1, 20),
            _T('First name:'),
            $member->surname,
            'helptext'
        ),
        new FormFieldBoolean('is_company', false, false,
            _T('Is company?'), $member->isCompany(), ''
        ),
        new FormFieldString('societe_adh', false, new FormValidatorString(1, 20),
            _T('Company:'), $member->company, 'Leave it empty if not a company'
        ),
        new FormFieldString('pseudo_adh', isset($required['pseudo_adh']),
             new FormValidatorString(1, 20),
             _T('Nickname:'), $member->nickname, ''
        ),
        new FormFieldDate('ddn_adh', false,
            new FormValidatorDate('01/01/1890',strftime('%d/%m/%Y')),
            _T('Birth date:'),
            $member->birthdate,
            '', true
        ),
        new FormFieldString('lieu_naissance', isset($required['lieu_naissance']),
            new FormValidatorString(1, 500),
            _T('Birthplace:'),
            $member->birth_place,
            ''
        ),
        new FormFieldString('prof_adh', isset($required['prof_adh']),
            new FormValidatorString(1,150),
            _T('Profession:'),
            $member->job, ''
        ),
        new FormFieldDomain('pref_lang', isset($required['pref_lang']), false, _T('Language:'), $member->language, '',
            getLangList()
        ),

        _T("Contact information:"),
        new FormFieldString('adresse_adh', isset($required['adresse_adh']),
            new FormValidatorString(1,150),
            _T('Address:'),
            $member->adress, ''
        ),
        new FormFieldString('adress_adh2', false, new FormValidatorString(1,150), _T(' (continuation)'),
                             $member->adress_continuation, ''
        ),
        new FormFieldString('cp_adh', isset($required['cp_adh']),
            new FormValidatorInteger(4,10),
            _T('Zip Code:'),
            $member->zipcode, ''
        ),
        new FormFieldString('ville_adh', isset($required['ville_adh']),
            new FormValidatorString(1,50),
            _T('City:'),
            $member->town, ''
        ),
        new FormFieldString('pays_adh', isset($required['pays_adh']),
            new FormValidatorString(1, 50),
            _T('Country:'),
            $member->country, ''
        ),
        new FormFieldString('tel_adh', isset($required['tel_adh']),
            new FormValidatorString(1, 20),
            _T('Phone:'),
            $member->phone, ''
        ),
        new FormFieldString('gsm_adh', isset($required['gsm_adh']),
            new FormValidatorString(1, 20),
            _T('Mobile phone:'),
            $member->phone, ''
        ),
        new FormFieldString('email_adh', isset($required['email_adh']),
            new FormValidatorPerlRegexp(1, 150, '/^[a-z\-_]+(\.[a-z\-_]+)*@[a-z\-_]+(\.[a-z\-_]+)$/', 'email invalide'),
            _T('E-Mail:'),
            $member->email, ''
        ),
        new FormFieldString('url_adh', isset($required['url_adh']),
            new FormValidatorString(1, 200),
            _T('Website:'),
            $member->website,
            ''
        ),
        /* etc etc, */
    )
);

if ($form->Process()) {
    ?><h3>Informations extraites du formulaire :</h3><?php
    var_dump($form->ExtractValues());
    $form->DisplayHTML();
    $form->DisplayForm();
}

die();


// Validation
if ( isset($_POST[array_shift($real_requireds)]) ) {
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
                $success_detected[] = _T("New member has been successfully added.");
                //Send email to admin if preference checked
                if ( $preferences->pref_mail_method > GaletteMail::METHOD_DISABLED
                    && $preferences->pref_bool_mailadh
                ) {
                    $texts = new Texts(
                        array(
                            'name_adh'  => custom_html_entity_decode($member->sname),
                            'mail_adh'  => custom_html_entity_decode($member->email),
                            'login_adh' => custom_html_entity_decode($member->login)
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
                            array(
                                'name_adh'      => custom_html_entity_decode($member->sname),
                                'mail_adh'      => custom_html_entity_decode($member->email),
                                'login_adh'     => custom_html_entity_decode($member->login),
                                'password_adh'  => custom_html_entity_decode($_POST['mdp_adh'])
                            )
                        );
                        $mtxt = $texts->getTexts(
                            (($new) ? 'sub' : 'accountedited'),
                            $preferences->pref_lang
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
                    $_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['mail_warning'] = $msg;
                }
            }

            //store requested groups
            $add_groups = Groups::addMemberToGroups($member, $_POST['groups_adh']);
            if ( $add_groups === true ) {
                if ( isset ($_POST['groups_adh']) ) {
                    $log->log(
                        'Member .' . $member->sname . ' has been added to groups ' .
                        print_r($_POST['groups_adh'], true),
                        PEAR_LOG_INFO
                    );
                } else {
                    $log->log(
                        'Member .' . $member->sname . ' has been detached of ' .
                        'his groups.',
                        PEAR_LOG_INFO
                    );
                }
            } else {
                $log->log(
                    'Member .' . $member->sname . ' has not been added to groups ' .
                    print_r($_POST['groups_adh'], true),
                    PEAR_LOG_ERR
                );
                $error_detected[] = _T("An error occured adding member to its groups.");
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
                $log->log(
                    $member->picture->getPhpErrorMessage($_FILES['photo']['error']),
                    PEAR_LOG_WARNING
                );
                $error_detected[] = $member->picture->getPhpErrorMessage(
                    $_FILES['photo']['error']
                );
            }
        }

        if ( isset($_POST['del_photo']) ) {
            if ( !$member->picture->delete($member->id) ) {
                $error_detected[] = _T("Delete failed");
            }
        }

        // dynamic fields
        set_all_dynamic_fields('adh', $member->id, $adherent['dyn']);
    }

    if ( count($error_detected) == 0 ) {
        $_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['account_success'] = serialize($success_detected);
        if ( !isset($_POST['id_adh']) ) {
            header(
                'location: ajouter_contribution.php?id_adh=' . $member->id
            );
        } elseif ( count($error_detected) == 0 ) {
            header('location: voir_adherent.php?id_adh=' . $member->id);
        }
    }
} else {
    $adherent['dyn'] = get_dynamic_fields('adh', $member->id, false);
}

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
//"Member Profile"
    $title = _T("Member Profile");
if ( $member->id != '' ) {
    $title .= ' (' . _T("modification") . ')';
} else {
    $title .= ' (' . _T("creation") . ')';
}

$tpl->assign('require_dialog', true);
$tpl->assign('page_title', $title);
$tpl->assign('required', $required);
$tpl->assign('disabled', $disabled);
$tpl->assign('member', $member);
$tpl->assign('data', $adherent);
$tpl->assign('self_adh', false);
$tpl->assign('dynamic_fields', $dynamic_fields);
$tpl->assign('error_detected', $error_detected);
if ( isset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['mail_warning']) ) {
    //warning will be showed here, no need to keep it longer into session
    unset($_SESSION['galette'][PREFIX_DB . '_' . NAME_DB]['mail_warning']);
}
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

//Groups
$groups = new Groups();
$groups_list = $groups->getList();
$tpl->assign('groups', $groups_list);

// page generation
$content = $tpl->fetch('member.tpl');
$tpl->assign('content', $content);
$tpl->display('page.tpl');
?>
