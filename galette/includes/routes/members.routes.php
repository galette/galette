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
use Galette\Filters\AdvancedMembersList;
use Galette\Entity\FieldsConfig;
use Galette\Entity\Politeness;
use Galette\Entity\Contribution;
use Galette\Repository\Groups;
use Galette\Entity\Adherent;
use Galette\IO\PdfMembersCards;

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
    '/members(/:option/:value)',
    $authenticate($app),
    function ($option = null, $value = null) use (
        $app, $login, &$session
    ) {
        if ( isset($session['filters']['members']) ) {
            $filters = unserialize($session['filters']['members']);
        } else {
            $filters = new MembersList();
        }

        if ( $option !== null ) {
            switch ( $option ) {
            case 'page':
                $filters->current_page = (int)$value;
                break;
            case 'order':
                $filters->orderby = $value;
                break;
            }
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

        $view = $app->view();

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($app, $view, false);
        $filters->setViewCommonsFilters($view);

        $session['filters']['members'] = serialize($filters);

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
                'adv_filters'           => $filters instanceof AdvancedMembersList
            )
        );
    }
)->name(
    'members'
)->conditions(
    array(
        'option'    => '(page|order)',
        'value'     => '\d+'
    )
);

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

        } else if ( $request->post('clear_adv_filter') ) {
            $session['filters']['members'] = null;
            unset($session['filters']['members']);
            $app->redirect(
                $app->urlFor('advanced-search')
            );
        } else if ( $request->post('adv_criterias') ) {
            $app->redirect(
                $app->urlFor('advanced-search')
            );
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
            //number of rows to show
            if ( $request->post('nbshow') !== null ) {
                $filters->show = $request->post('nbshow');
            }

            if ( $request->post('advanced_filtering') !== null ) {
                if ( !$filters instanceof AdvancedMembersList ) {
                    $filters = new AdvancedMembersList($filters);
                }
                //Advanced filters
                $posted = $request->post();
                $filters->reinit();
                unset($posted['advanced_filtering']);
                $freed = false;
                foreach ( $posted as $k=>$v ) {
                    if ( strpos($k, 'free_', 0) === 0 ) {
                        if ( !$freed ) {
                            $i = 0;
                            foreach ( $posted['free_field'] as $f ) {
                                if ( trim($f) !== ''
                                    && trim($posted['free_text'][$i]) !== ''
                                ) {
                                    $fs_search = $posted['free_text'][$i];
                                    $log_op
                                        = (int)$posted['free_logical_operator'][$i];
                                    $qry_op
                                        = (int)$posted['free_query_operator'][$i];
                                    $fs = array(
                                        'idx'       => $i,
                                        'field'     => $f,
                                        'search'    => $fs_search,
                                        'log_op'    => $log_op,
                                        'qry_op'    => $qry_op
                                    );
                                    $filters->free_search = $fs;
                                }
                                $i++;
                            }
                            $freed = true;
                        }
                    } else {
                        switch($k) {
                        case 'filter_field':
                            $k = 'field_filter';
                            break;
                        case 'filter_membership':
                            $k= 'membership_filter';
                            break;
                        case 'filter_account':
                            $k = 'account_status_filter';
                            break;
                        case 'contrib_min_amount':
                        case 'contrib_max_amount':
                            if ( trim($v) !== '' ) {
                                $v = (float)$v;
                            } else {
                                $v = null;
                            }
                            break;
                        }
                        $filters->$k = $v;
                    }
                }
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

//advanced search page
$app->get(
    '/advanced-search',
    $authenticate($app),
    function () use ($app, &$session, $members_fields, $members_fields_cats) {

        if ( isset($session['filters']['members']) ) {
            $filters = unserialize($session['filters']['members']);
            if ( !$filters instanceof AdvancedMembersList ) {
                $filters = new AdvancedMembersList($filters);
            }
        } else {
            $filters = new AdvancedMembersList();
        }

        $groups = new Galette\Repository\Groups();
        $groups_list = $groups->getList();

        //we want only visibles fields
        $fields = $members_fields;
        $fc = new FieldsConfig(
            Adherent::TABLE,
            $members_fields,
            $members_fields_cats
        );
        $visibles = $fc->getVisibilities();

        foreach ( $fields as $k=>$f ) {
            if ( $visibles[$k] == 0 ) {
                unset($fields[$k]);
            }
        }

        //dynamic fields
        $df = new DynamicFields();
        $dynamic_fields = $df->prepareForDisplay(
            'adh',
            array(),
            array(),
            0
        );

        $cdynamic_fields = $df->prepareForDisplay(
            'contrib',
            array(),
            array(),
            0
        );

        //Status
        $statuts = new Galette\Entity\Status();

        //Contributions types
        $ct = new Galette\Entity\ContributionsTypes();

        //Payments types
        $pt = array(
            Contribution::PAYMENT_OTHER         => _T("Other"),
            Contribution::PAYMENT_CASH          => _T("Cash"),
            Contribution::PAYMENT_CREDITCARD    => _T("Credit card"),
            Contribution::PAYMENT_CHECK         => _T("Check"),
            Contribution::PAYMENT_TRANSFER      => _T("Transfer"),
            Contribution::PAYMENT_PAYPAL        => _T("Paypal")
        );

        $view = $app->view();
        $filters->setViewCommonsFilters($view);

        $app->render(
            'advanced_search.tpl',
            array(
                'page_title'            => _T("Advanced search"),
                'require_dialog'        => true,
                'require_calendar'      => true,
                'require_sorter'        => true,
                'filter_groups_options' => $groups_list,
                'search_fields'         => $fields,
                'dynamic_fields'        => $dynamic_fields,
                'cdynamic_fields'       => $cdynamic_fields,
                'statuts'               => $statuts->getList(),
                'contributions_types'   => $ct->getList(),
                'filters'               => $filters,
                'payments_types'        => $pt
            )
        );


    }
)->name('advanced-search');

//Batch actions on members list
$app->post(
    '/members/batch',
    $authenticate($app),
    function () use ($app, &$session) {
        $request = $app->request();

        if ( $request->post('member_sel') ) {
            if ( isset($session['filters']['members']) ) {
                $filters =  unserialize($session['filters']['members']);
            } else {
                $filters = new MembersList();
            }

            $filters->selected = $request->post('member_sel');
            $session['filters']['members'] = serialize($filters);

            if ( $request->post('cards') ) {
                $app->redirect(
                    $app->urlFor('pdf-members-cards')
                );
            }
        } else {
            $app->flash(
                'error_detected',
                array(
                    _T("No member was selected, please check at least one name.")
                )
            );
            $app->redirect(
                $app->urlFor('members')
            );
        }
    }
)->name('batch-memberslist');

//PDF members cards
$app->get(
    '/members/cards',
    $authenticate($app),
    function () use ($app, $preferences, $session) {
        if ( isset($session['filters']['members']) ) {
            $filters =  unserialize($session['filters']['members']);
        } else {
            $filters = new MembersList();
        }

        $request = $app->request();
        if ( $request->get(Adherent::PK)
            && $request->get(Adherent::PK) > 0
        ) {
            // If we are called from "voir_adherent.php" get unique id value
            $unique = $request->get(Adherent::PK);
        } else {
            if ( count($filters->selected) == 0 ) {
                Analog::log(
                    'No member selected to generate members cards',
                    Analog::INFO
                );
                $app->flash(
                    'error_detected',
                    array(
                        _T("No member was selected, please check at least one name.")
                    )
                );
                $app->redirect(
                    $app->urlFor('members')
                );
            }
        }

        // Fill array $selected with selected ids
        $selected = array();
        if ( isset($unique) && $unique ) {
            $selected[] = $unique;
        } else {
            $selected = $filters->selected;
        }

        $m = new Members();
        $members = $m->getArrayList(
            $selected,
            array('nom_adh', 'prenom_adh'),
            true
        );

        if ( !is_array($members) || count($members) < 1 ) {
            Analog::log(
                'An error has occured, unable to get members list.',
                Analog::ERROR
            );

            $app->flash(
                'error_detected',
                array(
                    _T("Unable to get members list.")
                )
            );
            $app->redirect(
                $app->urlFor('members')
            );
        }

        $pdf = new PdfMembersCards($preferences);
        $pdf->drawCards($members);
        $pdf->Output(_T("Cards") . '.pdf', 'D');
    }
)->name('pdf-members-cards');
