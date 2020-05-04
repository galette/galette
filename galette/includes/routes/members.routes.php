<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members related routes
 *
 * PHP version 5
 *
 * Copyright © 2014-2018 The Galette Team
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
 * @category  Routes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2014-2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-27
 */

use Galette\Controllers\PdfController;
use Galette\Controllers\CsvController;
use Galette\Controllers\Crud;

use Analog\Analog;
use Galette\Core\Password;
use Galette\Core\PasswordImage;
use Galette\Core\Mailing;
use Galette\Core\GaletteMail;
use Galette\Repository\Members;
use Galette\Filters\MembersList;
use Galette\Filters\SavedSearchesList;
use Galette\Filters\AdvancedMembersList;
use Galette\Entity\FieldsConfig;
use Galette\Entity\Contribution;
use Galette\Repository\Groups;
use Galette\Repository\Reminders;
use Galette\Entity\Adherent;
use Galette\IO\Csv;
use Galette\IO\CsvOut;
use Galette\Entity\Status;
use Galette\Repository\Titles;
use Galette\Entity\Texts;
use Galette\Core\MailingHistory;
use Galette\Entity\Group;
use Galette\IO\File;
use Galette\Repository\SavedSearches;

//self subscription
$app->get(
    '/subscribe',
    function ($request, $response) {
        if (!$this->preferences->pref_bool_selfsubscribe || $this->login->isLogged()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }

        if ($this->session->member !== null) {
            $member = $this->session->member;
            $this->session->member = null;
        } else {
            $deps = [
                'dynamics'  => true
            ];
            $member = new Adherent($this->zdb, null, $deps);
        }

        //mark as self membership
        $member->setSelfMembership();

        // flagging required fields
        $fc = $this->fields_config;
        $form_elements = $fc->getFormElements($this->login, true, true);

        //image to defeat mass filling forms
        $spam = new PasswordImage();
        $spam_pass = $spam->newImage();
        $spam_img = $spam->getImage();

        // members
        $m = new Members();
        $members = $m->getSelectizedMembers(
            $this->zdb,
            $member->hasParent() ? $member->parent->id : null
        );

        $params['members'] = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];

        if (count($members)) {
            $params['members']['list'] = $members;
        }

        // display page
        $this->view->render(
            $response,
            'member.tpl',
            array(
                'page_title'        => _T("Subscription"),
                'parent_tpl'        => 'public_page.tpl',
                'member'            => $member,
                'self_adh'          => true,
                'autocomplete'      => true,
                // pseudo random int
                'time'              => time(),
                'titles_list'       => Titles::getList($this->zdb),
                //self_adh specific
                'spam_pass'         => $spam_pass,
                'spam_img'          => $spam_img,
                'fieldsets'         => $form_elements['fieldsets'],
                'hidden_elements'   => $form_elements['hiddens']
            ) + $params
        );
        return $response;
    }
)->setName('subscribe');

//members list CSV export
$app->get(
    '/members/export/csv',
    CsvController::class . ':membersExport'
)->setName('csv-memberslist')->add($authenticate);

//members list
$app->get(
    '/members[/{option:page|order}/{value:\d+}]',
    Crud\MembersController::class . ':list'
)->setName('members')->add($authenticate);

//members list filtering
$app->post(
    '/members/filter',
    Crud\MembersController::class . ':filter'
)->setName('filter-memberslist')->add($authenticate);

//members self card
$app->get(
    '/member/me',
    function ($request, $response) {
        if ($this->login->isSuperAdmin()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }
        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true,
            'dynamics'  => true
        );

        $member = new Adherent($this->zdb, $this->login->login, $deps);

        $fc = $this->fields_config;
        $display_elements = $fc->getDisplayElements($this->login);

        // display page
        $this->view->render(
            $response,
            'voir_adherent.tpl',
            array(
                'page_title'        => _T("Member Profile"),
                'member'            => $member,
                'pref_lang'         => $this->i18n->getNameFromId($member->language),
                'pref_card_self'    => $this->preferences->pref_card_self,
                'groups'            => Groups::getSimpleList(),
                'time'              => time(),
                'display_elements'  => $display_elements
            )
        );
    }
)->setName('me')->add($authenticate);

//members card
$app->get(
    '/member/{id:\d+}',
    Crud\MembersController::class . ':show'
)->setName('member')->add($authenticate)->add($navMiddleware);

$app->get(
    '/member/{action:edit|add}[/{id:\d+}]',
    function ($request, $response, $args) {
        $action = $args['action'];
        $id = null;
        if (isset($args['id'])) {
            $id = (int)$args['id'];
        }

        if ($action === 'edit' && $id === null) {
            throw new \RuntimeException(
                _T("Member ID cannot ben null calling edit route!")
            );
        } elseif ($action === 'add' && $id !== null) {
             return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('editmember', ['action' => 'add']));
        }
        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true,
            'dynamics'  => true
        );

        if ($this->session->member !== null) {
            $member = $this->session->member;
            $this->session->member = null;
        } else {
            $member = new Adherent($this->zdb, $id, $deps);
        }

        if ($id !== null) {
            if (!$member->canEdit($this->login)) {
                $this->flash->addMessage(
                    'error_detected',
                    _T("You do not have permission for requested URL.")
                );

                return $response
                    ->withStatus(403)
                    ->withHeader(
                        'Location',
                        $this->router->pathFor('me')
                    );
            }
        } else {
            if ($member->id != $id) {
                $member->load($this->login->id);
            }
        }

        // flagging required fields
        $fc = $this->fields_config;

        // password required if we create a new member
        if ($member->id != '') {
            $fc->setNotRequired('mdp_adh');
        }

        //handle requirements for parent fields
        $parent_fields = $member->getParentFields();
        $tpl_parent_fields = []; //for JS when detaching
        foreach ($parent_fields as $field) {
            if ($fc->isRequired($field)) {
                $tpl_parent_fields[] = $field;
                if ($member->hasParent()) {
                    $fc->setNotRequired($field);
                }
            }
        }

        // flagging required fields invisible to members
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $fc->setNotRequired('activite_adh');
            $fc->setNotRequired('id_statut');
        }

        // template variable declaration
        $title = _T("Member Profile");
        if ($member->id != '') {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        //Status
        $statuts = new Status($this->zdb);

        //Groups
        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getSimpleList(true);

        $form_elements = $fc->getFormElements(
            $this->login,
            $member->id == ''
        );

        // members
        $m = new Members();
        $members = $m->getSelectizedMembers(
            $this->zdb,
            $member->hasParent() ? $member->parent->id : null
        );

        $route_params['members'] = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];

        if (count($members)) {
            $route_params['members']['list'] = $members;
        }

        // display page
        $this->view->render(
            $response,
            'member.tpl',
            array(
                'parent_tpl'        => 'page.tpl',
                'autocomplete'      => true,
                'page_title'        => $title,
                'member'            => $member,
                'self_adh'          => false,
                // pseudo random int
                'time'              => time(),
                'titles_list'       => Titles::getList($this->zdb),
                'statuts'           => $statuts->getList(),
                'groups'            => $groups_list,
                'fieldsets'         => $form_elements['fieldsets'],
                'hidden_elements'   => $form_elements['hiddens'],
                'parent_fields'     => $tpl_parent_fields
            )
        );
        return $response;
    }
)->setName(
    'editmember'
)->add($authenticate)->add($navMiddleware);

