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
use Galette\Entity\Contribution;
use Galette\Repository\Groups;
use Galette\Repository\Reminders;
use Galette\Entity\Adherent;
use Galette\IO\PdfMembersCards;
use Galette\IO\PdfMembersLabels;
use Galette\IO\Csv;
use Galette\IO\CsvOut;
use Galette\Entity\Status;
use Galette\Repository\Titles;
use Galette\Entity\Texts;
use Galette\IO\Pdf;
use Galette\Core\MailingHistory;
use Galette\Entity\Group;
use Galette\IO\File;

//self subscription
$app->get(
    __('/subscribe', 'routes'),
    function ($request, $response) {
        if (!$this->preferences->pref_bool_selfsubscribe || $this->login->isLogged()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }

        $dyn_fields = new DynamicFields();

        if ($this->session->member !== null) {
            $member = $this->session->member;
            $this->session->member = null;
        } else {
            $member = new Adherent($this->zdb);
        }

        //mark as self membership
        $member->setSelfMembership();

        // flagging required fields
        $fc = $this->fields_config;
        $required = $fc->getRequired();
        // flagging fields visibility
        $visibles = $fc->getVisibilities();
        $form_elements = $fc->getFormElements($this->login, true);

        // disable some fields
        $disabled  = $member->disabled_fields;

        // DEBUT parametrage des champs
        // On recupere de la base la longueur et les flags des champs
        // et on initialise des valeurs par defaut

        $fields = Adherent::getDbFields($this->zdb);

        // - declare dynamic fields for display
        $disabled['dyn'] = array();
        if (!isset($adherent['dyn'])) {
            $adherent['dyn'] = array();
        }

        //image to defeat mass filling forms
        $spam = new PasswordImage();
        $spam_pass = $spam->newImage();
        $spam_img = $spam->getImage();

        $dynamic_fields = $dyn_fields->prepareForDisplay(
            'adh',
            $adherent['dyn'],
            $disabled['dyn'],
            1
        );

        // display page
        $this->view->render(
            $response,
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
                'languages'         => $this->i18n->getList(),
                'require_calendar'  => true,
                'autocomplete'      => true,
                // pseudo random int
                'time'              => time(),
                'titles_list'       => Titles::getList($this->zdb),
                //self_adh specific
                'spam_pass'         => $spam_pass,
                'spam_img'          => $spam_img,
                'fieldsets'         => $form_elements['fieldsets'],
                'hidden_elements'   => $form_elements['hiddens']
            )
        );
        return $response;
    }
)->setName('subscribe');

