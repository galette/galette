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
use Galette\Core\Mailing;
use Galette\Core\GaletteMail;
use Galette\Repository\Members;
use Galette\Filters\MembersList;
use Galette\Filters\AdvancedMembersList;
use Galette\Entity\FieldsConfig;
use Galette\Entity\Politeness;
use Galette\Entity\Contribution;
use Galette\Repository\Groups;
use Galette\Entity\Adherent;
use Galette\IO\PdfMembersCards;
use Galette\IO\PdfMembersLabels;
use Galette\IO\Csv;
use Galette\IO\CsvOut;
use Galette\Entity\Status;
use Galette\Repository\Titles;

//self subscription
$app->get(
    '/subscribe',
    function () use ($app, $zdb, $preferences, $login, $i18n,
        $members_fields, $members_fields_cats
    ) {
        if ( !$preferences->pref_bool_selfsubscribe || $login->isLogged() ) {

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }

        $dyn_fields = new DynamicFields();

        $member = new Adherent();
        //mark as self membership
        $member->setSelfMembership();

        // flagging required fields
        $fc = new FieldsConfig(
            Adherent::TABLE,
            $members_fields,
            $members_fields_cats
        );
        $required = $fc->getRequired();
        // flagging fields visibility
        $visibles = $fc->getVisibilities();
        $form_elements = $fc->getFormElements(true);

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
                'titles_list'       => Titles::getList($zdb),
                //self_adh specific
                'spam_pass'         => $spam_pass,
                'spam_img'          => $spam_img,
                'fieldsets'         => $form_elements['fieldsets'],
                'hidden_elements'   => $form_elements['hiddens']
            )
        );

    }
)->setName('subscribe');