$app->post(
    '/member/store[/{self:subscribe}]',
    function ($request, $response, $args) {
        if (!$this->preferences->pref_bool_selfsubscribe && !$this->login->isLogged()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }

        $post = $request->getParsedBody();
        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true,
            'dynamics'  => true
        );
        $member = new Adherent($this->zdb, null, $deps);
        $member->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
        if (isset($args['self'])) {
            //mark as self membership
            $member->setSelfMembership();
        }

        $success_detected = [];
        $warning_detected = [];
        $error_detected = [];

        // new or edit
        $adherent['id_adh'] = get_numeric_form_value('id_adh', '');
        if ($adherent['id_adh']) {
            $member->load((int)$adherent['id_adh']);
            if (!$member->canEdit($this->login)) {
                //redirection should have been done before. Just throw an Exception.
                throw new \RuntimeException(
                    str_replace(
                        '%id',
                        $member->id,
                        'No right to store member #%id'
                    )
                );
            }
        } else {
            $member->load($this->login->id);
            $adherent['id_adh'] = $this->login->id;
        }

        // flagging required fields
        $fc = $this->fields_config;

        // password required if we create a new member
        if ($member->id != '') {
            $fc->setNotRequired('mdp_adh');
        }

        if ($member->hasParent() && !isset($post['detach_parent'])
            || isset($post['parent_id']) && !empty($post['parent_id'])
        ) {
            $parent_fields = $member->getParentFields();
            foreach ($parent_fields as $field) {
                if ($fc->isRequired($field)) {
                    $fc->setNotRequired($field);
                }
            }
        }

        // flagging required fields invisible to members
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $fc->setNotRequired('activite_adh');
            $fc->setNotRequired('id_statut');
        }

        $form_elements = $fc->getFormElements(
            $this->login,
            $member->id == '',
            isset($args['self'])
        );
        $fieldsets     = $form_elements['fieldsets'];
        $required      = array();
        $disabled      = array();

        foreach ($fieldsets as $category) {
            foreach ($category->elements as $field) {
                if ($field->required == true) {
                    $required[$field->field_id] = true;
                }
                if ($field->disabled == true) {
                    $disabled[$field->field_id] = true;
                } elseif (!isset($post[$field->field_id])) {
                    switch ($field->field_id) {
                        //unchecked booleans are not sent from form
                        case 'bool_admin_adh':
                        case 'bool_exempt_adh':
                        case 'bool_display_info':
                            $post[$field->field_id] = 0;
                            break;
                    }
                }
            }
        }

        $real_requireds = array_diff(array_keys($required), array_keys($disabled));

        // Validation
        if (isset($post[array_shift($real_requireds)])) {
            // regular fields
            $valid = $member->check($post, $required, $disabled);
            if ($valid !== true) {
                $error_detected = array_merge($error_detected, $valid);
            }

            if (count($error_detected) == 0) {
                //all goes well, we can proceed

                $new = false;
                if ($member->id == '') {
                    $new = true;
                }
                $store = $member->store();
                if ($store === true) {
                    //member has been stored :)
                    if ($new) {
                        if (isset($args['self'])) {
                            $success_detected[] = _T("Your account has been created!");
                            if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED
                                && $member->getEmail() != ''
                            ) {
                                $success_detected[] = _T("An email has been sent to you, check your inbox.");
                            }
                        } else {
                            $success_detected[] = _T("New member has been successfully added.");
                        }
                        //Send email to admin if preference checked
                        if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED
                            && $this->preferences->pref_bool_mailadh
                        ) {
                            $texts = new Texts(
                                $this->preferences,
                                $this->router,
                                array(
                                    'name_adh'      => custom_html_entity_decode(
                                        $member->sname
                                    ),
                                    'firstname_adh' => custom_html_entity_decode(
                                        $member->surname
                                    ),
                                    'lastname_adh'  => custom_html_entity_decode(
                                        $member->name
                                    ),
                                    'mail_adh'      => custom_html_entity_decode(
                                        $member->email
                                    ),
                                    'login_adh'     => custom_html_entity_decode(
                                        $member->login
                                    )
                                )
                            );
                            $mtxt = $texts->getTexts(
                                (isset($args['self']) ? 'newselfadh' : 'newadh'),
                                $this->preferences->pref_lang
                            );

                            $mail = new GaletteMail($this->preferences);
                            $mail->setSubject($texts->getSubject());
                            $recipients = [];
                            foreach ($this->preferences->vpref_email_newadh as $pref_email) {
                                $recipients[$pref_email] = $pref_email;
                            }
                            $mail->setRecipients($recipients);
                            $mail->setMessage($texts->getBody());
                            $sent = $mail->send();

                            if ($sent == GaletteMail::MAIL_SENT) {
                                $this->history->add(
                                    str_replace(
                                        '%s',
                                        $member->sname . ' (' . $member->email . ')',
                                        _T("New account email sent to admin for '%s'.")
                                    )
                                );
                            } else {
                                $str = str_replace(
                                    '%s',
                                    $member->sname . ' (' . $member->email . ')',
                                    _T("A problem happened while sending email to admin for account '%s'.")
                                );
                                $this->history->add($str);
                                $warning_detected[] = $str;
                            }
                            unset($texts);
                        }
                    } else {
                        $success_detected[] = _T("Member account has been modified.");
                    }

                    // send email to member
                    if (isset($args['self']) || isset($post['mail_confirm']) && $post['mail_confirm'] == '1') {
                        if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED) {
                            if ($member->getEmail() == '' && !isset($args['self'])) {
                                $error_detected[] = _T("- You can't send a confirmation by email if the member hasn't got an address!");
                            } else {
                                $mreplaces = [
                                    'name_adh'      => custom_html_entity_decode(
                                        $member->sname
                                    ),
                                    'firstname_adh' => custom_html_entity_decode(
                                        $member->surname
                                    ),
                                    'lastname_adh'  => custom_html_entity_decode(
                                        $member->name
                                    ),
                                    'mail_adh'      => custom_html_entity_decode(
                                        $member->getEmail()
                                    ),
                                    'login_adh'     => custom_html_entity_decode(
                                        $member->login
                                    )
                                ];
                                if ($new) {
                                    $password = new Password($this->zdb);
                                    $res = $password->generateNewPassword($member->id);
                                    if ($res == true) {
                                        $link_validity = new DateTime();
                                        $link_validity->add(new DateInterval('PT24H'));
                                        $mreplaces['change_pass_uri'] = $this->preferences->getURL() .
                                            $this->router->pathFor(
                                                'password-recovery',
                                                ['hash' => base64_encode($password->getHash())]
                                            );
                                        $mreplaces['link_validity'] = $link_validity->format(_T("Y-m-d H:i:s"));
                                    } else {
                                        $str = str_replace(
                                            '%s',
                                            $login_adh,
                                            _T("An error occurred storing temporary password for %s. Please inform an admin.")
                                        );
                                        $this->history->add($str);
                                        $this->flash->addMessage(
                                            'error_detected',
                                            $str
                                        );
                                    }
                                }

                                //send email to member
                                // Get email text in database
                                $texts = new Texts(
                                    $this->preferences,
                                    $this->router,
                                    $mreplaces
                                );
                                $mlang = $this->preferences->pref_lang;
                                if (isset($post['pref_lang'])) {
                                    $mlang = $post['pref_lang'];
                                }
                                $mtxt = $texts->getTexts(
                                    (($new) ? 'sub' : 'accountedited'),
                                    $mlang
                                );

                                $mail = new GaletteMail($this->preferences);
                                $mail->setSubject($texts->getSubject());
                                $mail->setRecipients(
                                    array(
                                        $member->getEmail() => $member->sname
                                    )
                                );
                                $mail->setMessage($texts->getBody());
                                $sent = $mail->send();

                                if ($sent == GaletteMail::MAIL_SENT) {
                                    $msg = str_replace(
                                        '%s',
                                        $member->sname . ' (' . $member->getEmail() . ')',
                                        ($new) ?
                                        _T("New account email sent to '%s'.") :
                                        _T("Account modification email sent to '%s'.")
                                    );
                                    $this->history->add($msg);
                                    $success_detected[] = $msg;
                                } else {
                                    $str = str_replace(
                                        '%s',
                                        $member->sname . ' (' . $member->getEmail() . ')',
                                        _T("A problem happened while sending account email to '%s'")
                                    );
                                    $this->history->add($str);
                                    $error_detected[] = $str;
                                }
                            }
                        } elseif ($this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED) {
                            //if email has been disabled in the preferences, we should not be here ;
                            //we do not throw an error, just a simple warning that will be show later
                            $msg = _T("You asked Galette to send a confirmation email to the member, but email has been disabled in the preferences.");
                            $warning_detected[] = $msg;
                        }
                    }

                    // send email to admin
                    if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED
                        && $this->preferences->pref_bool_mailadh
                        && !$new
                        && $member->id == $this->login->id
                    ) {
                        $mreplaces = [
                            'name_adh'      => custom_html_entity_decode(
                                $member->sname
                            ),
                            'firstname_adh' => custom_html_entity_decode(
                                $member->surname
                            ),
                            'lastname_adh'  => custom_html_entity_decode(
                                $member->name
                            ),
                            'mail_adh'      => custom_html_entity_decode(
                                $member->getEmail()
                            ),
                            'login_adh'     => custom_html_entity_decode(
                                $member->login
                            )
                        ];

                        //send email to member
                        // Get email text in database
                        $texts = new Texts(
                            $this->preferences,
                            $this->router,
                            $mreplaces
                        );
                        $mlang = $this->preferences->pref_lang;

                        $mtxt = $texts->getTexts(
                            'admaccountedited',
                            $mlang
                        );

                        $mail = new GaletteMail($this->preferences);
                        $mail->setSubject($texts->getSubject());
                        $recipients = [];
                        foreach ($this->preferences->vpref_email_newadh as $pref_email) {
                            $recipients[$pref_email] = $pref_email;
                        }
                        $mail->setRecipients($recipients);

                        $mail->setMessage($texts->getBody());
                        $sent = $mail->send();

                        if ($sent == GaletteMail::MAIL_SENT) {
                            $msg = _T("Account modification email sent to admin.");
                            $this->history->add($msg);
                            $success_detected[] = $msg;
                        } else {
                            $str = _T("A problem happened while sending account email to admin");
                            $this->history->add($str);
                            $warning_detected[] = $str;
                        }
                    }

                    //store requested groups
                    $add_groups = null;
                    $groups_adh = null;
                    $managed_groups_adh = null;

                    //add/remove user from groups
                    if (isset($post['groups_adh'])) {
                        $groups_adh = $post['groups_adh'];
                    }
                    $add_groups = Groups::addMemberToGroups(
                        $member,
                        $groups_adh
                    );

                    if ($add_groups === false) {
                        $error_detected[] = _T("An error occurred adding member to its groups.");
                    }

                    //add/remove manager from groups
                    if (isset($post['groups_managed_adh'])) {
                        $managed_groups_adh = $post['groups_managed_adh'];
                    }
                    $add_groups = Groups::addMemberToGroups(
                        $member,
                        $managed_groups_adh,
                        true
                    );
                    $member->loadGroups();

                    if ($add_groups === false) {
                        $error_detected[] = _T("An error occurred adding member to its groups as manager.");
                    }
                } else {
                    //something went wrong :'(
                    $error_detected[] = _T("An error occurred while storing the member.");
                }
            }

            if (count($error_detected) == 0) {
                $files_res = $member->handleFiles($_FILES);
                if (is_array($files_res)) {
                    $error_detected = array_merge($error_detected, $files_res);
                }

                if (isset($post['del_photo'])) {
                    if (!$member->picture->delete($member->id)) {
                        $error_detected[] = _T("Delete failed");
                        $str_adh = $member->id . ' (' . $member->sname  . ' ' . ')';
                        Analog::log(
                            'Unable to delete picture for member ' . $str_adh,
                            Analog::ERROR
                        );
                    }
                }
            }

            if (count($error_detected) > 0) {
                foreach ($error_detected as $error) {
                    if (strpos($error, '%member_url_') !== false) {
                        preg_match('/%member_url_(\d+)/', $error, $matches);
                        $url = $this->router->pathFor('member', ['id' => $matches[1]]);
                        $error = str_replace(
                            '%member_url_' . $matches[1],
                            $url,
                            $error
                        );
                    }
                    $this->flash->addMessage(
                        'error_detected',
                        $error
                    );
                }
            }

            if (count($warning_detected) > 0) {
                foreach ($warning_detected as $warning) {
                    $this->flash->addMessage(
                        'warning_detected',
                        $warning
                    );
                }
            }
            if (count($success_detected) > 0) {
                foreach ($success_detected as $success) {
                    $this->flash->addMessage(
                        'success_detected',
                        $success
                    );
                }
            }

            if (count($error_detected) == 0) {
                $redirect_url = null;
                if (isset($args['self'])) {
                    $redirect_url = $this->router->pathFor('login');
                } elseif (isset($post['redirect_on_create'])
                    && $post['redirect_on_create'] > Adherent::AFTER_ADD_DEFAULT
                ) {
                    switch ($post['redirect_on_create']) {
                        case Adherent::AFTER_ADD_TRANS:
                            $redirect_url = $this->router->pathFor('transaction', ['action' => 'add']);
                            break;
                        case Adherent::AFTER_ADD_NEW:
                            $redirect_url = $this->router->pathFor('editmember', ['action' => 'add']);
                            break;
                        case Adherent::AFTER_ADD_SHOW:
                            $redirect_url = $this->router->pathFor('member', ['id' => $member->id]);
                            break;
                        case Adherent::AFTER_ADD_LIST:
                            $redirect_url = $this->router->pathFor('members');
                            break;
                        case Adherent::AFTER_ADD_HOME:
                            $redirect_url = $this->router->pathFor('slash');
                            break;
                    }
                } elseif (!isset($post['id_adh']) && !$member->isDueFree()) {
                    $redirect_url = $this->router->pathFor(
                        'contribution',
                        [
                            'type'      => 'fee',
                            'action'    => 'add',
                        ]
                    ) . '?id_adh=' . $member->id;
                } else {
                    $redirect_url = $this->router->pathFor('member', ['id' => $member->id]);
                }

                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $redirect_url);
            } else {
                //store entity in session
                $this->session->member = $member;

                if (isset($args['self'])) {
                    $redirect_url = $this->router->pathFor('subscribe');
                } else {
                    if ($member->id) {
                        $rparams = [
                            'id'    => $member->id,
                            'action'    => 'edit'
                        ];
                    } else {
                        $rparams = ['action' => 'add'];
                    }
                    $redirect_url = $this->router->pathFor(
                        'editmember',
                        $rparams
                    );
                }

                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $redirect_url);
            }
        }
    }
)->setName('storemembers');

