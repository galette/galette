<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members related routes
 *
 * PHP version 5
 *
 * Copyright © 2014 The Galette Team
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
 * @copyright 2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-27
 */

use Galette\Entity\DynamicFields;
use Galette\Core\PasswordImage;
use Galette\Repository\Members;
use Galette\Filters\MembersList;
use Galette\Entity\FieldsConfig;
use Galette\Entity\Politeness;
use Galette\Repository\Groups;
use Galette\Entity\Adherent;

//self subscription
$app->get(
    '/subscribe',
    function () use ($app, $preferences, $login, $i18n) {
        if ( !$preferences->pref_bool_selfsubscribe || $login->isLogged() ) {
            $app->redirect($app->urlFor('slash'));
        }

        $dyn_fields = new DynamicFields();

        $member = new Adherent();
        //mark as self membership
        $member->setSelfMembership();

        // flagging required fields
        $fc = new FieldsConfig(Adherent::TABLE, $member->fields);
        $required = $fc->getRequired();
        // flagging fields visibility
        $visibles = $fc->getVisibilities();


        // disable some fields
        $disabled  = $member->disabled_fields;

        // DEBUT parametrage des champs
        // On recupere de la base la longueur et les flags des champs
        // et on initialise des valeurs par defaut

        $fields = Adherent::getDbFields();

        // - declare dynamic fields for display
        $disabled['dyn'] = array();
        if ( !isset($adherent['dyn']) ) {
            $adherent['dyn'] = array();
        }

        //image to defeat mass filling forms
        $spam = new PasswordImage();
        $spam_pass = $spam->newImage();
        $spam_img = $spam->getImage();

        $dynamic_fields = $dyn_fields->prepareForDisplay(
            'adh', $adherent['dyn'], $disabled['dyn'], 1
        );

        /*if ( $has_register ) {
            $tpl->assign('has_register', $has_register);
        }
        if ( isset($head_redirect) ) {
            $tpl->assign('head_redirect', $head_redirect);
        }*/
        // /self_adh specific

        $app->render(
            'member.tpl',
            array(
                'page_title'        => _T("Subscription"),
                'parent_tpl'        => 'public_page.tpl',
                'required'          => $required,
                'visibles'          => $visibles,
                'disabled'          => $disabled,
                'member'            => $member,
                'self_adh'          => true,
                'dynamic_fields'    => $dynamic_fields,
                'languages'         => $i18n->getList(),
                'require_calendar'  => true,
                // pseudo random int
                'time'              => time(),
                // genre
                'radio_titres'      => Politeness::getList(),
                //self_adh specific
                'spam_pass'         => $spam_pass,
                'spam_img'          => $spam_img
            )
        );

    }
)->name('subscribe');

//members list
$app->get(
    '/members',
    $authenticate($app),
    function () use ($app, $login, &$session) {
        /*if ( isset($session['filters']['members'])
            && !isset($_POST['mailing'])
            && !isset($_POST['mailing_new'])
        ) {*/
        if ( isset($session['filters']['members']) ) {
            $filters = unserialize($session['filters']['members']);
        } else {
            $filters = new MembersList();
        }

        $members = new Members($filters);

        $members_list = array();
        if ( $login->isAdmin() || $login->isStaff() ) {
            $members_list = $members->getMembersList(true);
        } else {
            $members_list = $members->getManagedMembersList(true);
        }

        $groups = new Groups();
        $groups_list = $groups->getList();

        $session['filters']['members'] = serialize($filters);

        $smarty = $app->view()->getInstance();

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($smarty);

        $app->render(
            'gestion_adherents.tpl',
            array(
                'page_title'            => _T("Members management"),
                'require_dialog'        => true,
                'require_calendar'      => true,
                'members'               => $members_list,
                'filter_groups_options' => $groups_list,
                'nb_members'            => $members->getCount(),
                'filters'               => $filters,
                'filter_field_options'  => array(
                    Members::FILTER_NAME            => _T("Name"),
                    Members::FILTER_COMPANY_NAME    => _T("Company name"),
                    Members::FILTER_ADDRESS         => _T("Address"),
                    Members::FILTER_MAIL            => _T("Email,URL,IM"),
                    Members::FILTER_JOB             => _T("Job"),
                    Members::FILTER_INFOS           => _T("Infos")
                ),
                'filter_membership_options' => array(
                    0 => _T("All members"),
                    3 => _T("Up to date members"),
                    1 => _T("Close expiries"),
                    2 => _T("Latecomers"),
                    4 => _T("Never contributed"),
                    5 => _T("Staff members"),
                    6 => _T("Administrators")
                ),
                'filter_accounts_options'   => array(
                    0 => _T("All accounts"),
                    1 => _T("Active accounts"),
                    2 => _T("Inactive accounts")
                )
            )
        );
    }
)->name('members');