//members list CSV export
$app->get(
    '/members/export/csv',
    function () use ($app, $session, $login, $zdb,
        $members_fields, $members_fields_cats
    ) {
        $csv = new CsvOut();

        if ( isset($session['filters']['members']) ) {
            //CAUTION: this one may be simple or advanced, display must change
            $filters = unserialize($session['filters']['members']);
        } else {
            $filters = new MembersList();
        }

        $export_fields = null;
        if ( file_exists(GALETTE_CONFIG_PATH  . 'local_export_fields.inc.php') ) {
            include_once GALETTE_CONFIG_PATH  . 'local_export_fields.inc.php';
            $export_fields = $fields;
        }

        // fields visibility
        $fc = new FieldsConfig(
            Adherent::TABLE,
            $members_fields,
            $members_fields_cats
        );
        $visibles = $fc->getVisibilities();
        $fields = array();
        $headers = array();
        foreach ( $members_fields as $k=>$f ) {
            if ( $k !== 'mdp_adh'
                && $export_fields === null
                || (is_array($export_fields) && in_array($k, $export_fields))
            ) {
                if ( $visibles[$k] == FieldsConfig::VISIBLE ) {
                    $fields[] = $k;
                    $labels[] = $f['label'];
                } else if ( ($login->isAdmin()
                    || $login->isStaff()
                    || $login->isSuperAdmin())
                    && $visibles[$k] == FieldsConfig::ADMIN
                ) {
                    $fields[] = $k;
                    $labels[] = $f['label'];
                }
            }
        }

        $members = new Members($filters);
        $members_list = $members->getArrayList(
            $filters->selected,
            null,
            false,
            false,
            $fields,
            true
        );

        $s = new Status();
        $statuses = $s->getList();

        $t = new Titles();
        $titles = $t->getList($zdb);

        foreach ($members_list as &$member ) {
            if ( isset($member->id_statut) ) {
                //add textual status
                $member->id_statut = $statuses[$member->id_statut];
            }

            if ( isset($member->titre_adh) ) {
                //add textuel title
                $member->titre_adh = $titles[$member->titre_adh]->short;
            }

            //handle dates
            if (isset($member->date_crea_adh) ) {
                if ( $member->date_crea_adh != ''
                    && $member->date_crea_adh != '1901-01-01'
                ) {
                    $dcrea = new DateTime($member->date_crea_adh);
                    $member->date_crea_adh = $dcrea->format(_T("Y-m-d"));
                } else {
                    $member->date_crea_adh = '';
                }
            }

            if ( isset($member->date_modif_adh) ) {
                if ( $member->date_modif_adh != ''
                    && $member->date_modif_adh != '1901-01-01'
                ) {
                    $dmodif = new DateTime($member->date_modif_adh);
                    $member->date_modif_adh = $dmodif->format(_T("Y-m-d"));
                } else {
                    $member->date_modif_adh = '';
                }
            }

            if ( isset($member->date_echeance) ) {
                if ( $member->date_echeance != ''
                    && $member->date_echeance != '1901-01-01'
                ) {
                    $dech = new DateTime($member->date_echeance);
                    $member->date_echeance = $dech->format(_T("Y-m-d"));
                } else {
                    $member->date_echeance = '';
                }
            }

            if ( isset($member->ddn_adh) ) {
                if ( $member->ddn_adh != ''
                    && $member->ddn_adh != '1901-01-01'
                ) {
                    $ddn = new DateTime($member->ddn_adh);
                    $member->ddn_adh = $ddn->format(_T("Y-m-d"));
                } else {
                    $member->ddn_adh = '';
                }
            }

            if ( isset($member->sexe_adh) ) {
                //handle gender
                switch ( $member->sexe_adh ) {
                case Adherent::MAN:
                    $member->sexe_adh = _T("Man");
                    break;
                case Adherent::WOMAN:
                    $member->sexe_adh = _T("Woman");
                    break;
                case Adherent::NC:
                    $member->sexe_adh = _T("Unspecified");
                    break;
                }
            }

            //handle booleans
            if ( isset($member->activite_adh) ) {
                $member->activite_adh
                    = ($member->activite_adh) ? _T("Yes") : _T("No");
            }
            if ( isset($member->bool_admin_adh) ) {
                $member->bool_admin_adh
                    = ($member->bool_admin_adh) ? _T("Yes") : _T("No");
            }
            if ( isset($member->bool_exempt_adh) ) {
                $member->bool_exempt_adh
                    = ($member->bool_exempt_adh) ? _T("Yes") : _T("No");
            }
            if ( isset($member->bool_display_info) ) {
                $member->bool_display_info
                    = ($member->bool_display_info) ? _T("Yes") : _T("No");
            }
        }
        $filename = 'filtered_memberslist.csv';
        $filepath = CsvOut::DEFAULT_DIRECTORY . $filename;
        $fp = fopen($filepath, 'w');
        if ( $fp ) {
            $res = $csv->export(
                $members_list,
                Csv::DEFAULT_SEPARATOR,
                Csv::DEFAULT_QUOTE,
                $labels,
                $fp
            );
            fclose($fp);
            $written[] = array(
                'name' => $filename,
                'file' => $filepath
            );
        }

        $response = $app->response;
        if (file_exists(CsvOut::DEFAULT_DIRECTORY . $filename) ) {
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set(
                'Content-Disposition',
                'attachment; filename="' . $filename . '";'
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->setBody(
                readfile(CsvOut::DEFAULT_DIRECTORY . $filename)
            );
        } else {
            Analog::log(
                'A request has been made to get an exported file named `' .
                $filename .'` that does not exists.',
                Analog::WARNING
            );
            $response->setStatus(404);
        }
    }
)->setName('csv-memberslist')->add($authenticate);

//members list
$app->get(
    '/members[/{option}/{value}]',
    function ($request, $response, $args = []) {
        $option = null;
        if (isset($args['option'])) {
            $option = $args['option'];
        }
        $value = null;
        if (isset($args['value'])) {
            $value = $args['value'];
        }

        if (isset($this->session['filters']['members'])) {
            $filters = unserialize($this->session['filters']['members']);
        } else {
            $filters = new MembersList();
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

        $members = new Members($filters);

        $members_list = array();
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $members_list = $members->getMembersList(true);
        } else {
            $members_list = $members->getManagedMembersList(true);
        }

        $groups = new Groups();
        $groups_list = $groups->getList();

        //$view = $app->view();

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($this->router, $this->view, false);
        $filters->setViewCommonsFilters($this->preferences, $view);

        $this->session['filters']['members'] = serialize($filters);


        // display page
        $this->view->render(
            $response,
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
        return $response;
    }
)->setName(
    'members'
)->add($authenticate)/*->conditions(
    array(
        'option'    => '(page|order)',
        'value'     => '\d+'
    )
)*/;

//members list filtering
$app->post(
    '/members/filter',
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

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('advanced-search'));
        } else if ( $request->post('adv_criterias') ) {

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('advanced-search'));
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

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor($from));
    }
)->setName('filter-memberslist')->add($authenticate);