$app->get(
    '/member/remove/{id:\d+}',
    Crud\MembersController::class . ':confirmDelete'
)->setName('removeMember')->add($authenticate);

$app->get(
    '/members/remove',
    Crud\MembersController::class . ':confirmDelete'
)->setName('removeMembers')->add($authenticate);

$app->post(
    '/member/remove' . '[/{id:\d+}]',
    Crud\MembersController::class . ':delete'
)->setName('doRemoveMember')->add($authenticate);

//advanced search page
$app->get(
    '/advanced-search',
    Crud\MembersController::class . ':advancedSearch'
)->setName('advanced-search')->add($authenticate);

//Batch actions on members list
$app->post(
    '/members/batch',
    Crud\MembersController::class . ':handleBatch'
)->setName('batch-memberslist')->add($authenticate);

//PDF members cards
$app->get(
    '/members/cards[/{' . Adherent::PK . ':\d+}]',
    PdfController::class . ':membersCards'
)->setName('pdf-members-cards')->add($authenticate);

//PDF members labels
$app->get(
    '/members/labels',
    PdfController::class . ':membersLabels'
)->setName('pdf-members-labels')->add($authenticate);

//PDF adhesion form
$app->get(
    '/members/adhesion-form/{' . Adherent::PK . ':\d+}',
    PdfController::class . ':adhesionForm'
)->setName('adhesionForm')->add($authenticate);

