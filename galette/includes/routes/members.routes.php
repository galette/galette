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
use Galette\Repository\Members;
use Galette\Filters\MembersList;
use Galette\Repository\Groups;
use Galette\Repository\Reminders;
use Galette\Entity\Adherent;
use Galette\Entity\Status;
use Galette\Repository\Titles;
use Galette\Entity\Texts;
use Galette\Entity\Group;
use Galette\IO\File;

//self subscription
$app->get(
    '/subscribe',
    Crud\MembersController::class . ':selfSubscribe'
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
    Crud\MembersController::class . ':showMe'
)->setName('me')->add($authenticate);

//members card
$app->get(
    '/member/{id:\d+}',
    Crud\MembersController::class . ':show'
)->setName('member')->add($authenticate)->add('Galette\Middleware\MembersNavigate');

$app->get(
    '/member/{action:edit|add}[/{id:\d+}]',
    Crud\MembersController::class . ':edit'
)->setName('editmember')->add($authenticate)->add('Galette\Middleware\MembersNavigate');

$app->post(
    '/member/store[/{self:subscribe}]',
    Crud\MembersController::class . ':doEdit'
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
    Crud\MembersController::class . ':getDynamicFile'
)->setName('getDynamicFile')->add($authenticate);

$app->get(
    '/members/mass-change',
    Crud\MembersController::class . ':massChange'
)->setName('masschangeMembers')->add($authenticate);

$app->post(
    '/members/mass-change/validate',
    Crud\MembersController::class . ':validateMassChange'
)->setName('masschangeMembersReview')->add($authenticate);

$app->post(
    '/members/mass-change',
    Crud\MembersController::class . ':doMassChange'
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
    Crud\SavedSearchesController::class . ':doAdd'
)->setName('saveSearch');

$app->get(
    '/saved-searches[/{option:page|order}/{value:\d+}]',
    Crud\SavedSearchesController::class . ':list'
)->setName('searches')->add($authenticate);

$app->get(
    '/search/remove/{id:\d+}',
    Crud\SavedSearchesController::class . ':confirmDelete'
)->setName('removeSearch')->add($authenticate);

$app->get(
    '/searches/remove',
    Crud\SavedSearchesController::class . ':confirmDelete'
)->setName('removeSearches')->add($authenticate);

$app->post(
    '/search/remove' . '[/{id:\d+}]',
    Crud\SavedSearchesController::class . ':delete'
)->setName('doRemoveSearch')->add($authenticate);

$app->get(
    '/save-search/{id}',
    Crud\SavedSearchesController::class . ':load'
)->setName('loadSearch');