//members self card
$app->get(
    '/member/me',
    function () use ($app, $login) {
        if ($login->isSuperAdmin()) {

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }
        $deps = array(
            'picture'   => false,
            'groups'    => false,
            'dues'      => false
        );
        $member = new Adherent($login->login, $deps);

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('member'), ['id' => $member->id]);
    }
)->setName('me')->add($authenticate);

//members card
$app->get(
    '/member/:id',
    function ($id) use ($app, $login, $session, $i18n, $preferences,
        $members_fields, $members_fields_cats
    ) {
        $dyn_fields = new DynamicFields();

        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true
        );
        $member = new Adherent((int)$id, $deps);

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
                $id = $login->id;
            }
        }

        $navigate = array();

        if ( isset($session['filters']['members']) ) {
            $filters =  unserialize($session['filters']['members']);
        } else {
            $filters = new MembersList();
        }

        if ( ($login->isAdmin() || $login->isStaff() || $login->isGroupManager()) && count($filters) > 0 ) {
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
)->setName('member')->add($authenticate);

$app->get(
    '/member/:action(/:id)',
    function (
        $action,
        $id = null
    ) use (
        $app,
        $zdb,
        $login,
        $session,
        $i18n,
        $preferences,
        $members_fields,
        $members_fields_cats
    ) {
        if ($action === 'edit' && $id === null) {
            throw new \RuntimeException(
                _T("Member ID cannot ben null calling edit route!")
            );
        }
        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true
        );
        $route_params = [];
        $member = new Adherent(null, $deps);
        //TODO: dynamic fields should be handled by Adherent object
        $dyn_fields = new DynamicFields();

        if ( $login->isAdmin() || $login->isStaff() || $login->isGroupManager() ) {
            if ( $id !== null ) {
                $adherent['id_adh'] = $id;
                $member->load($id);
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
                $disabled = $member->adm_edit_disabled_fields
                    + $member->staff_edit_disabled_fields;
            } else {
                $disabled = $member->adm_edit_disabled_fields
                    + $member->staff_edit_disabled_fields
                    + $member->disabled_fields;
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
        $fc = new FieldsConfig(Adherent::TABLE, $members_fields, $members_fields_cats);

        //address and mail fields are not required if member has a parent
        $no_parent_required = array(
            'adresse_adh',
            'adresse2_adh',
            'cp_adh',
            'ville_adh',
            'email_adh'
        );
        if ($member->hasParent()) {
            foreach ($no_parent_required as $field) {
                if ($fc->isRequired($field)) {
                    $fc->setNotRequired($field);
                } else {
                    $i = array_search($field, $no_parent_required);
                    unset($no_parent_required[$i]);
                }
            }
            $route_params['no_parent_required'] = $no_parent_required;
        }

        // flagging required fields invisible to members
        if ( $login->isAdmin() || $login->isStaff() ) {
            $fc->setNotRequired('activite_adh');
            $fc->setNotRequired('id_statut');
        }

        $required = $fc->getRequired();
        // flagging fields visibility
        $visibles = $fc->getVisibilities();

        $real_requireds = array_diff(array_keys($required), array_keys($disabled));

        if ( $member->id !== false &&  $member->id !== '' ) {
            $adherent['dyn'] = $dyn_fields->getFields('adh', $member->id, false);
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

        if ( isset($session['mail_warning']) ) {
            //warning will be showed here, no need to keep it longer into session
            unset($session['mail_warning']);
        }

        //Status
        $statuts = new Galette\Entity\Status();

        //Groups
        $groups = new Groups();
        $groups_list = $groups->getSimpleList(true);

        $form_elements = $fc->getFormElements();

        $app->render(
            'member.tpl',
            array_merge(
                $route_params,
                array(
                    'parent_tpl'        => 'page.tpl',
                    'navigate'          => $navigate,
                    'require_dialog'    => true,
                    'page_title'        => $title,
                    'required'          => $required,
                    'visibles'          => $visibles,
                    'disabled'          => $disabled,
                    'member'            => $member,
                    'data'              => $adherent,
                    'self_adh'          => false,
                    'dynamic_fields'    => $dynamic_fields,
                    'languages'         => $i18n->getList(),
                    'require_calendar'  => true,
                    // pseudo random int
                    'time'              => time(),
                    'titles_list'       => Titles::getList($zdb),
                    'statuts'           => $statuts->getList(),
                    'groups'            => $groups_list,
                    'fieldsets'         => $form_elements['fieldsets'],
                    'hidden_elements'   => $form_elements['hiddens']
                )
            )
        );

    }
)->setName(
    'editmember'
)->add($authenticate)/*->conditions(
    array(
        'action' => '(edit|add)',
    )
)*/;

$app->post(
    '/member/store',
    function () use (
        $app,
        $login,
        $session,
        $preferences,
        $members_fields,
        $members_fields_cats,
        &$success_detected,
        &$warning_detected,
        &$error_detected
    ) {
        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true
        );
        $member = new Adherent(null, $deps);
        //TODO: dynamic fields should be handled by Adherent object
        $dyn_fields = new DynamicFields();

        // new or edit
        $adherent['id_adh'] = get_numeric_form_value('id_adh', '');

        if ( $login->isAdmin() || $login->isStaff() || $login->isGroupManager() ) {
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
                $disabled = $member->adm_edit_disabled_fields
                    + $member->staff_edit_disabled_fields;
            } else {
                $disabled = $member->adm_edit_disabled_fields
                    + $member->staff_edit_disabled_fields
                    + $member->disabled_fields;
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
        $fc = new FieldsConfig(Adherent::TABLE, $members_fields, $members_fields_cats);

        // password required if we create a new member
        if ( $member->id != '' ) {
            $fc->setNotRequired('mdp_adh');
        }

        //address and mail fields are not required if member has a parent
        $no_parent_required = array(
            'adresse_adh',
            'adresse2_adh',
            'cp_adh',
            'ville_adh',
            'email_adh'
        );
        if ($member->hasParent()) {
            foreach ($no_parent_required as $field) {
                if ($fc->isRequired($field)) {
                    $fc->setNotRequired($field);
                } else {
                    $i = array_search($field, $no_parent_required);
                    unset($no_parent_required[$i]);
                }
            }
            $tpl->assign('no_parent_required', $no_parent_required);
        }

        // flagging required fields invisible to members
        if ( $login->isAdmin() || $login->isStaff() ) {
            $fc->setNotRequired('activite_adh');
            $fc->setNotRequired('id_statut');
        }

        $required = $fc->getRequired();
        // flagging fields visibility
        $visibles = $fc->getVisibilities();

        $real_requireds = array_diff(array_keys($required), array_keys($disabled));

        // Validation
        if ( isset($_POST[array_shift($real_requireds)]) ) {
            $adherent['dyn'] = $dyn_fields->extractPosted(
                $_POST,
                $_FILES,
                $disabled, $member->id
            );
            $dyn_fields_errors = $dyn_fields->getErrors();
            if ( count($dyn_fields_errors) > 0 ) {
                $error_detected = array_merge($error_detected, $dyn_fields_errors);
            }
            // regular fields
            $valid = $member->check($_POST, $required, $disabled);
            if ( $valid !== true ) {
                $error_detected = array_merge($error_detected, $valid);
            }

            if ( count($error_detected) == 0) {
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
                                    'name_adh'      => custom_html_entity_decode(
                                        $member->sname
                                    ),
                                    'firstname_adh' => custom_html_entity_decode(
                                        $member->surname
                                    ),
                                    'lastname_adh'  => custom_html_entity_decode(
                                        $member->setName
                                    ),
                                    'mail_adh'      => custom_html_entity_decode(
                                        $member->email
                                    ),
                                    'login_adh'     => custom_html_entity_decode(
                                        $member->login
                                    )
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
                                        'name_adh'      => custom_html_entity_decode(
                                            $member->sname
                                        ),
                                        'firstname_adh' => custom_html_entity_decode(
                                            $member->surname
                                        ),
                                        'lastname_adh'  => custom_html_entity_decode(
                                            $member->setName
                                        ),
                                        'mail_adh'      => custom_html_entity_decode(
                                            $member->email
                                        ),
                                        'login_adh'     => custom_html_entity_decode(
                                            $member->login
                                        ),
                                        'password_adh'  => custom_html_entity_decode(
                                            $_POST['mdp_adh']
                                        )
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
            }

            if ( count($error_detected) == 0 ) {

                // picture upload
                if ( isset($_FILES['photo']) ) {
                    if ( $_FILES['photo']['error'] === UPLOAD_ERR_OK ) {
                        if ( $_FILES['photo']['tmp_name'] !='' ) {
                            if ( is_uploaded_file($_FILES['photo']['tmp_name']) ) {
                                $res = $member->picture->store($_FILES['photo']);
                                if ( $res < 0 ) {
                                    $error_detected[]
                                        = $member->picture->getErrorMessage($res);
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
                if (count($warning_detected) > 0) {
                    $this->flash->addMessage(
                        'warning_detected',
                        $warning_detected
                    );
                }
                if (count($success_detected) > 0) {
                    $this->flash->addMessage(
                        'success_detected',
                        $success_detected
                    );
                }
                if ( !isset($_POST['id_adh']) ) {
                    //TODO: use route
                    header(
                        'location: ajouter_contribution.php?id_adh=' . $member->id
                    );
                    die();
                } elseif ( count($error_detected) == 0 ) {

                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $this->router->pathFor('member', ['id' => $member->id]));
                }
            } else {
                $this->flash->addMessage(
                    'error_detected',
                    $error_detected
                );
            }
        }
    }
)->setName('storemembers')->add($authenticate);

//advanced search page
$app->get(
    '/advanced-search',
    function () use ($app, &$session, $members_fields, $members_fields_cats, $preferences) {
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
        $filters->setViewCommonsFilters($preferences, $view);

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
)->setName('advanced-search')->add($authenticate);

//Batch actions on members list
$app->post(
    '/members/batch',
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
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('pdf-members-cards'));
            }

            if ( $request->post('labels') ) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('pdf-members-labels'));
            }

            if ( $request->post('mailing') ) {
                $options = array();
                if ( $request->post() ) {
                    $options['new'] = 'new';
                }

                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('mailing', $options));
            }

            if ( $request->post('attendance_sheet') ) {
                //TODO
            }

            if ( $request->post('csv') ) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('csv-memberslist'));
            }

        } else {
            $this->flash->addMessage(
                'error_detected',
                array(
                    _T("No member was selected, please check at least one name.")
                )
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }
    }
)->setName('batch-memberslist')->add($authenticate);