//Empty PDF adhesion form
$app->get(
    '/members/empty-adhesion-form',
    PdfController::class . ':adhesionForm'
)->setName('emptyAdhesionForm');

//mailing
$app->get(
    '/mailing',
    Crud\MailingsController::class . ':add'
)->setName('mailing')->add($authenticate);

$app->post(
    '/mailing',
    Crud\MailingsController::class . ':doAdd'
)->setName('doMailing')->add($authenticate);

$app->map(
    ['GET', 'POST'],
    '/mailing/preview[/{id:\d+}]',
    Crud\MailingsController::class . ':preview'
)->setName('mailingPreview')->add($authenticate);

$app->get(
    '/mailing/preview/{id:\d+}/attachment/{pos:\d+}',
    Crud\MailingsController::class . ':previewAttachment'
)->setName('previewAttachment')->add($authenticate);

$app->post(
    '/ajax/mailing/set-recipients',
    Crud\MailingsController::class . ':setRecipients'
)->setName('mailingRecipients')->add($authenticate);

//reminders
$app->get(
    '/reminders',
    function ($request, $response) {
        $texts = new Texts($this->preferences, $this->router);

        $previews = array(
            'impending' => $texts->getTexts('impendingduedate', $this->preferences->pref_lang),
            'late'      => $texts->getTexts('lateduedate', $this->preferences->pref_lang)
        );

        $members = new Members();
        $reminders = $members->getRemindersCount();

        // display page
        $this->view->render(
            $response,
            'reminder.tpl',
            [
                'page_title'                => _T("Reminders"),
                'previews'                  => $previews,
                'count_impending'           => $reminders['impending'],
                'count_impending_nomail'    => $reminders['nomail']['impending'],
                'count_late'                => $reminders['late'],
                'count_late_nomail'         => $reminders['nomail']['late']
            ]
        );
        return $response;
    }
)->setName('reminders')->add($authenticate);