//members list CSV export
$app->get(
    __('/members/export/csv', 'routes'),
    function ($request, $response) {
        $csv = new CsvOut();

        if (isset($this->session->filter_members)) {
            //CAUTION: this one may be simple or advanced, display must change
            $filters = $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        $export_fields = null;
        if (file_exists(GALETTE_CONFIG_PATH  . 'local_export_fields.inc.php')) {
            include_once GALETTE_CONFIG_PATH  . 'local_export_fields.inc.php';
            $export_fields = $fields;
        }

        // fields visibility
        $fc = $this->fields_config;
        $visibles = $fc->getVisibilities();
        $fields = array();
        $headers = array();
        foreach ($this->members_fields as $k => $f) {
            if ($k !== 'mdp_adh'
                && $export_fields === null
                || (is_array($export_fields) && in_array($k, $export_fields))
            ) {
                if ($visibles[$k] == FieldsConfig::VISIBLE) {
                    $fields[] = $k;
                    $labels[] = $f['label'];
                } elseif (($this->login->isAdmin()
                    || $this->login->isStaff()
                    || $this->login->isSuperAdmin())
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

        $s = new Status($this->zdb);
        $statuses = $s->getList();

        $t = new Titles();
        $titles = $t->getList($this->zdb);

        foreach ($members_list as &$member) {
            if (isset($member->id_statut)) {
                //add textual status
                $member->id_statut = $statuses[$member->id_statut];
            }

            if (isset($member->titre_adh)) {
                //add textuel title
                $member->titre_adh = $titles[$member->titre_adh]->short;
            }

            //handle dates
            if (isset($member->date_crea_adh)) {
                if ($member->date_crea_adh != ''
                    && $member->date_crea_adh != '1901-01-01'
                ) {
                    $dcrea = new DateTime($member->date_crea_adh);
                    $member->date_crea_adh = $dcrea->format(__("Y-m-d"));
                } else {
                    $member->date_crea_adh = '';
                }
            }

            if (isset($member->date_modif_adh)) {
                if ($member->date_modif_adh != ''
                    && $member->date_modif_adh != '1901-01-01'
                ) {
                    $dmodif = new DateTime($member->date_modif_adh);
                    $member->date_modif_adh = $dmodif->format(__("Y-m-d"));
                } else {
                    $member->date_modif_adh = '';
                }
            }

            if (isset($member->date_echeance)) {
                if ($member->date_echeance != ''
                    && $member->date_echeance != '1901-01-01'
                ) {
                    $dech = new DateTime($member->date_echeance);
                    $member->date_echeance = $dech->format(__("Y-m-d"));
                } else {
                    $member->date_echeance = '';
                }
            }

            if (isset($member->ddn_adh)) {
                if ($member->ddn_adh != ''
                    && $member->ddn_adh != '1901-01-01'
                ) {
                    $ddn = new DateTime($member->ddn_adh);
                    $member->ddn_adh = $ddn->format(__("Y-m-d"));
                } else {
                    $member->ddn_adh = '';
                }
            }

            if (isset($member->sexe_adh)) {
                //handle gender
                switch ($member->sexe_adh) {
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
            if (isset($member->activite_adh)) {
                $member->activite_adh
                    = ($member->activite_adh) ? _T("Yes") : _T("No");
            }
            if (isset($member->bool_admin_adh)) {
                $member->bool_admin_adh
                    = ($member->bool_admin_adh) ? _T("Yes") : _T("No");
            }
            if (isset($member->bool_exempt_adh)) {
                $member->bool_exempt_adh
                    = ($member->bool_exempt_adh) ? _T("Yes") : _T("No");
            }
            if (isset($member->bool_display_info)) {
                $member->bool_display_info
                    = ($member->bool_display_info) ? _T("Yes") : _T("No");
            }
        }
        $filename = 'filtered_memberslist.csv';
        $filepath = CsvOut::DEFAULT_DIRECTORY . $filename;
        $fp = fopen($filepath, 'w');
        if ($fp) {
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

        if (file_exists(CsvOut::DEFAULT_DIRECTORY . $filename)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '";');
            header('Pragma: no-cache');
            echo readfile(CsvOut::DEFAULT_DIRECTORY . $filename);
        } else {
            Analog::log(
                'A request has been made to get an exported file named `' .
                $filename .'` that does not exists.',
                Analog::WARNING
            );
            $notFound = $this->notFoundHandler;
            return $notFound($request, $response);
        }
    }
)->setName('csv-memberslist')->add($authenticate);

//members list
$app->get(
    __('/members', 'routes') . '[/{option:' . __('page', 'routes') . '|' . __('order', 'routes') . '}/{value:\d+}]',
    function ($request, $response, $args = []) {
        $option = null;
        if (isset($args['option'])) {
            $option = $args['option'];
        }
        $value = null;
        if (isset($args['value'])) {
            $value = $args['value'];
        }

        if (isset($this->session->filter_members)) {
            $filters = $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        if ($option !== null) {
            switch ($option) {
                case __('page', 'routes'):
                    $filters->current_page = (int)$value;
                    break;
                case __('order', 'routes'):
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

        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getList();

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($this->router, $this->view->getSmarty(), false);
        $filters->setViewCommonsFilters($this->preferences, $this->view->getSmarty());

        $this->session->filter_members = $filters;

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
)->add($authenticate);

//members list filtering
$app->post(
    __('/members/filter', 'routes'),
    function ($request, $response) {
        $post = $request->getParsedBody();
        if (isset($this->session->filter_members)) {
            //CAUTION: this one may be simple or advanced, display must change
            $filters = $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        //reintialize filters
        if (isset($post['clear_filter'])) {
            $filters->reinit();
            if ($filters instanceof AdvancedMembersList) {
                $filters = new MembersList();
            }
        } elseif (isset($post['clear_adv_filter'])) {
            $this->session->filter_members = null;
            unset($this->session->filter_members);

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('advanced-search'));
        } elseif (isset($post['adv_criterias'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('advanced-search'));
        } else {
            //string to filter
            if (isset($post['filter_str'])) { //filter search string
                $filters->filter_str = stripslashes(
                    htmlspecialchars($post['filter_str'], ENT_QUOTES)
                );
            }
            //field to filter
            if (isset($post['filter_field'])) {
                if (is_numeric($post['filter_field'])) {
                    $filters->field_filter = $post['filter_field'];
                }
            }
            //membership to filter
            if (isset($post['filter_membership'])) {
                if (is_numeric($post['filter_membership'])) {
                    $filters->membership_filter
                        = $post['filter_membership'];
                }
            }
            //account status to filter
            if (isset($post['filter_account'])) {
                if (is_numeric($post['filter_account'])) {
                    $filters->account_status_filter
                        = $post['filter_account'];
                }
            }
            //email filter
            if (isset($post['email_filter'])) {
                $filters->email_filter = (int)$post['email_filter'];
            }
            //group filter
            if (isset($post['group_filter'])
                && $post['group_filter'] > 0
            ) {
                $filters->group_filter = (int)$post['group_filter'];
            }
            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = $post['nbshow'];
            }

            if (isset($post['advanced_filtering'])) {
                if (!$filters instanceof AdvancedMembersList) {
                    $filters = new AdvancedMembersList($filters);
                }
                //Advanced filters
                $filters->reinit();
                unset($post['advanced_filtering']);
                $freed = false;
                foreach ($post as $k => $v) {
                    if (strpos($k, 'free_', 0) === 0) {
                        if (!$freed) {
                            $i = 0;
                            foreach ($post['free_field'] as $f) {
                                if (trim($f) !== ''
                                    && trim($post['free_text'][$i]) !== ''
                                ) {
                                    $fs_search = $post['free_text'][$i];
                                    $log_op
                                        = (int)$post['free_logical_operator'][$i];
                                    $qry_op
                                        = (int)$post['free_query_operator'][$i];
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
                        switch ($k) {
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
                                if (trim($v) !== '') {
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

        $this->session->filter_members = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('members'));
    }
)->setName('filter-memberslist')->add($authenticate);

//members self card
$app->get(
    __('/member/me', 'routes'),
    function ($request, $response) {
        if ($this->login->isSuperAdmin()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }
        $dyn_fields = new DynamicFields();
        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true
        );

        $member = new Adherent($this->zdb, $this->login->login, $deps);
        $id = $member->id;
        var_dump($id);

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

        // flagging fields visibility
        $fc = $this->fields_config;
        $visibles = $fc->getVisibilities();

        $display_elements = $fc->getDisplayElements($this->login);

        // display page
        $this->view->render(
            $response,
            'voir_adherent.tpl',
            array(
                'page_title'        => _T("Member Profile"),
                'require_dialog'    => true,
                'member'            => $member,
                'data'              => $adherent,
                'pref_lang_img'     => $this->i18n->getFlagFromId($member->language),
                'pref_lang'         => ucfirst($this->i18n->getNameFromId($member->language)),
                'pref_card_self'    => $this->preferences->pref_card_self,
                'dynamic_fields'    => $dynamic_fields,
                'groups'            => Groups::getSimpleList(),
                'time'              => time(),
                'visibles'          => $visibles,
                'display_elements'  => $display_elements
            )
        );
    }
)->setName('me')->add($authenticate);

//members card
$app->get(
    __('/member', 'routes') . '/{id:\d+}',
    function ($request, $response, $args) {
        $id = $args['id'];
        $dyn_fields = new DynamicFields();

        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true
        );
        $member = new Adherent($this->zdb, (int)$id, $deps);

        if ($this->login->id != $id && !$this->login->isAdmin() && !$this->login->isStaff()) {
            //check if requested member is part of managed groups
            $groups = $member->groups;
            $is_managed = false;
            foreach ($groups as $g) {
                if ($this->login->isGroupManager($g->getId())) {
                    $is_managed = true;
                    break;
                }
            }
            if ($is_managed !== true) {
                //requested member is not part of managed groups,
                //fall back to logged in member
                Analog::log(
                    'Trying to display member #' . $id . ' without appropriate ACLs',
                    Analog::WARNING
                );

                return $response
                    ->withStatus(403)
                    ->withHeader(
                        'Location',
                        $this->router->pathFor(
                            'member',
                            ['id' => $this->login->id]
                        )
                    );
            }
        }

        $navigate = array();

        if (isset($this->session->filter_members)) {
            $filters =  $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        if ($this->login->isAdmin()
            || $this->login->isStaff()
            || $this->login->isGroupManager()
        ) {
            $m = new Members($filters);
            $ids = $m->getList(false, array(Adherent::PK, 'nom_adh', 'prenom_adh'));
            $ids = $ids->toArray();
            foreach ($ids as $k => $m) {
                if ($m['id_adh'] == $member->id) {
                    $navigate = array(
                        'cur'  => $m['id_adh'],
                        'count' => count($ids),
                        'pos' => $k+1
                    );
                    if ($k > 0) {
                        $navigate['prev'] = $ids[$k-1]['id_adh'];
                    }
                    if ($k < count($ids)-1) {
                        $navigate['next'] = $ids[$k+1]['id_adh'];
                    }
                    break;
                }
            }
        }

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

        // flagging fields visibility
        $fc = $this->fields_config;
        $visibles = $fc->getVisibilities();

        $display_elements = $fc->getDisplayElements($this->login);

        // display page
        $this->view->render(
            $response,
            'voir_adherent.tpl',
            array(
                'page_title'        => _T("Member Profile"),
                'require_dialog'    => true,
                'member'            => $member,
                'data'              => $adherent,
                'navigate'          => $navigate,
                'pref_lang_img'     => $this->i18n->getFlagFromId($member->language),
                'pref_lang'         => ucfirst($this->i18n->getNameFromId($member->language)),
                'pref_card_self'    => $this->preferences->pref_card_self,
                'dynamic_fields'    => $dynamic_fields,
                'groups'            => Groups::getSimpleList(),
                'time'              => time(),
                'visibles'          => $visibles,
                'display_elements'  => $display_elements
            )
        );
        return $response;
    }
)->setName('member')->add($authenticate);

$app->get(
    __('/member', 'routes') . '/{action:' . __('edit', 'routes') . '|' . __('add', 'routes') . '}[/{id:\d+}]',
    function ($request, $response, $args) {
        $action = $args['action'];
        $id = null;
        if (isset($args['id'])) {
            $id = $args['id'];
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
            'children'  => true
        );
        $route_params = [];

        if ($this->session->member !== null) {
            $member = $this->session->member;
            $this->session->member = null;
        } else {
            $member = new Adherent($this->zdb, null, $deps);
        }

        //TODO: dynamic fields should be handled by Adherent object
        $dyn_fields = new DynamicFields();

        if ($this->login->isAdmin() || $this->login->isStaff() || $this->login->isGroupManager()) {
            if ($id !== null) {
                $adherent['id_adh'] = $id;
                if ($member->id != $id) {
                    $member->load($id);
                }
                if (!$this->login->isAdmin() && !$this->login->isStaff() && $this->login->isGroupManager()) {
                    //check if current logged in user can manage loaded member
                    $groups = $member->groups;
                    $can_manage = false;
                    foreach ($groups as $group) {
                        if ($this->login->isGroupManager($group->getId())) {
                            $can_manage = true;
                            break;
                        }
                    }
                    if ($can_manage !== true) {
                        Analog::log(
                            'Logged in member ' . $this->login->login .
                            ' has tried to load member #' . $member->id .
                            ' but do not manage any groups he belongs to.',
                            Analog::WARNING
                        );
                        $member->load($this->login->id);
                    }
                }
            }

            // disable some fields
            if ($this->login->isAdmin()) {
                $disabled = $member->adm_edit_disabled_fields;
            } elseif ($this->login->isStaff()) {
                $disabled = $member->adm_edit_disabled_fields
                    + $member->staff_edit_disabled_fields;
            } else {
                $disabled = $member->adm_edit_disabled_fields
                    + $member->staff_edit_disabled_fields
                    + $member->disabled_fields;
            }

            if ($this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED) {
                $disabled['send_mail'] = 'disabled="disabled"';
            }
        } else {
            if ($member->id != $id) {
                $member->load($this->login->id);
            }
            $adherent['id_adh'] = $this->login->id;
            // disable some fields
            $disabled  = $member->disabled_fields + $member->edit_disabled_fields;
        }

        // flagging required fields
        $fc = $this->fields_config;

        // password required if we create a new member
        if ($member->id != '') {
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
            $route_params['no_parent_required'] = $no_parent_required;
        }

        // flagging required fields invisible to members
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $fc->setNotRequired('activite_adh');
            $fc->setNotRequired('id_statut');
        }

        $required = $fc->getRequired();
        // flagging fields visibility
        $visibles = $fc->getVisibilities();

        $real_requireds = array_diff(array_keys($required), array_keys($disabled));

        if ($member->id !== false &&  $member->id !== '') {
            $adherent['dyn'] = $dyn_fields->getFields('adh', $member->id, false);
        }

        // - declare dynamic fields for display
        $disabled['dyn'] = array();
        if (!isset($adherent['dyn'])) {
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
        if ($member->id != '') {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        $navigate = array();

        if (isset($this->session->filter_members)) {
            $filters =  $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        if (($this->login->isAdmin() || $this->login->isStaff()) && count($filters) > 0) {
            $m = new Members();
            $ids = $m->getList(false, array(Adherent::PK, 'nom_adh', 'prenom_adh'));
            $ids = $ids->toArray();
            foreach ($ids as $k => $m) {
                if ($m['id_adh'] == $member->id) {
                    $navigate = array(
                        'cur'  => $m['id_adh'],
                        'count' => count($ids),
                        'pos' => $k+1
                    );
                    if ($k > 0) {
                        $navigate['prev'] = $ids[$k-1]['id_adh'];
                    }
                    if ($k < count($ids)-1) {
                        $navigate['next'] = $ids[$k+1]['id_adh'];
                    }
                    break;
                }
            }
        }

        //Status
        $statuts = new Status($this->zdb);

        //Groups
        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getSimpleList(true);

        $form_elements = $fc->getFormElements($this->login);

        // display page
        $this->view->render(
            $response,
            'member.tpl',
            array_merge(
                $route_params,
                array(
                    'parent_tpl'        => 'page.tpl',
                    'navigate'          => $navigate,
                    'require_dialog'    => true,
                    'autocomplete'      => true,
                    'page_title'        => $title,
                    'required'          => $required,
                    'visibles'          => $visibles,
                    'disabled'          => $disabled,
                    'member'            => $member,
                    'data'              => $adherent,
                    'self_adh'          => false,
                    'dynamic_fields'    => $dynamic_fields,
                    'languages'         => $this->i18n->getList(),
                    'require_calendar'  => true,
                    // pseudo random int
                    'time'              => time(),
                    'titles_list'       => Titles::getList($this->zdb),
                    'statuts'           => $statuts->getList(),
                    'groups'            => $groups_list,
                    'fieldsets'         => $form_elements['fieldsets'],
                    'hidden_elements'   => $form_elements['hiddens']
                )
            )
        );
        return $response;
    }
)->setName(
    'editmember'
)->add($authenticate);

$app->post(
    __('/member/store', 'routes') . '[/{self:' . __('subscribe', 'routes') . '}]',
    function ($request, $response, $args) {
        if (!$this->preferences->pref_bool_selfsubscribe && !$this->login->isLogged()) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        }

        $deps = array(
            'picture'   => true,
            'groups'    => true,
            'dues'      => true,
            'parent'    => true,
            'children'  => true
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

        //TODO: dynamic fields should be handled by Adherent object
        $dyn_fields = new DynamicFields();
        $success_detected = [];
        $warning_detected = [];
        $error_detected = [];

        // new or edit
        $adherent['id_adh'] = get_numeric_form_value('id_adh', '');

        if ($this->login->isAdmin() || $this->login->isStaff() || $this->login->isGroupManager()) {
            if ($adherent['id_adh']) {
                $member->load($adherent['id_adh']);
                if (!$this->login->isAdmin() && !$this->login->isStaff() && $this->login->isGroupManager()) {
                    //check if current logged in user can manage loaded member
                    $groups = $member->groups;
                    $can_manage = false;
                    foreach ($groups as $group) {
                        if ($this->login->isGroupManager($group->getId())) {
                            $can_manage = true;
                            break;
                        }
                    }
                    if ($can_manage !== true) {
                        Analog::log(
                            'Logged in member ' . $this->login->login .
                            ' has tried to load member #' . $member->id .
                            ' but do not manage any groups he belongs to.',
                            Analog::WARNING
                        );
                        $member->load($this->login->id);
                    }
                }
            }

            // disable some fields
            if ($this->login->isAdmin()) {
                $disabled = $member->adm_edit_disabled_fields;
            } elseif ($this->login->isStaff()) {
                $disabled = $member->adm_edit_disabled_fields
                    + $member->staff_edit_disabled_fields;
            } else {
                $disabled = $member->adm_edit_disabled_fields
                    + $member->staff_edit_disabled_fields
                    + $member->disabled_fields;
            }

            if ($this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED) {
                $disabled['send_mail'] = 'disabled="disabled"';
            }
        } else {
            $member->load($this->login->id);
            $adherent['id_adh'] = $this->login->id;
            // disable some fields
            $disabled  = $member->disabled_fields + $member->edit_disabled_fields;
        }

        // flagging required fields
        $fc = $this->fields_config;

        // password required if we create a new member
        if ($member->id != '') {
            $fc->setNotRequired('mdp_adh');
        }

        // flagging required fields invisible to members
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $fc->setNotRequired('activite_adh');
            $fc->setNotRequired('id_statut');
        }

        $required = $fc->getRequired();
        // flagging fields visibility
        $visibles = $fc->getVisibilities();

        $real_requireds = array_diff(array_keys($required), array_keys($disabled));

        // Validation
        if (isset($_POST[array_shift($real_requireds)])) {
            $adherent['dyn'] = $dyn_fields->extractPosted(
                $_POST,
                $_FILES,
                $disabled,
                $member->id
            );
            $dyn_fields_errors = $dyn_fields->getErrors();
            if (count($dyn_fields_errors) > 0) {
                $error_detected = array_merge($error_detected, $dyn_fields_errors);
            }
            // regular fields
            $valid = $member->check($_POST, $required, $disabled);
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
                            if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED && $member->getEmail() != '') {
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
                                $this->texts_fields,
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
                            $mtxt = $texts->getTexts('newadh', $this->preferences->pref_lang);

                            $mail = new GaletteMail();
                            $mail->setSubject($texts->getSubject());
                            $mail->setRecipients(
                                array(
                                    $this->preferences->pref_email_newadh => _T("Galette admin")
                                )
                            );
                            $mail->setMessage($texts->getBody());
                            $sent = $mail->send();

                            if ($sent == GaletteMail::MAIL_SENT) {
                                $this->history->add(
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
                                $this->history->add($str);
                                $warning_detected[] = $str;
                            }
                            unset($texts);
                        }
                    } else {
                        $success_detected[] = _T("Member account has been modified.");
                    }

                    // send mail to member
                    if (isset($args['self']) || isset($_POST['mail_confirm']) && $_POST['mail_confirm'] == '1') {
                        if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED) {
                            if ($member->getEmail() == '' && !isset($args['self'])) {
                                $error_detected[] = _T("- You can't send a confirmation by email if the member hasn't got an address!");
                            } else {
                                //send mail to member
                                // Get email text in database
                                $texts = new Texts(
                                    $this->texts_fields,
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
                                            $member->setName
                                        ),
                                        'mail_adh'      => custom_html_entity_decode(
                                            $member->getEmail()
                                        ),
                                        'login_adh'     => custom_html_entity_decode(
                                            $member->login
                                        ),
                                        'password_adh'  => custom_html_entity_decode(
                                            $_POST['mdp_adh']
                                        )
                                    )
                                );
                                $mlang = $this->preferences->pref_lang;
                                if (isset($_POST['pref_lang'])) {
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
                                        _T("New account mail sent to '%s'.") :
                                        _T("Account modification mail sent to '%s'.")
                                    );
                                    $this->history->add($msg);
                                    $success_detected[] = $msg;
                                } else {
                                    $str = str_replace(
                                        '%s',
                                        $member->sname . ' (' . $member->getEmail() . ')',
                                        _T("A problem happened while sending account mail to '%s'")
                                    );
                                    $this->history->add($str);
                                    $error_detected[] = $str;
                                }
                            }
                        } elseif ($this->preferences->pref_mail_method == GaletteMail::METHOD_DISABLED) {
                            //if mail has been disabled in the preferences, we should not be here ;
                            //we do not throw an error, just a simple warning that will be show later
                            $msg = _T("You asked Galette to send a confirmation mail to the member, but mail has been disabled in the preferences.");
                            $warning_detected[] = $msg;
                        }
                    }

                    //store requested groups
                    $add_groups = null;
                    $groups_adh = null;
                    $managed_groups_adh = null;

                    //add/remove user from groups
                    if (isset($_POST['groups_adh'])) {
                        $groups_adh = $_POST['groups_adh'];
                    }
                    $add_groups = Groups::addMemberToGroups(
                        $member,
                        $groups_adh
                    );

                    if ($add_groups === false) {
                        $error_detected[] = _T("An error occured adding member to its groups.");
                    }

                    //add/remove manager from groups
                    if (isset($_POST['groups_managed_adh'])) {
                        $managed_groups_adh = $_POST['groups_managed_adh'];
                    }
                    $add_groups = Groups::addMemberToGroups(
                        $member,
                        $managed_groups_adh,
                        true
                    );
                    $member->loadGroups();

                    if ($add_groups === false) {
                        $error_detected[] = _T("An error occured adding member to its groups as manager.");
                    }
                } else {
                    //something went wrong :'(
                    $error_detected[] = _T("An error occured while storing the member.");
                }
            }

            if (count($error_detected) == 0) {
                // picture upload
                if (isset($_FILES['photo'])) {
                    if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                        if ($_FILES['photo']['tmp_name'] !='') {
                            if (is_uploaded_file($_FILES['photo']['tmp_name'])) {
                                $res = $member->picture->store($_FILES['photo']);
                                if ($res < 0) {
                                    $error_detected[]
                                        = $member->picture->getErrorMessage($res);
                                }
                            }
                        }
                    } elseif ($_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                        Analog::log(
                            $member->picture->getPhpErrorMessage($_FILES['photo']['error']),
                            Analog::WARNING
                        );
                        $error_detected[] = $member->picture->getPhpErrorMessage(
                            $_FILES['photo']['error']
                        );
                    }
                }

                if (isset($_POST['del_photo'])) {
                    if (!$member->picture->delete($member->id)) {
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
                if (!isset($_POST['id_adh']) && !$member->isDueFree() && !isset($args['self'])) {
                    return $response
                        ->withStatus(301)
                        ->withHeader(
                            'Location',
                            $this->router->pathFor(
                                'contribution',
                                [
                                    'type'      => __('fee', 'routes'),
                                    'action'    => __('add', 'routes'),
                                ]
                            ) . '?id_adh=' . $member->id
                        );
                } else {
                    if (isset($args['self'])) {
                        $redirect_url = $this->router->pathFor('login');
                    } else {
                        $redirect_url = $this->router->pathFor('member', ['id' => $member->id]);
                    }
                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $redirect_url);
                }
            } else {
                //store entity in session
                $this->session->member = $member;

                if (isset($args['self'])) {
                    $redirect_url = $this->router->pathFor('subscribe');
                } else {
                    if ($member->id) {
                        $rparams = [
                            'id'    => $member->id,
                            'action'    => __('edit', 'routes')
                        ];
                    } else {
                        $rparams = ['action' => __('add', 'routes')];
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
    __('/member/remove', 'routes') . '/{id:\d+}',
    function ($request, $response, $args) {
        $adh = new Adherent($this->zdb, (int)$args['id']);

        $data = [
            'id'            => $args['id'],
            'redirect_uri'  => $this->router->pathFor('members')
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'type'          => _T("Member"),
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => sprintf(
                    _T('Remove member %1$s'),
                    $adh->sfullname
                ),
                'form_url'      => $this->router->pathFor('doRemoveMember', ['id' => $adh->id]),
                'cancel_uri'    => $this->router->pathFor('members'),
                'data'          => $data
            )
        );
        return $response;
    }
)->setName('removeMember')->add($authenticate);

$app->get(
    __('/members/remove', 'routes'),
    function ($request, $response) {
        $post = $request->getParsedBody();

        $filters =  $this->session->filter_members;

        $data = [
            'id'           => $filters->selected,
            'redirect_uri'  => $this->router->pathFor('members')
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'type'          => _T("Member"),
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => _T('Remove members'),
                'message'       => str_replace(
                    '%count',
                    count($data['id']),
                    _T('You are about to remove %count members.')
                ),
                'form_url'      => $this->router->pathFor('doRemoveMember'),
                'cancel_uri'    => $this->router->pathFor('members'),
                'data'          => $data
            )
        );
        return $response;
    }
)->setName('removeMembers')->add($authenticate);

$app->post(
    __('/member/remove', 'routes') . '[/{id:\d+}]',
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
            if (isset($this->session->filter_members)) {
                $filters =  $this->session->filter_members;
            } else {
                $filters = new MembersList();
            }
            $members = new Members($filters);

            if (!is_array($post['id'])) {
                //delete member
                $adh = new Adherent($this->zdb, (int)$post['id']);
                $ids = (array)$post['id'];
            } else {
                $ids = $post['id'];
            }

            $del = $members->removeMembers($ids);

            if ($del !== true) {
                if (count($ids) === 1) {
                    $error_detected = str_replace(
                        '%name',
                        $adh->sname,
                        _T("An error occured trying to remove member %name :/")
                    );
                } else {
                    $error_detected = _T("An error occured trying to remove members :/");
                }

                $this->flash->addMessage(
                    'error_detected',
                    $error_detected
                );
            } else {
                if (!is_array($post['id'])) {
                    $success_detected = str_replace(
                        '%name',
                        $adh->sname,
                        _T("Member %name has been successfully deleted.")
                    );
                } else {
                    $success_detected = str_replace(
                        '%count',
                        count($ids),
                        _T("%count members have been successfully deleted.")
                    );
                }

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
)->setName('doRemoveMember')->add($authenticate);

//advanced search page
$app->get(
    __('/advanced-search', 'routes'),
    function ($request, $response) {
        if (isset($this->session->filter_members)) {
            $filters = $this->session->filter_members;
            if (!$filters instanceof AdvancedMembersList) {
                $filters = new AdvancedMembersList($filters);
            }
        } else {
            $filters = new AdvancedMembersList();
        }

        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getList();

        //we want only visibles fields
        $fields = $this->members_fields;
        $fc = $this->fields_config;
        $visibles = $fc->getVisibilities();

        foreach ($fields as $k => $f) {
            if ($visibles[$k] == 0) {
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
        $statuts = new Status($this->zdb);

        //Contributions types
        $ct = new Galette\Entity\ContributionsTypes($this->zdb);

        //Payments types
        $pt = array(
            Contribution::PAYMENT_OTHER         => _T("Other"),
            Contribution::PAYMENT_CASH          => _T("Cash"),
            Contribution::PAYMENT_CREDITCARD    => _T("Credit card"),
            Contribution::PAYMENT_CHECK         => _T("Check"),
            Contribution::PAYMENT_TRANSFER      => _T("Transfer"),
            Contribution::PAYMENT_PAYPAL        => _T("Paypal")
        );

        $filters->setViewCommonsFilters($this->preferences, $this->view->getSmarty());

        // display page
        $this->view->render(
            $response,
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
        return $response;
    }
)->setName('advanced-search')->add($authenticate);

//Batch actions on members list
$app->post(
    __('/members/batch', 'routes'),
    function ($request, $response) {
        $post = $request->getParsedBody();

        if (isset($post['member_sel'])) {
            if (isset($this->session->filter_members)) {
                $filters = $this->session->filter_members;
            } else {
                $filters = new MembersList();
            }

            $filters->selected = $post['member_sel'];
            $this->session->filter_members = $filters;

            if (isset($post['cards'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('pdf-members-cards'));
            }

            if (isset($post['labels'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('pdf-members-labels'));
            }

            if (isset($post['mailing'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('mailing') . '?new=new');
            }

            if (isset($post['attendance_sheet'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('attendance_sheet_details'));
            }

            if (isset($post['csv'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('csv-memberslist'));
            }

            if (isset($post['delete'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('removeMembers'));
            }

        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("No member was selected, please check at least one name.")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }
    }
)->setName('batch-memberslist')->add($authenticate);

//PDF members cards
$app->get(
    __('/members/cards', 'routes') . '[/{' . Adherent::PK . ':\d+}]',
    function ($request, $response, $args) {
        if ($this->session->filter_members) {
            $filters =  $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        if (isset($args[Adherent::PK])
            && $args[Adherent::PK] > 0
        ) {
            // If we are called from a member's card, get unique id value
            $unique = $args[Adherent::PK];
        } else {
            if (count($filters->selected) == 0) {
                Analog::log(
                    'No member selected to generate members cards',
                    Analog::INFO
                );
                $this->flash->addMessage(
                    'error_detected',
                    _T("No member was selected, please check at least one name.")
                );

                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor('members'));
            }
        }

        // Fill array $selected with selected ids
        $selected = array();
        if (isset($unique) && $unique) {
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

        if (!is_array($members) || count($members) < 1) {
            Analog::log(
                'An error has occured, unable to get members list.',
                Analog::ERROR
            );

            $this->flash->addMessage(
                'error_detected',
                _T("Unable to get members list.")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }

        $pdf = new PdfMembersCards($this->preferences);
        $pdf->drawCards($members);
        $pdf->Output(_T("Cards") . '.pdf', 'D');
    }
)->setName('pdf-members-cards')->add($authenticate);

//PDF members labels
$app->get(
    __('/members/labels', 'routes'),
    function ($request, $response) {
        $get = $request->getQueryParams();

        if ($this->session->filter_reminders_labels) {
            $filters =  $this->session->filter_reminders_labels;
            unset($this->session->filter_reminders_labels);
        } elseif ($this->session->filter_members) {
            $filters =  $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        $members = null;
        if (isset($get['from'])
            && $get['from'] === 'mailing'
        ) {
            //if we're from mailing, we have to retrieve
            //its unreachables members for labels
            $mailing = $this->session->mailing;
            $members = $mailing->unreachables;
        } else {
            if (count($filters->selected) == 0) {
                Analog::log('No member selected to generate labels', Analog::INFO);
                $this->flash->addMessage(
                    'error_detected',
                    _T("No member was selected, please check at least one name.")
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

        if (!is_array($members) || count($members) < 1) {
            Analog::log(
                'An error has occured, unable to get members list.',
                Analog::ERROR
            );

            $this->flash->addMessage(
                'error_detected',
                _T("Unable to get members list.")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }

        $pdf = new PdfMembersLabels($this->preferences);
        $pdf->drawLabels($members);
        $pdf->Output(_T("labels_print_filename") . '.pdf', 'D');
    }
)->setName('pdf-members-labels')->add($authenticate);

//PDF adhesion form
$app->get(
    __('/members/adhesion-form', 'routes') . '/{' . Adherent::PK . ':\d+}',
    function ($request, $response, $args) {
        $id_adh = (int)$args[Adherent::PK];

        if (!$this->login->isAdmin() && !$this->login->isStaff()) {
            $id_adh = (int)$this->login->id;
        }

        $adh = new Adherent($this->zdb, $id_adh);
        $form = $this->preferences->pref_adhesion_form;
        $pdf = new $form($adh, $this->zdb, $this->preferences);
        $pdf->download();
    }
)->setName('adhesionForm')->add($authenticate);

//Empty PDF adhesion form
$app->get(
    __('/members/empty-adhesion-form', 'routes'),
    function ($request, $response) {
        $adh = new Adherent($this->zdb);
        $form = $this->preferences->pref_adhesion_form;
        $pdf = new $form($adh, $this->zdb, $this->preferences);
        $pdf->download();
    }
)->setName('emptyAdhesionForm');

//mailing
$app->get(
    __('/mailing', 'routes'),
    function ($request, $response) {
        $get = $request->getQueryParams();

        //We're done :-)
        if (isset($get['mailing_new'])
            || isset($get['reminder'])
        ) {
            if ($this->session->mailing !== null) {
                // check for temporary attachments to remove
                $m = $this->session->mailing;
                $m->removeAttachments(true);
            }
            $this->session->mailing = null;
        }

        $params = array();

        if ($this->preferences->pref_mail_method == Mailing::METHOD_DISABLED
            && !GALETTE_MODE === 'DEMO'
        ) {
            $this->history->add(
                _T("Trying to load mailing while mail is disabled in preferences.")
            );
            $this->flash->addMessage(
                'error_detected',
                _T("Trying to load mailing while mail is disabled in preferences.")
            );
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        } else {
            if (isset($this->session->filter_members)) {
                $filters =  $this->session->filter_members;
            } else {
                $filters = new MembersList();
            }

            if ($this->session->mailing !== null
                && !isset($get['from'])
                && !isset($get['reset'])
            ) {
                $mailing = $this->session->mailing;
            } elseif (isset($get['from']) && is_numeric($get['from'])) {
                $mailing = new Mailing(null, $get['from']);
                MailingHistory::loadFrom($this->zdb, (int)$get['from'], $mailing);
            } elseif (isset($get['reminder'])) {
                //FIXME: use a constant!
                $filters->reinit();
                $filters->membership_filter = Members::MEMBERSHIP_LATE;
                $filters->account_status_filter = Members::ACTIVE_ACCOUNT;
                $m = new Members($filters);
                $members = $m->getList(true);
                $mailing = new Mailing(($members !== false) ? $members : null);
            } else {
                if (count($filters->selected) == 0
                    && !isset($get['mailing_new'])
                    && !isset($get['reminder'])
                ) {
                    Analog::log(
                        '[Mailings] No member selected for mailing',
                        Analog::WARNING
                    );

                    if (isset($profiler)) {
                        $profiler->stop();
                    }

                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $this->router->pathFor('members'));
                }
                $m = new Members();
                $members = $m->getArrayList($filters->selected);
                $mailing = new Mailing(($members !== false) ? $members : null);
            }

            if (isset($get['remove_attachment'])) {
                $mailing->removeAttachment($get['remove_attachment']);
            }

            if ($mailing->current_step !== Mailing::STEP_SENT) {
                $this->session->mailing = $mailing;
            }

            /** TODO: replace that... */
            $this->session->labels = $mailing->unreachables;

            $params = array_merge(
                $params,
                array(
                    'mailing'           => $mailing,
                    'attachments'       => $mailing->attachments,
                    'html_editor'       => true,
                    'html_editor_active'=> $this->preferences->pref_editor_enabled
                )
            );
        }

        // display page
        $this->view->render(
            $response,
            'mailing_adherents.tpl',
            array_merge(
                array(
                    'page_title'            => _T("Mailing"),
                    'require_dialog'        => true
                ),
                $params
            )
        );
        return $response;
    }
)->setName('mailing')->add($authenticate);

$app->post(
    __('/mailing', 'routes'),
    function ($request, $response) {
        $post = $request->getParsedBody();
        $error_detected = [];
        $success_detected = [];

        $goto = $this->router->pathFor('mailings');

        //We're done :-)
        if (isset($post['mailing_done'])
            || isset($post['mailing_cancel'])
        ) {
            if ($this->session->mailing !== null) {
                // check for temporary attachments to remove
                $m = $this->session->mailing;
                $m->removeAttachments(true);
            }
            $this->session->mailing = null;
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }

        $params = array();

        if ($this->preferences->pref_mail_method == Mailing::METHOD_DISABLED
            && !GALETTE_MODE === 'DEMO'
        ) {
            $this->history->add(
                _T("Trying to load mailing while mail is disabled in preferences.")
            );
            $error_detected[] = _T("Trying to load mailing while mail is disabled in preferences.");
            $goto = $this->router->pathFor('slash');
        } else {
            if (isset($this->session->filter_members)) {
                $filters =  $this->session->filter_members;
            } else {
                $filters = new MembersList();
            }

            if ($this->session->mailing !== null
                && !isset($post['mailing_cancel'])
            ) {
                $mailing = $this->session->mailing;
            } else {
                if (count($filters->selected) == 0) {
                    Analog::log(
                        '[Mailings] No member selected for mailing',
                        Analog::WARNING
                    );

                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $this->router->pathFor('members'));
                }
                $m = new Members();
                $members = $m->getArrayList($filters->selected);
                $mailing = new Mailing(($members !== false) ? $members : null);
            }

            if (isset($post['mailing_go'])
                || isset($post['mailing_reset'])
                || isset($post['mailing_confirm'])
                || isset($post['mailing_save'])
            ) {
                if (trim($post['mailing_objet']) == '') {
                    $error_detected[] = _T("Please type an object for the message.");
                } else {
                    $mailing->subject = $_POST['mailing_objet'];
                }

                if (trim($post['mailing_corps']) == '') {
                    $error_detected[] = _T("Please enter a message.");
                } else {
                    $mailing->message = $post['mailing_corps'];
                }

                $mailing->html = (isset($post['mailing_html'])) ? true : false;

                //handle attachments
                if (isset($_FILES['files'])) {
                    for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
                        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                            if ($_FILES['files']['tmp_name'][$i] != '') {
                                if (is_uploaded_file($_FILES['files']['tmp_name'][$i])) {
                                    $da_file = array();
                                    foreach (array_keys($_FILES['files']) as $key) {
                                        $da_file[$key] = $_FILES['files'][$key][$i];
                                    }
                                    $res = $mailing->store($da_file);
                                    if ($res < 0) {
                                        //what to do if one of attachments fail? should other be removed?
                                        $error_detected[] = $mailing->getAttachmentErrorMessage($res);
                                    }
                                }
                            }
                        } elseif ($_FILES['files']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
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

                if (count($error_detected) == 0
                    && !isset($_POST['mailing_reset'])
                    && !isset($_POST['mailing_save'])
                ) {
                    $mailing->current_step = Mailing::STEP_PREVIEW;
                } else {
                    $mailing->current_step = Mailing::STEP_START;
                }
            }

            if (isset($_POST['mailing_confirm']) && count($error_detected) == 0) {
                $mailing->current_step = Mailing::STEP_SEND;
                //ok... let's go for fun
                $sent = $mailing->send();
                if ($sent == Mailing::MAIL_ERROR) {
                    $mailing->current_step = Mailing::STEP_START;
                    Analog::log(
                        '[Mailings] Message was not sent. Errors: ' .
                        print_r($mailing->errors, true),
                        Analog::ERROR
                    );
                    foreach ($mailing->errors as $e) {
                        $error_detected[] = $e;
                    }
                } else {
                    $mlh = new MailingHistory($this->zdb, $this->login, null, $mailing);
                    $mlh->storeMailing(true);
                    Analog::log(
                        '[Mailings] Message has been sent.',
                        Analog::INFO
                    );
                    $mailing->current_step = Mailing::STEP_SENT;
                    //cleanup
                    $filters->selected = null;
                    $this->session->filter_members = $filters;
                    $this->session->mailing = null;
                    $success_detected[] = _T("Mailing has been successfully sent!");
                    $goto = $this->router->pathFor('members');
                }
            }

            if ($mailing->current_step !== Mailing::STEP_SENT) {
                $this->session->mailing = $mailing;
            }

            /** TODO: replace that... */
            $this->session->labels = $mailing->unreachables;

            if (!isset($post['html_editor_active'])
                || trim($post['html_editor_active']) == ''
            ) {
                $post['html_editor_active'] = $this->preferences->pref_editor_enabled;
            }

            if (isset($post['mailing_save'])) {
                //user requested to save the mailing
                $histo = new MailingHistory($this->zdb, $this->login, null, $mailing);
                if ($histo->storeMailing() !== false) {
                    $success_detected[] = _T("Mailing has been successfully saved.");
                    $this->session->mailing = null;
                }
            }
        }

        //flash messages if any
        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage('error_detected', $error);
            }
        }
        if (count($success_detected) > 0) {
            foreach ($success_detected as $success) {
                $this->flash->addMessage('success_detected', $success);
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $goto);
    }
)->setName('doMailing')->add($authenticate);

$app->map(
    ['GET', 'POST'],
    __('/mailing', 'routes') . __('/preview', 'routes') . '[/{id:\d+}]',
    function ($request, $response, $args) {
        $post = $request->getParsedBody();
        // check for ajax mode
        $ajax = false;
        if ($request->isXhr()
            || isset($post['ajax'])
            && $post['ajax'] == 'true'
        ) {
            $ajax = true;
        }

        $mailing = null;
        if (isset($args['id'])) {
            $mailing = new Mailing(null);
            MailingHistory::loadFrom($this->zdb, (int)$args['id'], $mailing, false);
            $attachments = $mailing->attachments;
        } else {
            $mailing = $this->session->mailing;

            $mailing->subject = $post['subject'];
            $mailing->message = $post['body'];
            $mailing->html = ($post['html'] === 'true');
            $attachments = (isset($post['attachments']) ? $post['attachments'] : []);
        }

        // display page
        $this->view->render(
            $response,
            'mailing_preview.tpl',
            [
                'page_title'    => _T("Mailing preview"),
                'mailing_id'    => $args['id'],
                'mode'          => ($ajax ? 'ajax' : ''),
                'mailing'       => $mailing,
                'recipients'    => $mailing->recipients,
                'sender'        => $this->preferences->pref_email_nom . ' &lt;' .
                    $this->preferences->pref_email . '&gt;',
                'attachments'   => $attachments

            ]
        );
        return $response;
    }
)->setName('mailingPreview')->add($authenticate);

$app->get(
    __('/mailing', 'routes') . __('/preview', 'routes') . '/{id:\d+}' . __('/attachment', 'routes') . '/{pos:\d+}',
    function ($request, $response, $args) {
        $mailing = new Mailing(null);
        MailingHistory::loadFrom($this->zdb, (int)$args['id'], $mailing, false);
        $attachments = $mailing->attachments;
        $attachment = $attachments[$args['pos']];
        $filepath = $attachment->getDestDir() .  $attachment->getFileName();


        $ext = pathinfo($attachment->getFileName())['extension'];
        $response = $response->withHeader('Content-type', $attachment->getMimeType($filepath));

        $body = $response->getBody();
        $body->write(file_get_contents($filepath));
        return $response;
    }
)->setName('previewAttachment')->add($authenticate);

$app->post(
    __('/ajax', 'routes') . __('/mailing', 'routes') . __('/set-recipients', 'routes'),
    function ($request, $response, $args) {
        $post = $request->getParsedBody();
        $mailing = $this->session->mailing;

        $m = new Members();

        $members = $m->getArrayList(
            $post['recipients'],
            null,
            false,
            true,
            null,
            false,
            false,
            true
        );
        $mailing->setRecipients($members);

        $this->session->mailing = $mailing;

        // display page
        $this->view->render(
            $response,
            'mailing_recipients.tpl',
            [
                'mailing'       => $mailing

            ]
        );
        return $response;
    }
)->setName('mailingRecipients')->add($authenticate);

//reminders
$app->get(
    __('/reminders', 'routes'),
    function ($request, $response) {
        $texts = new Texts($this->texts_fields, $this->preferences, $this->router);

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
                'require_dialog'            => true,
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
    __('/reminders', 'routes'),
    function ($request, $response) {
        $error_detected = [];
        $warning_detected = [];
        $success_detected = [];

        $post = $request->getParsedBody();
        $texts = new Texts($this->texts_fields, $this->preferences, $this->router);
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
                    //send reminders by mail
                    $sent = $reminder->send($texts, $this->history, $this->zdb);

                    if ($sent === true) {
                        $success_detected[] = $reminder->getMessage();
                    } else {
                        $error_detected[] = $reminder->getMessage();
                    }
                } else {
                    //generate labels for members without mail address
                    $labels_members[] = $reminder->member_id;
                }
            }

            if ($labels === true) {
                if (count($labels_members) > 0) {
                    $labels_filters = new MembersList();
                    $labels_filters->selected = $labels_members;
                    $this->session->filters_reminders_labels = $labels_filters;
                    header('location: etiquettes_adherents.php');
                    die();
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
    __('/members/reminder-filter', 'routes') .
        '/{membership:' . __('nearly', 'routes') . '|' . __('late', 'routes')  . '}' .
        '/{mail:' . __('withmail', 'routes'). '|' . __('withoutmail', 'routes') . '}',
    function ($request, $response, $args) {
        //always reset filters
        $filters = new MembersList();
        $filters->account_status_filter = Members::ACTIVE_ACCOUNT;

        $membership = ($args['membership'] === __('nearly', 'routes') ?
            Members::MEMBERSHIP_NEARLY :
            Members::MEMBERSHIP_LATE);
        $filters->membership_filter = $membership;

        $mail = ($args['mail'] === __('withmail', 'routes') ?
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
    __('/attendance-sheet/details', 'routes'),
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
    __('/attendance-sheet', 'routes'),
    function ($request, $response) {
        $post = $request->getParsedBody();

        if ($this->session->filter_members !== null) {
            $filters = $this->session->filter_members;
        } else {
            $filters = new MembersList();
        }

        //retrieve selected members
        $selection = (isset($post['selection']) ) ? $post['selection'] : array();

        $filters->selected = $selection;
        $this->session->filter_members = $filters;

        if (count($filters->selected) == 0) {
            Analog::log('No member selected to generate attendance sheet', Analog::INFO);
            $this->flash->addMessage(
                'error_detected',
                _T("No member selected to generate attendance sheet")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }

        $m = new Members();
        $members = $m->getArrayList(
            $filters->selected,
            array('nom_adh', 'prenom_adh'),
            true
        );

        if (!is_array($members) || count($members) < 1) {
            Analog::log('No member selected to generate attendance sheet', Analog::INFO);
            $this->flash->addMessage(
                'error_detected',
                _T("No member selected to generate attendance sheet")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('members'));
        }

        //with or without images?
        $_wimages = false;
        if (isset($post['sheet_photos']) && $post['sheet_photos'] === '1') {
            $_wimages = true;
        }

        $doc_title = _T("Attendance sheet");
        if (isset($post['sheet_type']) && trim($post['sheet_type']) != '') {
            $doc_title = $post['sheet_type'];
        }

        $pdf = new Galette\IO\PdfAttendanceSheet($this->preferences);
        $pdf->doc_title = $doc_title;
        // Set document information
        $pdf->SetTitle($doc_title);

        if (isset($post['sheet_title']) && trim($post['sheet_title']) != '') {
            $pdf->sheet_title = $post['sheet_title'];
        }
        if (isset($post['sheet_sub_title']) && trim($post['sheet_sub_title']) != '') {
            $pdf->sheet_sub_title = $_POST['sheet_sub_title'];
        }
        if (isset($post['sheet_date']) && trim($post['sheet_date']) != '') {
            $dformat = __("Y-m-d");
            $date = DateTime::createFromFormat(
                $dformat,
                $post['sheet_date']
            );
            $pdf->sheet_date = $date;
        }

        $pdf->drawSheet($members, $doc_title);
        $pdf->Output(_T("attendance_sheet") . '.pdf', 'D');
    }
)->setName('attendance_sheet')->add($authenticate);

$app->post(
    __('/ajax/members', 'routes') .
    '[/{option:' . __('page', 'routes') . '|' . __('order', 'routes') . '}/{value:\d+}]',
    function ($request, $response, $args) {
        $post = $request->getParsedBody();

        if (isset($this->session->ajax_members_filters)) {
            $filters = $this->session->ajax_members_filters;
        } else {
            $filters = new MembersList();
        }

        if (isset($args['option']) && $args['option'] == __('page', 'routes')) {
            $filters->current_page = (int)$args['value'];
        }

        //numbers of rows to display
        if (isset($post['nbshow']) && is_numeric($post['nbshow'])) {
            $filters->show = $post['nbshow'];
        }

        $members = new Members($filters);
        $members_list = $members->getMembersList(true);

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
    __('/ajax/group/members', 'routes'),
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
    __('/member', 'routes') . '/{id:\d+}' . __('/file', 'routes') . '/{fid:\d+}/{pos:\d+}/{name}',
    function ($request, $response, $args) {
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
            $type = File::getMimeType($filename);
            header('Content-Type: ' . $type);
            header('Content-Disposition: attachment; filename="' . $args['name'] . '";');
            header('Pragma: no-cache');
            echo readfile(GALETTE_FILES_PATH . $filename);
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