//PDF members cards
$app->get(
    '/members/cards',
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
                $this->flash->addMessage(
                    'error_detected',
                    array(
                        _T("No member was selected, please check at least one name.")
                    )
                );

                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('members'));
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

            $this->flash->addMessage(
                'error_detected',
                array(
                    _T("Unable to get members list.")
                )
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }

        $pdf = new PdfMembersCards($preferences);
        $pdf->drawCards($members);
        $pdf->Output(_T("Cards") . '.pdf', 'D');
    }
)->setName('pdf-members-cards')->add($authenticate);

//PDF members labels
$app->get(
    '/members/labels',
    function () use ($app, $preferences, $session) {

        if ( isset ($session['filters']['reminders_labels']) ) {
            $filters =  unserialize($session['filters']['reminders_labels']);
            unset($session['filters']['reminders_labels']);
        } elseif ( isset($session['filters']['members']) ) {
            $filters =  unserialize($session['filters']['members']);
        } else {
            $filters = new MembersList();
        }

        $request = $app->request();

        $members = null;
        if ( $request->get('from') !== null
            && $request->get('from') === 'mailing'
        ) {
            //if we're from mailing, we have to retrieve
            //its unreachables members for labels
            $mailing = unserialize($session['mailing']);
            $members = $mailing->unreachables;
        } else {
            if ( count($filters->selected) == 0 ) {
                Analog::log('No member selected to generate labels', Analog::INFO);
                $this->flash->addMessage(
                    'error_detected',
                    array(
                        _T("No member was selected, please check at least one name.")
                    )
                );

                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('members'));
            }

            $m = new Members();
            $members = $m->getArrayList(
                $filters->selected,
                array('nom_adh', 'prenom_adh')
            );
        }

        if ( !is_array($members) || count($members) < 1 ) {
            Analog::log(
                'An error has occured, unable to get members list.',
                Analog::ERROR
            );

            $this->flash->addMessage(
                'error_detected',
                array(
                    _T("Unable to get members list.")
                )
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }

        $pdf = new PdfMembersLabels($preferences);
        $pdf->drawLabels($members);
        $pdf->Output(_T("labels_print_filename") . '.pdf', 'D');
    }
)->setName('pdf-members-labels')->add($authenticate);