$app->post(
    '/reminders',
    function ($request, $response) {
        $error_detected = [];
        $warning_detected = [];
        $success_detected = [];

        $post = $request->getParsedBody();
        $texts = new Texts($this->preferences, $this->router);
        $selected = null;
        if (isset($post['reminders'])) {
            $selected = $post['reminders'];
        }
        $reminders = new Reminders($selected);

        $labels = false;
        $labels_members = array();
        if (isset($post['reminder_wo_mail'])) {
            $labels = true;
        }

        $list_reminders = $reminders->getList($this->zdb, $labels);
        if (count($list_reminders) == 0) {
            $warning_detected[] = _T("No reminder to send for now.");
        } else {
            foreach ($list_reminders as $reminder) {
                if ($labels === false) {
                    //send reminders by email
                    $sent = $reminder->send($texts, $this->history, $this->zdb);

                    if ($sent === true) {
                        $success_detected[] = $reminder->getMessage();
                    } else {
                        $error_detected[] = $reminder->getMessage();
                    }
                } else {
                    //generate labels for members without email address
                    $labels_members[] = $reminder->member_id;
                }
            }

            if ($labels === true) {
                if (count($labels_members) > 0) {
                    $labels_filters = new MembersList();
                    $labels_filters->selected = $labels_members;
                    $this->session->filters_reminders_labels = $labels_filters;
                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $this->router->pathFor('pdf-member-labels'));
                } else {
                    $error_detected[] = _T("There are no member to proceed.");
                }
            }

            if (count($error_detected) > 0) {
                array_unshift(
                    $error_detected,
                    _T("Reminder has not been sent:")
                );
            }

            if (count($success_detected) > 0) {
                array_unshift(
                    $success_detected,
                    _T("Sent reminders:")
                );
            }
        }

        //flash messages if any
        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage('error_detected', $error);
            }
        }
        if (count($warning_detected) > 0) {
            foreach ($warning_detected as $warning) {
                $this->flash->addMessage('warning_detected', $warning);
            }
        }
        if (count($success_detected) > 0) {
            foreach ($success_detected as $success) {
                $this->flash->addMessage('success_detected', $success);
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('reminders'));
    }
)->setName('doReminders')->add($authenticate);

$app->get(
    '/members/reminder-filter/{membership:nearly|late}/{mail:withmail|withoutmail}',
    function ($request, $response, $args) {
        //always reset filters
        $filters = new MembersList();
        $filters->filter_account = Members::ACTIVE_ACCOUNT;

        $membership = ($args['membership'] === 'nearly' ?
            Members::MEMBERSHIP_NEARLY :
            Members::MEMBERSHIP_LATE);
        $filters->membership_filter = $membership;

        //TODO: filter on reminder may take care of parent email as well
        $mail = ($args['mail'] === 'withmail' ?
            Members::FILTER_W_EMAIL :
            Members::FILTER_WO_EMAIL);
        $filters->email_filter = $mail;

        $this->session->filter_members = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('members'));
    }
)->setName('reminders-filter')->add($authenticate);