//members list filtering
$app->post(
    '/members/filter',
    $authenticate($app),
    function ($from = 'members') use ($app, &$session) {
        $request = $app->request();

        if ( isset($session['filters']['members']) ) {
            //CAUTION: this one may be simple or advanced, display must change
            $filters = unserialize($session['filters']['members']);
        } else {
            $filters = new MembersList();
        }

        //reintialize filters
        if ( $request->post('clear_filter') ) {
            $filters->reinit();
            if ($filters instanceof AdvancedMembersList) {
                $filters = new MembersList();
            }
        } else {
            //string to filter
            if ( $request->post('filter_str') !== null ) { //filter search string
                $filters->filter_str = stripslashes(
                    htmlspecialchars($request->post('filter_str'), ENT_QUOTES)
                );
            }
            //field to filter
            if ( $request->post('filter_field') !== null ) {
                if ( is_numeric($request->post('filter_field')) ) {
                    $filters->field_filter = $request->post('filter_field');
                }
            }
            //membership to filter
            if ( $request->post('filter_membership') !== null ) {
                if ( is_numeric($request->post('filter_membership')) ) {
                    $filters->membership_filter
                        = $request->post('filter_membership');
                }
            }
            //account status to filter
            if ( $request->post('filter_account') !== null ) {
                if ( is_numeric($request->post('filter_account')) ) {
                    $filters->account_status_filter
                        = $request->post('filter_account');
                }
            }
            //email filter
            if ( $request->post('email_filter') !== null ) {
                $filters->email_filter = (int)$request->post('email_filter');
            }
            //group filter
            if ( $request->post('group_filter') !== null
                && $request->post('group_filter') > 0
            ) {
                $filters->group_filter = (int)$request->post('group_filter');
            }
        }

        $session['filters']['members'] = serialize($filters);

        $app->redirect(
            $app->urlFor($from)
        );

    }
)->name('filter-memberslist');

//members self card
$app->get(
    '/member/me',
    $authenticate($app),
    function () use ($app, $login) {
        $deps = array(
            'picture'   => false,
            'groups'    => false,
            'dues'      => false
        );
        $member = new Adherent($login->login, $deps);
        $app->redirect(
            $app->urlFor('member', [$member->id])
        );
    }
)->name('me');

//members card
$app->get(
    '/member/:id',
    $authenticate($app),
    function ($id) use ($app, $login, $session, $i18n, $preferences,
        $members_fields, $members_fields_cats
    ) {
        $dyn_fields = new DynamicFields();

        $member = new Adherent();
        $member->load($id);

        if ( $login->id != $id && !$login->isAdmin() && !$login->isStaff() ) {
            //check if requested member is part of managed groups
            $groups = $member->groups;
            $is_managed = false;
            foreach ( $groups as $g ) {
                if ( $login->isGroupManager($g->getId()) ) {
                    $is_managed = true;
                    break;
                }
            }
            if ( $is_managed !== true ) {
                //requested member is not part of managed groups, fall back to logged
                //in member
                $member->load($login->id);
            }
        }

        $navigate = array();

        if ( isset($session['filters']['members']) ) {
            $filters =  unserialize($session['filters']['members']);
        } else {
            $filters = new MembersList();
        }

        if ( ($login->isAdmin() || $login->isStaff()) && count($filters) > 0 ) {
            $m = new Members($filters);
            $ids = $m->getList(false, array(Adherent::PK, 'nom_adh', 'prenom_adh'));
            $ids = $ids->toArray();
            foreach ( $ids as $k=>$m ) {
                if ( $m['id_adh'] == $member->id ) {
                    $navigate = array(
                        'cur'  => $m['id_adh'],
                        'count' => count($ids),
                        'pos' => $k+1
                    );
                    if ( $k > 0 ) {
                        $navigate['prev'] = $ids[$k-1]['id_adh'];
                    }
                    if ( $k < count($ids)-1 ) {
                        $navigate['next'] = $ids[$k+1]['id_adh'];
                    }
                    break;
                }
            }
        }

        //Set caller page ref for cards error reporting
        //$session['caller'] = 'voir_adherent.php?id_adh='.$id_adh;

        // declare dynamic field values
        $adherent['dyn'] = $dyn_fields->getFields('adh', $id, true);

        // - declare dynamic fields for display
        $disabled['dyn'] = array();
        $dynamic_fields = $dyn_fields->prepareForDisplay(
            'adh',
            $adherent['dyn'],
            $disabled['dyn'],
            0
        );

        //if we got a mail warning when adding/editing a member,
        //we show it and delete it from session
        /*if ( isset($session['mail_warning']) ) {
            $warning_detected[] = $session['mail_warning'];
            unset($session['mail_warning']);
        }
        $tpl->assign('warning_detected', $warning_detected);
        if ( isset($session['account_success']) ) {
            $success_detected = unserialize($session['account_success']);
            unset($session['account_success']);
        }*/

        // flagging fields visibility
        $fc = new FieldsConfig(Adherent::TABLE, $members_fields, $members_fields_cats);
        $visibles = $fc->getVisibilities();

        $display_elements = $fc->getDisplayElements();

        $app->render(
            'voir_adherent.tpl',
            array(
                'page_title'        => _T("Member Profile"),
                'require_dialog'    => true,
                'member'            => $member,
                'data'              => $adherent,
                'navigate'          => $navigate,
                'pref_lang_img'     => $i18n->getFlagFromId($member->language),
                'pref_lang'         => ucfirst($i18n->getNameFromId($member->language)),
                'pref_card_self'    => $preferences->pref_card_self,
                'dynamic_fields'    => $dynamic_fields,
                'groups'            => Groups::getSimpleList(),
                'time'              => time(),
                'visibles'          => $visibles,
                'display_elements'  => $display_elements
            )
        );

    }
)->name('member');