//mailing
$app->get(
    '/mailing',
    function () use ($app, $preferences, &$session,
        &$success_detected, &$warning_detected, &$error_detected
    ) {
        $request = $app->request();
        //We're done :-)
        if ( isset($_POST['mailing_done'])
            || isset($_POST['mailing_cancel'])
            || isset($_GET['mailing_new'])
            || isset($_GET['reminder'])
        ) {
            if ( isset($session['mailing']) ) {
                // check for temporary attachments to remove
                $m = unserialize($session['mailing']);
                $m->removeAttachments(true);
            }
            $session['mailing'] = null;
            unset($session['mailing']);
            if ( !isset($_GET['mailing_new']) && !isset($_GET['reminder']) ) {
                header('location: gestion_adherents.php');
                exit(0);
            }
        }

        $params = array();

        if ( $preferences->pref_mail_method == Mailing::METHOD_DISABLED
            && !GALETTE_MODE === 'DEMO'
        ) {
            $hist->add(
                _T("Trying to load mailing while mail is disabled in preferences.")
            );
        } else {
            if ( isset($session['filters']['members']) ) {
                $filters =  unserialize($session['filters']['members']);
            } else {
                $filters = new MembersList();
            }

            if ( isset($session['mailing'])
                && !isset($_POST['mailing_cancel'])
                && !isset($_GET['from'])
                && !isset($_GET['reset'])
            ) {
                $mailing = unserialize($session['mailing']);
            } else if (isset($_GET['from']) && is_numeric($_GET['from'])) {
                $mailing = new Mailing(null, $_GET['from']);
                MailingHistory::loadFrom((int)$_GET['from'], $mailing);
            } else if (isset($_GET['reminder'])) {
                //FIXME: use a constant!
                $filters->reinit();
                $filters->membership_filter = Members::MEMBERSHIP_LATE;
                $filters->account_status_filter = Members::ACTIVE_ACCOUNT;
                $m = new Members($filters);
                $members = $m->getList(true);
                $mailing = new Mailing(($members !== false) ? $members : null);
            } else {
                if ( count($filters->selected) == 0
                    && !isset($_GET['mailing_new'])
                    && !isset($_GET['reminder'])
                ) {
                    Analog::log(
                        '[mailing_adherents.php] No member selected for mailing',
                        Analog::WARNING
                    );

                    if ( isset($profiler) ) {
                        $profiler->stop();
                    }

                    header('location:gestion_adherents.php');
                    die();
                }
                $m = new Members();
                $members = $m->getArrayList($filters->selected);
                $mailing = new Mailing(($members !== false) ? $members : null);
            }

            if ( isset($_POST['mailing_go'])
                || isset($_POST['mailing_reset'])
                || isset($_POST['mailing_confirm'])
                || isset($_POST['mailing_save'])
            ) {
                if ( trim($_POST['mailing_objet']) == '' ) {
                    $error_detected[] = _T("Please type an object for the message.");
                } else {
                    $mailing->subject = $_POST['mailing_objet'];
                }

                if ( trim($_POST['mailing_corps']) == '') {
                    $error_detected[] = _T("Please enter a message.");
                } else {
                    $mailing->message = $_POST['mailing_corps'];
                }

                $mailing->html = ( isset($_POST['mailing_html']) ) ? true : false;

                //handle attachments
                if ( isset($_FILES['files']) ) {
                    for ( $i = 0; $i < count($_FILES['files']['name']); $i++) {
                        if ( $_FILES['files']['error'][$i] === UPLOAD_ERR_OK ) {
                            if ( $_FILES['files']['tmp_name'][$i] !='' ) {
                                if ( is_uploaded_file($_FILES['files']['tmp_name'][$i]) ) {
                                    $da_file = array();
                                    foreach ( array_keys($_FILES['files']) as $key ) {
                                        $da_file[$key] = $_FILES['files'][$key][$i];
                                    }
                                    $res = $mailing->store($da_file);
                                    if ( $res < 0 ) {
                                        //what to do if one of attachments fail? should other be removed?
                                        $error_detected[] = $mailing->getAttachmentErrorMessage($res);
                                    }
                                }
                            }
                        } else if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                            Analog::log(
                                $mailing->getPhpErrorMessage($_FILES['files']['error'][$i]),
                                Analog::WARNING
                            );
                            $error_detected[] = $mailing->getPhpErrorMessage(
                                $_FILES['files']['error'][$i]
                            );
                        }
                    }
                }

                if ( count($error_detected) == 0
                    && !isset($_POST['mailing_reset'])
                    && !isset($_POST['mailing_save'])
                ) {
                    $mailing->current_step = Mailing::STEP_PREVIEW;
                } else {
                    $mailing->current_step = Mailing::STEP_START;
                }
            }

            if ( isset($_POST['mailing_confirm']) && count($error_detected) == 0 ) {

                $mailing->current_step = Mailing::STEP_SEND;
                //ok... let's go for fun
                $sent = $mailing->send();
                if ( $sent == Mailing::MAIL_ERROR ) {
                    $mailing->current_step = Mailing::STEP_START;
                    Analog::log(
                        '[mailing_adherents.php] Message was not sent. Errors: ' .
                        print_r($mailing->errors, true),
                        Analog::ERROR
                    );
                    foreach ( $mailing->errors as $e ) {
                        $error_detected[] = $e;
                    }
                } else {
                    $mlh = new MailingHistory($mailing);
                    $mlh->storeMailing(true);
                    Analog::log(
                        '[mailing_adherents.php] Message has been sent.',
                        Analog::INFO
                    );
                    $mailing->current_step = Mailing::STEP_SENT;
                    //cleanup
                    $filters->selected = null;
                    $session['filters']['members'] = serialize($filters);
                    $session['mailing'] = null;
                    unset($session['mailing']);
                }
            }

            if ( isset($_GET['remove_attachment']) ) {
                $mailing->removeAttachment($_GET['remove_attachment']);
            }

            if ( $mailing->current_step !== Mailing::STEP_SENT ) {
                $session['mailing'] = serialize($mailing);
            }

            /** TODO: replace that... */
            $session['labels'] = $mailing->unreachables;

            if ( !isset($_POST['html_editor_active'])
                || trim($_POST['html_editor_active']) == ''
            ) {
                $_POST['html_editor_active'] = $preferences->pref_editor_enabled;
            }

            if ( isset($_POST['mailing_save']) ) {
                //user requested to save the mailing
                $histo = new MailingHistory($mailing);
                if ( $histo->storeMailing() !== false ) {
                    $success_detected[] = _T("Mailing has been successfully saved.");
                    $params['mailing_saved'] = true;
                    $session['mailing'] = null;
                    unset($session['mailing']);
                    $head_redirect = array(
                        'timeout'   => 30,
                        'url'       => 'gestion_mailings.php'
                    );
                    $params['head_redirect'] = $head_redirect;
                }
            }

            $params = array_merge(
                $params,
                array(
                    'success_detected'  => $success_detected,
                    'warning_detected'  => $warning_detected,
                    'error_detected'    => $error_detected,
                    'mailing'           => $mailing,
                    'attachments'       => $mailing->attachments,
                    'html_editor'       => true,
                    'html_editor_active'=> $request->post('html_editor_active')
                )
            );
        }

        $app->render(
            'mailing_adherents.tpl',
            array_merge(
                array(
                    'page_title'            => _T("Mailing"),
                    'require_dialog'        => true
                ),
                $params
            )
        );

    }
)->setName('mailing')->add($authenticate);