$app->map(
    ['GET', 'POST'],
    '/attendance-sheet/details',
    function ($request, $response) {
        $post = $request->getParsedBody();

        if ($this->session->filter_members !== null) {
            $filters = $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        // check for ajax mode
        $ajax = false;
        if ($request->isXhr()
            || isset($post['ajax'])
            && $post['ajax'] == 'true'
        ) {
            $ajax = true;

            //retrieve selected members
            $selection = (isset($post['selection']) ) ? $post['selection'] : array();

            $filters->selected = $selection;
            $this->session->filter_members = $filters;
        } else {
            $selection = $filters->selected;
        }


        // display page
        $this->view->render(
            $response,
            'attendance_sheet_details.tpl',
            [
                'page_title'    => _T("Attendance sheet configuration"),
                'ajax'          => $ajax,
                'selection'     => $selection
            ]
        );
        return $response;
    }
)->setName('attendance_sheet_details')->add($authenticate);

$app->post(
    '/attendance-sheet',
    PdfController::class . ':attendanceSheet'
)->setName('attendance_sheet')->add($authenticate);

$app->post(
    '/ajax/members[/{option:page|order}/{value:\d+}]',
    function ($request, $response, $args) {
        $post = $request->getParsedBody();

        if (isset($this->session->ajax_members_filters)) {
            $filters = $this->session->ajax_members_filters;
        } else {
            $filters = new MembersList();
        }

        if (isset($args['option']) && $args['option'] == 'page') {
            $filters->current_page = (int)$args['value'];
        }

        //numbers of rows to display
        if (isset($post['nbshow']) && is_numeric($post['nbshow'])) {
            $filters->show = $post['nbshow'];
        }

        $members = new Members($filters);
        if (!$this->login->isAdmin() && !$this->login->isStaff()) {
            if ($this->login->isGroupManager()) {
                $members_list = $members->getManagedMembersList(true);
            } else {
                Analog::log(
                    str_replace(
                        ['%id', '%login'],
                        [$this->login->id, $this->login->login],
                        'Trying to list group members without access from #%id (%login)'
                    ),
                    Analog::ERROR
                );
                throw new Exception('Access denied.');
            }
        } else {
            $members_list = $members->getMembersList(true);
        }

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($this->router, $this->view->getSmarty(), false);

        $this->session->ajax_members_filters = $filters;

        $selected_members = null;
        $unreachables_members = null;
        if (!isset($post['from'])) {
            $mailing = $this->session->mailing;
            if (!isset($post['members'])) {
                $selected_members = $mailing->recipients;
                $unreachables_members = $mailing->unreachables;
            } else {
                $m = new Members();
                $selected_members = $m->getArrayList($post['members']);
                if (isset($post['unreachables']) && is_array($post['unreachables'])) {
                    $unreachables_members = $m->getArrayList($post['unreachables']);
                }
            }
        } else {
            switch ($post['from']) {
                case 'groups':
                    if (!isset($post['gid'])) {
                        Analog::log(
                            'Trying to list group members with no group id provided',
                            Analog::ERROR
                        );
                        throw new Exception('A group id is required.');
                        exit(0);
                    }
                    if (!isset($post['members'])) {
                        $group = new Group((int)$post['gid']);
                        $selected_members = array();
                        if (!isset($post['mode']) || $post['mode'] == 'members') {
                            $selected_members = $group->getMembers();
                        } elseif ($post['mode'] == 'managers') {
                            $selected_members = $group->getManagers();
                        } else {
                            Analog::log(
                                'Trying to list group members with unknown mode',
                                Analog::ERROR
                            );
                            throw new Exception('Unknown mode.');
                            exit(0);
                        }
                    } else {
                        $m = new Members();
                        $selected_members = $m->getArrayList($post['members']);
                        if (isset($post['unreachables']) && is_array($post['unreachables'])) {
                            $unreachables_members = $m->getArrayList($post['unreachables']);
                        }
                    }
                    break;
                case 'attach':
                    if (!isset($post['id_adh'])) {
                        throw new \RuntimeException(
                            'Current selected member must be excluded while attaching!'
                        );
                        exit(0);
                    }
                    break;
            }
        }

        $params = [
            'filters'               => $filters,
            'members_list'          => $members_list,
            'selected_members'      => $selected_members,
            'unreachables_members'  => $unreachables_members
        ];

        if (isset($post['multiple'])) {
            $params['multiple'] = true;
        }

        if (isset($post['gid'])) {
            $params['the_id'] = $post['gid'];
        }

        if (isset($post['id_adh'])) {
            $params['excluded'] = $post['id_adh'];
        }

        // display page
        $this->view->render(
            $response,
            'ajax_members.tpl',
            $params
        );
        return $response;
    }
)->setName('ajaxMembers')->add($authenticate);

$app->post(
    '/ajax/group/members',
    function ($request, $response) {
        $post = $request->getParsedBody();

        $ids = $post['persons'];
        $mode = $post['person_mode'];

        if (!$ids || !$mode) {
            Analog::log(
                'Missing persons and mode for ajaxGroupMembers',
                Analog::INFO
            );
            die();
        }

        $m = new Members;
        $persons = $m->getArrayList($ids);

        // display page
        $this->view->render(
            $response,
            'group_persons.tpl',
            [
                'persons'       => $persons,
                'person_mode'   => $mode
            ]
        );
        return $response;
    }
)->setName('ajaxGroupMembers')->add($authenticate);

$app->get(
    '/member/{id:\d+}/file/{fid:\d+}/{pos:\d+}/{name}',
    function ($request, $response, $args) {
        $id = (int)$args['id'];
        $deps = array(
            'picture'   => false,
            'groups'    => false,
            'dues'      => false,
            'parent'    => false,
            'children'  => false,
            'dynamics'  => true
        );
        $member = new Adherent($this->zdb, $id, $deps);

        $denied = null;
        if (!$member->canEdit($this->login)) {
            $fields = $member->getDynamicFields()->getFields();
            if (!isset($fields[$args['fid']])) {
                //field does not exists or access is forbidden
                $denied = true;
            } else {
                $denied = false;
            }
        }

        if ($denied === true) {
            $this->flash->addMessage(
                'error_detected',
                _T("You do not have permission for requested URL.")
            );

            return $response
                ->withStatus(403)
                ->withHeader(
                    'Location',
                    $this->router->pathFor(
                        'member',
                        ['id' => $id]
                    )
                );
        }

        $filename = str_replace(
            [
                '%mid',
                '%fid',
                '%pos'
            ],
            [
                $args['id'],
                $args['fid'],
                $args['pos']
            ],
            'member_%mid_field_%fid_value_%pos'
        );

        if (file_exists(GALETTE_FILES_PATH . $filename)) {
            $type = File::getMimeType(GALETTE_FILES_PATH . $filename);
            $response = $response
                ->withHeader('Content-Type', $type)
                ->withHeader('Content-Disposition', 'attachment;filename="' . $args['name'] . '"')
                ->withHeader('Pragma', 'no-cache');
            $response->write(readfile(GALETTE_FILES_PATH . $filename));
            return $response;
        } else {
            Analog::log(
                'A request has been made to get an exported file named `' .
                $filename .'` that does not exists.',
                Analog::WARNING
            );

            $this->flash->addMessage(
                'error_detected',
                _T("The file does not exists or cannot be read :(")
            );

            return $response
                ->withStatus(404)
                ->withHeader(
                    'Location',
                    $this->router->pathFor('member', ['id' => $args['id']])
                );
        }
    }
)->setName('getDynamicFile')->add($authenticate);

$app->get(
    '/members/mass-change',
    function ($request, $response) {
        $filters =  $this->session->filter_members;

        $data = [
            'id'            => $filters->selected,
            'redirect_uri'  => $this->router->pathFor('members')
        ];

        $fc = $this->fields_config;
        $form_elements = $fc->getMassiveFormElements($this->members_fields, $this->login);

        //dynamic fields
        $deps = array(
            'picture'   => false,
            'groups'    => false,
            'dues'      => false,
            'parent'    => false,
            'children'  => false,
            'dynamics'  => false
        );
        $member = new Adherent($this->zdb, null, $deps);

        //Status
        $statuts = new Status($this->zdb);

        // display page
        $this->view->render(
            $response,
            'mass_change_members.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => str_replace(
                    '%count',
                    count($data['id']),
                    _T('Mass change %count members')
                ),
                'form_url'      => $this->router->pathFor('masschangeMembersReview'),
                'cancel_uri'    => $this->router->pathFor('members'),
                'data'          => $data,
                'member'        => $member,
                'fieldsets'     => $form_elements['fieldsets'],
                'titles_list'   => Titles::getList($this->zdb),
                'statuts'       => $statuts->getList(),
                'require_mass'  => true
            )
        );
        return $response;
    }
)->setName('masschangeMembers')->add($authenticate);

$app->post(
    '/members/mass-change/validate',
    function ($request, $response) {
        $post = $request->getParsedBody();

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Mass changes has not been confirmed!")
            );
        } else {
            //we want only visibles fields
            $fc = $this->fields_config;
            $form_elements = $fc->getMassiveFormElements($this->members_fields, $this->login);

            $changes = [];
            foreach ($form_elements['fieldsets'] as $form_element) {
                foreach ($form_element->elements as $field) {
                    if (isset($post[$field->field_id]) && isset($post['mass_' . $field->field_id])) {
                        $changes[$field->field_id] = [
                            'label' => $field->label,
                            'value' => $post[$field->field_id]
                        ];
                    }
                }
            }
        }

        $filters =  $this->session->filter_members;
        $data = [
            'id'            => $filters->selected,
            'redirect_uri'  => $this->router->pathFor('members')
        ];

        //Status
        $statuts = new Status($this->zdb);

        // display page
        $this->view->render(
            $response,
            'mass_change_members.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => str_replace(
                    '%count',
                    count($data['id']),
                    _T('Review mass change %count members')
                ),
                'form_url'      => $this->router->pathFor('massstoremembers'),
                'cancel_uri'    => $this->router->pathFor('members'),
                'data'          => $data,
                'titles_list'   => Titles::getList($this->zdb),
                'statuts'       => $statuts->getList(),
                'changes'       => $changes
            )
        );
        return $response;
    }
)->setName('masschangeMembersReview')->add($authenticate);

$app->post(
    '/members/mass-change',
    function ($request, $response, $args) {
        $post = $request->getParsedBody();
        $redirect_url = $post['redirect_uri'];
        $error_detected = [];
        $mass = 0;

        unset($post['redirect_uri']);
        if (!isset($post['confirm'])) {
            $error_detected[] = _T("Mass changes has not been confirmed!");
        } else {
            unset($post['confirm']);
            $ids = $post['id'];
            unset($post['id']);

            $fc = $this->fields_config;
            $form_elements = $fc->getMassiveFormElements($this->members_fields, $this->login);
            $disabled = $this->members_fields;
            foreach (array_keys($post) as $key) {
                $found = false;
                foreach ($form_elements['fieldsets'] as $fieldset) {
                    if (isset($fieldset->elements[$key])) {
                        $found = true;
                        continue;
                    }
                }
                if (!$found) {
                    Analog::log(
                        'Permission issue mass editing field ' . $key,
                        Analog::WARNING
                    );
                    unset($post[$key]);
                } else {
                    unset($disabled[$key]);
                }
            }

            if (!count($post)) {
                $error_detected[] = _T("Nothing to do!");
            } else {
                foreach ($ids as $id) {
                    $deps = array(
                        'picture'   => false,
                        'groups'    => $is_manager,
                        'dues'      => false,
                        'parent'    => false,
                        'children'  => false,
                        'dynamics'  => false
                    );
                    $member = new Adherent($this->zdb, (int)$id, $deps);
                    $member->setDependencies(
                        $this->preferences,
                        $this->members_fields,
                        $this->history
                    );
                    if (!$member->canEdit($this->login)) {
                        continue;
                    }

                    $valid = $member->check($post, [], $disabled);
                    if ($valid === true) {
                        $done = $member->store();
                        if (!$done) {
                            $error_detected[] = _T("An error occurred while storing the member.");
                        } else {
                            ++$mass;
                        }
                    } else {
                        $error_detected = array_merge($error_detected, $valid);
                    }
                }
            }
        }

        if ($mass == 0 && !count($error_detected)) {
            $error_detected[] = _T('Something went wront during mass edition!');
        } else {
            $this->flash->addMessage(
                'success_detected',
                str_replace(
                    '%count',
                    $mass,
                    _T('%count members has been changed successfully!')
                )
            );
        }

        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        if (!$request->isXhr()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $redirect_url);
        } else {
            return $response->withJson(
                [
                    'success'   => count($error_detected) === 0
                ]
            );
        }
    }
)->setName('massstoremembers')->add($authenticate);

//Duplicate member
$app->get(
    '/members/duplicate/{' . Adherent::PK . ':\d+}',
    Crud\MembersController::class . ':duplicate'
)->setName('duplicateMember')->add($authenticate);

//saved searches
$app->map(
    ['GET', 'POST'],
    '/save-search',
    function ($request, $response) {
        if ($request->isPost()) {
            $post = $request->getParsedBody();
        } else {
            $post = $request->getQueryParams();
        }

        $name = null;
        if (isset($post['search_title'])) {
            $name = $post['search_title'];
            unset($post['search_title']);
        }

        //when using advanced search, no parameters are sent
        if (isset($post['advanced_search'])) {
            $post = [];
            $filters = $this->session->filter_members;
            foreach ($filters->search_fields as $field) {
                $post[$field] = $filters->$field;
            }
        }

        //reformat, add required infos
        $post = [
            'parameters'    => $post,
            'form'          => 'Adherent',
            'name'          => $name
        ];

        $sco = new Galette\Entity\SavedSearch($this->zdb, $this->login);
        if ($check = $sco->check($post)) {
            if (!$res = $sco->store()) {
                if ($res === false) {
                    $this->flash->addMessage(
                        'error_detected',
                        _T("An SQL error has occurred while storing search.")
                    );
                } else {
                    $this->flash->addMessage(
                        'warning_detected',
                        _T("This search is already saved.")
                    );
                }
            } else {
                $this->flash->addMessage(
                    'success_detected',
                    _T("Search has been saved.")
                );
            }
        } else {
            //report errors
            foreach ($sco->getErrors() as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        if ($request->isGet()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }
    }
)->setName('saveSearch');

$app->get(
    '/saved-searches[/{option:page|order}/{value:\d+}]',
    function ($request, $response, $args = []) {
        $option = null;
        if (isset($args['option'])) {
            $option = $args['option'];
        }
        $value = null;
        if (isset($args['value'])) {
            $value = $args['value'];
        }

        if (isset($this->session->filter_savedsearch)) {
            $filters = $this->session->filter_savedsearch;
        } else {
            $filters = new SavedSearchesList();
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
            }
        }

        $searches = new SavedSearches($this->zdb, $this->login, $filters);
        $list = $searches->getList(true);

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($this->router, $this->view->getSmarty(), false);

        $this->session->filter_savedsearch = $filters;

        // display page
        $this->view->render(
            $response,
            'saved_searches.tpl',
            array(
                'page_title'        => _T("Saved searches"),
                'searches'          => $list,
                'nb'                => $searches->getCount(),
                'filters'           => $filters
            )
        );
        return $response;
    }
)->setName('searches')->add($authenticate);

$app->get(
    '/search/remove/{id:\d+}',
    function ($request, $response, $args) {
        $data = [
            'id'            => $args['id'],
            'redirect_uri'  => $this->router->pathFor('searches')
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'type'          => _T("Saved search"),
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => _T('Remove saved search'),
                'form_url'      => $this->router->pathFor('doRemoveSearch', ['id' => $args['id']]),
                'cancel_uri'    => $this->router->pathFor('searches'),
                'data'          => $data
            )
        );
        return $response;
    }
)->setName('removeSearch')->add($authenticate);

$app->get(
    '/searches/remove',
    function ($request, $response) {
        $filters =  $this->session->filter_savedsearch;

        $data = [
            'id'            => $filters->selected,
            'redirect_uri'  => $this->router->pathFor('searches')
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'type'          => _T("Saved search"),
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => _T('Remove saved searches'),
                'message'       => str_replace(
                    '%count',
                    count($data['id']),
                    _T('You are about to remove %count searches.')
                ),
                'form_url'      => $this->router->pathFor('doRemoveSearch'),
                'cancel_uri'    => $this->router->pathFor('searches'),
                'data'          => $data
            )
        );
        return $response;
    }
)->setName('removeSearches')->add($authenticate);

$app->post(
    '/search/remove' . '[/{id:\d+}]',
    function ($request, $response) {
        $post = $request->getParsedBody();
        $ajax = isset($post['ajax']) && $post['ajax'] === 'true';
        $success = false;

        $uri = isset($post['redirect_uri']) ?
            $post['redirect_uri'] :
            $this->router->pathFor('slash');

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Removal has not been confirmed!")
            );
        } else {
            if (isset($this->session->filter_savedsearch)) {
                $filters =  $this->session->filter_savedsearch;
            } else {
                $filters = new SavedSearchesList();
            }
            $searches = new SavedSearches($this->zdb, $this->login, $filters);

            if (!is_array($post['id'])) {
                $ids = (array)$post['id'];
            } else {
                $ids = $post['id'];
            }

            $del = $searches->remove($ids, $this->history);

            if ($del !== true) {
                $error_detected = _T("An error occurred trying to remove searches :/");

                $this->flash->addMessage(
                    'error_detected',
                    $error_detected
                );
            } else {
                $success_detected = str_replace(
                    '%count',
                    count($ids),
                    _T("%count searches have been successfully deleted.")
                );

                $this->flash->addMessage(
                    'success_detected',
                    $success_detected
                );

                $success = true;
            }
        }

        if (!$ajax) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $uri);
        } else {
            return $response->withJson(
                [
                    'success'   => $success
                ]
            );
        }
    }
)->setName('doRemoveSearch')->add($authenticate);

$app->get(
    '/save-search/{id}',
    function ($request, $response, $args) {
        try {
            $sco = new Galette\Entity\SavedSearch($this->zdb, $this->login, (int)$args['id']);
            $this->flash->addMessage(
                'success_detected',
                _T("Saved search loaded")
            );
        } catch (\Exception $e) {
            $this->flash->addMessage(
                'error_detected',
                _T("An SQL error has occurred while storing search.")
            );
        }
        $parameters = (array)$sco->parameters;

        $filters = null;
        if (isset($parameters['free_search'])) {
            $filters = new AdvancedMembersList();
        } else {
            $filters = new MembersList();
        }

        foreach ($parameters as $key => $value) {
            $filters->$key = $value;
        }
        $this->session->filter_members = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('members'));
    }
)->setName('loadSearch');
