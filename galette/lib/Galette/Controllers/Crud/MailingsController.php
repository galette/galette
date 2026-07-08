<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers\Crud;

use Galette\Controllers\Attributes\Route;
use Galette\Controllers\CrudController;
use Galette\Core\Galette;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Core\GaletteMail;
use Galette\Core\Mailing;
use Galette\Core\MailingHistory;
use Galette\Core\MailingQueue;
use Galette\Entity\Adherent;
use Galette\Filters\MailingsList;
use Galette\Filters\MembersList;
use Galette\Repository\Members;
use Analog\Analog;

use function Safe\file_get_contents;

/**
 * Galette Mailing controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class MailingsController extends CrudController
{
    // CRUD - Create

    /**
     * Add page
     */
    #[Route(
        name: 'mailing',
        pattern: '/mailing',
        methods: ['GET']
    )]
    public function add(Request $request, Response $response): Response
    {
        $get = $request->getQueryParams();

        //We're done :-)
        if (
            isset($get['mailing_new'])
            || isset($get['reminder'])
        ) {
            if ($this->session->mailing !== null) {
                // check for temporary attachments to remove
                $m = $this->session->mailing;
                $m->removeAttachments(true);
            }
            $this->session->mailing = null;
            unset($this->session->mailing);
            $this->session->redirect_mailing = null;
            unset($this->session->redirect_mailing);
        }

        $params = [];

        if (
            $this->preferences->pref_mail_method == Mailing::METHOD_DISABLED
            && !Galette::isDemo()
        ) {
            $this->history->add(
                _T("Trying to load mailing while email is disabled in preferences.")
            );
            return $this->redirectWithErrors(
                response: $response,
                errors: [_T("Trying to load mailing while email is disabled in preferences.")],
                redirect_url: $this->routeparser->urlFor('slash')
            );
        } else {
            if (isset($this->session->{$this->getFilterName($this->getDefaultFilterName())})) {
                $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName())};
            } else {
                $filters = new MembersList();
            }

            if (
                $this->session->mailing !== null
                && !isset($get['from'])
                && !isset($get['reset'])
            ) {
                $mailing = $this->session->mailing;
            } elseif (isset($get['from']) && is_numeric($get['from'])) {
                $mailing = new Mailing($this->preferences, [], (int)$get['from']);
                MailingHistory::loadFrom($this->zdb, (int)$get['from'], $mailing);
            } elseif (isset($get['reminder'])) {
                //FIXME: use a constant!
                $filters->reinit();
                $filters->membership_filter = Members::MEMBERSHIP_LATE;
                $filters->filter_account = Members::ACTIVE_ACCOUNT;
                $m = new Members($filters);
                $members = $m->getList(true);
                $mailing = new Mailing($this->preferences, $members);
            } else {
                if (
                    count($filters->selected) == 0
                    && !isset($get['mailing_new'])
                    && !isset($get['reminder'])
                ) {
                    Analog::log(
                        '[Mailings] No member selected for mailing',
                        Analog::WARNING
                    );

                    $redirect_url = $this->session->redirect_mailing ?? $this->routeparser->urlFor('members');
                    return $this->redirectWithErrors(
                        response: $response,
                        errors: [_T('No member selected for mailing!')],
                        redirect_url: $redirect_url
                    );
                }
                $m = new Members();
                $members = $m->getArrayList($filters->selected);
                $mailing = new Mailing($this->preferences, ($members !== false) ? $members : []);
            }

            if (isset($get['remove_attachment'])) {
                $mailing->removeAttachment($get['remove_attachment']);
            }

            if ($mailing->current_step !== Mailing::STEP_SENT) {
                $this->session->mailing = $mailing;
            }

            /** TODO: replace that... */
            $this->session->labels = $mailing->unreachables;

            if (!$this->login->isSuperAdmin()) {
                $member = new Adherent($this->zdb, (int)$this->login->id, false);
                $params['sender_current'] = [
                    'name'  => $member->sname,
                    'email' => $member->getEmail()
                ];
            }

            $params = array_merge(
                $params,
                [
                    'mailing'           => $mailing,
                    'attachments'       => $mailing->attachments,
                    'html_editor'       => true,
                    'html_editor_active' => $this->preferences->pref_editor_enabled,
                    'documentation'     => 'usermanual/adherents.html#e-mailing'
                ]
            );
        }

        // display page
        $this->view->render(
            $response,
            'pages/mailing_form.html.twig',
            array_merge(
                [
                    'page_title' => _T("Mailing")
                ],
                $params
            )
        );
        return $response;
    }

    /**
     * Add action
     */
    #[Route(
        name: 'doMailing',
        pattern: '/mailing',
        methods: ['POST']
    )]
    public function doAdd(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $error_detected = [];
        $success_detected = [];

        $goto = $this->routeparser->urlFor('mailings');
        $redirect_url = $this->session->redirect_mailing ?? $this->routeparser->urlFor('members');

        //We're done :-)
        if (
            isset($post['mailing_done'])
            || isset($post['mailing_cancel'])
        ) {
            if ($this->session->mailing !== null) {
                // check for temporary attachments to remove
                $m = $this->session->mailing;
                $m->removeAttachments(true);
            }
            $this->session->mailing = null;
            unset($this->session->mailing);
            $this->session->redirect_mailing = null;
            unset($this->session->redirect_mailing);
            $this->session->{$this->getFilterName($this->getDefaultFilterName())} = null;
            unset($this->session->{$this->getFilterName($this->getDefaultFilterName())});

            return $response
                ->withStatus(301)
                ->withHeader('Location', $redirect_url);
        }

        if (
            $this->preferences->pref_mail_method == Mailing::METHOD_DISABLED
            && !Galette::isDemo()
        ) {
            $this->history->add(
                _T("Trying to load mailing while email is disabled in preferences.")
            );
            $error_detected[] = _T("Trying to load mailing while email is disabled in preferences.");
            $goto = $this->routeparser->urlFor('slash');
        } else {
            $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName())} ?? new MembersList();

            if (
                $this->session->mailing !== null
            ) {
                $mailing = $this->session->mailing;
            } else {
                if (count($filters->selected) == 0) {
                    Analog::log(
                        '[Mailings] No member selected for mailing',
                        Analog::WARNING
                    );

                    return $this->redirectWithErrors(
                        response: $response,
                        errors: [_T('No member selected for mailing!')],
                        redirect_url: $redirect_url
                    );
                }
                $m = new Members();
                $members = $m->getArrayList($filters->selected);
                $mailing = new Mailing($this->preferences, ($members !== false) ? $members : []);
            }

            if (
                isset($post['mailing_go'])
                || isset($post['mailing_reset'])
                || isset($post['mailing_confirm'])
                || isset($post['mailing_save'])
            ) {
                if (trim((string)$post['mailing_objet']) == '') {
                    $error_detected[] = _T("Please type an object for the message.");
                } else {
                    $mailing->subject = $post['mailing_objet'];
                }

                if (trim((string)$post['mailing_corps']) == '') {
                    $error_detected[] = _T("Please enter a message.");
                } else {
                    $mailing->message = $post['mailing_corps'];
                }

                switch ($post['sender'] ?? false) {
                    case GaletteMail::SENDER_CURRENT:
                        $member = new Adherent($this->zdb, (int)$this->login->id, false);
                        $mailing->setSender(
                            $member->sname,
                            $member->getEmail()
                        );
                        break;
                    case GaletteMail::SENDER_OTHER:
                        $mailing->setSender(
                            $post['sender_name'],
                            $post['sender_address']
                        );
                        break;
                    case GaletteMail::SENDER_PREFS:
                    default:
                        //nothing to do; this is the default :)
                        break;
                }

                $mailing->html = isset($post['mailing_html']);

                //handle attachments
                $res = $mailing->upload($request->getUploadedFiles(), 'attachment');
                if (!$res) {
                    $error_detected = array_merge($error_detected, $mailing->uploadErrors());
                }

                if (
                    count($error_detected) == 0
                    && !isset($post['mailing_reset'])
                    && !isset($post['mailing_save'])
                ) {
                    $mailing->current_step = Mailing::STEP_PREVIEW;
                } else {
                    $mailing->current_step = Mailing::STEP_START;
                }
                //until mail is sent (above), we redirect to mailing page
                $goto = $this->routeparser->urlFor('mailing');
            }

            if (isset($post['mailing_confirm']) && count($error_detected) == 0) {
                $mailing->current_step = Mailing::STEP_SEND;

                //when hourly/daily limits are set, sending must be spread over
                //time: store the mailing and queue its recipients instead of
                //sending synchronously
                $use_queue = ((int)$this->preferences->pref_mail_hourly_limit > 0)
                    || ((int)$this->preferences->pref_mail_daily_limit > 0);

                if ($use_queue) {
                    $mlh = new MailingHistory($this->zdb, $this->login, $this->preferences, null, $mailing);
                    $mlh->storeMailing(false);
                    $queue = new MailingQueue($this->zdb, $this->preferences);
                    $nb = $queue->enqueue((int)$mailing->id, $mailing->recipients);
                    Analog::log(
                        '[Mailings] ' . $nb . ' recipient(s) queued for mailing #' . $mailing->id,
                        Analog::INFO
                    );
                    //cleanup and redirect to the progress page
                    $this->session->{$this->getFilterName($this->getDefaultFilterName())} = null;
                    $this->session->mailing = null;
                    $this->session->redirect_mailing = null;
                    return $response
                        ->withStatus(301)
                        ->withHeader(
                            'Location',
                            $this->routeparser->urlFor('mailingQueue', ['id' => (string)$mailing->id])
                        );
                }

                //ok... let's go for fun
                $sent = $mailing->send();
                if ($sent == Mailing::MAIL_ERROR) {
                    $mailing->current_step = Mailing::STEP_START;
                    Analog::log(
                        '[Mailings] Message was not sent. Errors: '
                        . print_r($mailing->errors, true),
                        Analog::ERROR
                    );
                    foreach ($mailing->errors as $e) {
                        $error_detected[] = $e;
                    }
                } else {
                    $mlh = new MailingHistory(
                        zdb: $this->zdb,
                        login: $this->login,
                        preferences: $this->preferences,
                        filters: null,
                        mailing: $mailing
                    );
                    $mlh->storeMailing(true);
                    Analog::log(
                        '[Mailings] Message has been sent.',
                        Analog::INFO
                    );
                    $mailing->current_step = Mailing::STEP_SENT;
                    //cleanup
                    $this->session->{$this->getFilterName($this->getDefaultFilterName())} = null;
                    $this->session->mailing = null;
                    $this->session->redirect_mailing = null;
                    $success_detected[] = _T("Mailing has been successfully sent!");
                    $goto = $redirect_url;
                }
            }

            if ($mailing->current_step !== Mailing::STEP_SENT) {
                $this->session->mailing = $mailing;
            }

            /** TODO: replace that... */
            $this->session->labels = $mailing->unreachables;

            if (
                !isset($post['html_editor_active'])
                || trim($post['html_editor_active']) == ''
            ) {
                $post['html_editor_active'] = $this->preferences->pref_editor_enabled;
            }

            if (isset($post['mailing_save'])) {
                //user requested to save the mailing
                $histo = new MailingHistory(
                    zdb: $this->zdb,
                    login: $this->login,
                    preferences: $this->preferences,
                    filters: null,
                    mailing: $mailing
                );
                if ($histo->storeMailing() !== false) {
                    $success_detected[] = _T("Mailing has been successfully saved.");
                    $this->session->mailing = null;
                    $this->session->redirect_mailing = null;
                    $goto = $this->routeparser->urlFor('mailings');
                }
            }
        }

        return $this->redirect(
            response: $response,
            redirect_url: $goto,
            successes: $success_detected,
            errors: $error_detected
        );
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * Mailings history page
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param int|string|null $value  Value of the option
     */
    #[Route(
        name: 'mailings',
        pattern: '/mailings[/{option:page|order|reset}/{value}]',
        methods: ['GET']
    )]
    public function list(Request $request, Response $response, ?string $option = null, int|string|null $value = null): Response
    {
        if (isset($this->session->{$this->getFilterName('mailings')})) {
            $filters = $this->session->{$this->getFilterName('mailings')};
        } else {
            $filters = new MailingsList();
        }

        if (isset($request->getQueryParams()['nbshow'])) {
            $filters->show = $request->getQueryParams()['nbshow'];
        }

        $mailhist = new MailingHistory(
            zdb: $this->zdb,
            login: $this->login,
            preferences: $this->preferences,
            filters: $filters
        );

        switch ($option) {
            case 'page':
                $filters->current_page = (int)$value;
                break;
            case 'order':
                $filters->orderby = $value;
                break;
            case 'reset':
                $mailhist->clean();
                //reinitialize object after flush
                $filters = new MailingsList();
                $mailhist = new MailingHistory(
                    zdb: $this->zdb,
                    login: $this->login,
                    preferences: $this->preferences,
                    filters: $filters
                );
                break;
            default:
                break;
        }

        $this->session->{$this->getFilterName('mailings')} = $filters;

        //assign pagination variables to the template and add pagination links
        $mailhist->filters->setViewPagination($this->routeparser, $this->view);
        $history_list = $mailhist->getHistory();
        //assign pagination variables to the template and add pagination links
        $mailhist->filters->setViewPagination($this->routeparser, $this->view);

        // display page
        $this->view->render(
            $response,
            'pages/mailings_list.html.twig',
            [
                'page_title'        => _T("Mailings"),
                'logs'              => $history_list,
                'history'           => $mailhist,
                'filters'           => $filters,
                'documentation'     => 'usermanual/adherents.html#e-mailing'
            ]
        );
        return $response;
    }

    /**
     * Mailings filtering
     */
    #[Route(
        name: 'mailings_filter',
        pattern: '/mailings/filter',
        methods: ['POST']
    )]
    public function filter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        if ($this->session->{$this->getFilterName('mailings')} !== null) {
            $filters = $this->session->{$this->getFilterName('mailings')};
        } else {
            $filters = new MailingsList();
        }

        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            if (isset($post['nbshow']) && is_numeric($post['nbshow'])) {
                $filters->show = $post['nbshow'];
            }

            if (isset($post['end_date_filter']) || isset($post['start_date_filter'])) {
                if (isset($post['start_date_filter'])) {
                    $filters->start_date_filter = $post['start_date_filter'];
                }
                if (isset($post['end_date_filter'])) {
                    $filters->end_date_filter = $post['end_date_filter'];
                }
            }

            if (isset($post['sender_filter'])) {
                $filters->sender_filter = $post['sender_filter'];
            }

            if (isset($post['sent_filter'])) {
                $filters->sent_filter = $post['sent_filter'];
            }


            if (isset($post['subject_filter'])) {
                $filters->subject_filter = $post['subject_filter'];
            }
        }

        $this->session->{$this->getFilterName('mailings')} = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('mailings'));
    }

    /**
     * Edit page
     *
     * @param int $id Record id
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        //no edit page, just to satisfy inheritance
        return $response;
    }

    /**
     * Edit action
     *
     * @param int $id Record id
     */
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        //no edit page, just to satisfy inheritance
        return $response;
    }

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('mailings');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveMailing',
            ['id' => $args['id'] ?? null]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function confirmRemoveTitle(array $args): string
    {
        return sprintf(
            _T('Remove mailing #%1$s'),
            $args['id'] ?? ''
        );
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    protected function doDelete(array $args, array $post): bool
    {
        $mailhist = new MailingHistory($this->zdb, $this->login, $this->preferences);
        return $mailhist->removeEntries((int)$args['id'], $this->history);
    }
    // /CRUD - Delete
    // /CRUD

    /**
     * Preview action
     *
     * @param ?int $id Mailing id
     */
    #[Route(
        name: 'mailingPreview',
        pattern: '/mailing/preview[/{id:\d+}]',
        methods: ['GET', 'POST']
    )]
    public function preview(Request $request, Response $response, ?int $id = null): Response
    {
        $post = $request->getParsedBody();
        // check for ajax mode
        $ajax = false;
        if (
            ($this->isAjax($request))
            || isset($post['ajax'])
            && $post['ajax'] == 'true'
        ) {
            $ajax = true;
        }

        if ($id !== null) {
            $mailing = new Mailing($this->preferences);
            MailingHistory::loadFrom(zdb: $this->zdb, id: $id, mailing: $mailing, new: false);
        } else {
            $mailing = $this->session->mailing;

            switch ($post['sender']) {
                case GaletteMail::SENDER_CURRENT:
                    $member = new Adherent($this->zdb, (int)$this->login->id, false);
                    $mailing->setSender(
                        $member->sname,
                        $member->getEmail()
                    );
                    break;
                case GaletteMail::SENDER_OTHER:
                    $mailing->setSender(
                        $post['sender_name'],
                        $post['sender_address']
                    );
                    break;
                case GaletteMail::SENDER_PREFS:
                default:
                    //nothing to do; this is the default :)
                    break;
            }

            $mailing->subject = $post['subject'];
            $mailing->message = $post['body'];
            $mailing->html = ($post['html'] === 'true');
        }
        $attachments = $mailing->attachments;

        // display page
        $this->view->render(
            $response,
            'modals/mailing_preview.html.twig',
            [
                'page_title'    => _T("Mailing preview"),
                'mailing_id'    => $id,
                'mode'          => ($ajax ? 'ajax' : ''),
                'mailing'       => $mailing,
                'recipients'    => $mailing->recipients,
                'sender'        => $mailing->getSenderName() . ' <'
                    . $mailing->getSenderAddress() . '>',
                'attachments'   => $attachments

            ]
        );
        return $response;
    }

    /**
     * Preview attachment action
     *
     * @param int $id  Mailing id
     * @param int $pos Attachment position in list
     */
    #[Route(
        name: 'previewAttachment',
        pattern: '/mailing/preview/{id:\d+}/attachment/{pos:\d+}',
        methods: ['GET']
    )]
    public function previewAttachment(Request $request, Response $response, int $id, int $pos): Response
    {
        $mailing = new Mailing($this->preferences);
        MailingHistory::loadFrom(zdb: $this->zdb, id: $id, mailing: $mailing, new: false);
        $attachments = $mailing->attachments;
        $attachment = $attachments[$pos];
        $filepath = $attachment->getDestDir() . $attachment->getFileName();

        $response = $response->withHeader('Content-type', $attachment->getMimeType($filepath));

        $body = $response->getBody();
        $body->write(file_get_contents($filepath));
        return $response;
    }

    /**
     * Set recipients action
     */
    #[Route(
        name: 'mailingRecipients',
        pattern: '/ajax/mailing/set-recipients',
        methods: ['POST']
    )]
    public function setRecipients(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $mailing = $this->session->mailing;

        $m = new Members();
        $members = [];

        if (isset($post['recipients'])) {
            $members = $m->getArrayList(
                ids: $post['recipients'],
                orderby: null,
                with_photos: false,
                as_members: true,
                fields: null,
                export: false,
                dues: false,
                parent: true
            );
        }
        $mailing->setRecipients($members);

        $this->session->mailing = $mailing;

        // display page
        $this->view->render(
            $response,
            'elements/mailing_recipients.html.twig',
            [
                'mailing'       => $mailing

            ]
        );
        return $response;
    }

    /**
     * Mailing queue progress page
     *
     * @param int $id Mailing history id
     */
    #[Route(
        name: 'mailingQueue',
        pattern: '/mailing/queue/{id:\d+}',
        methods: ['GET']
    )]
    public function queue(Request $request, Response $response, int $id): Response
    {
        $queue = new MailingQueue($this->zdb, $this->preferences);

        // display page
        $this->view->render(
            $response,
            'pages/mailing_queue.html.twig',
            [
                'page_title'    => _T("Sending mailing"),
                'mailing_id'    => $id,
                'process_url'   => $this->routeparser->urlFor('mailingProcessQueue'),
                'stats'         => $queue->getStats($id),
                'batch_delay'   => (int)$this->preferences->pref_mail_batch_delay,
                'documentation' => 'usermanual/adherents.html#e-mailing'
            ]
        );
        return $response;
    }

    /**
     * Process a batch of the mailing queue (AJAX)
     */
    #[Route(
        name: 'mailingProcessQueue',
        pattern: '/ajax/mailing/process-queue',
        methods: ['POST']
    )]
    public function processQueue(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $mailing_id = isset($post['id']) && is_numeric($post['id'])
            ? (int)$post['id']
            : null;

        $queue = new MailingQueue($this->zdb, $this->preferences);
        $progress = $queue->processBatch($mailing_id, MailingQueue::KIND_MAILING);

        return $this->withJson($response, $progress);
    }

    /**
     * Reminders queue progress page
     */
    #[Route(
        name: 'remindersQueue',
        pattern: '/reminders/queue',
        methods: ['GET']
    )]
    public function remindersQueue(Request $request, Response $response): Response
    {
        $queue = new MailingQueue($this->zdb, $this->preferences);

        // display page
        $this->view->render(
            $response,
            'pages/mailing_queue.html.twig',
            [
                'page_title'    => _T("Sending reminders"),
                'mailing_id'    => null,
                'process_url'   => $this->routeparser->urlFor('remindersProcessQueue'),
                'stats'         => $queue->getStats(null, MailingQueue::KIND_REMINDER),
                'batch_delay'   => (int)$this->preferences->pref_mail_batch_delay,
                'documentation' => 'usermanual/contributions.html#reminders'
            ]
        );
        return $response;
    }

    /**
     * Process a batch of the reminders queue (AJAX)
     */
    #[Route(
        name: 'remindersProcessQueue',
        pattern: '/ajax/reminders/process-queue',
        methods: ['POST']
    )]
    public function remindersProcessQueue(Request $request, Response $response): Response
    {
        $queue = new MailingQueue($this->zdb, $this->preferences);
        $queue->setReminderContext($this->history, $this->login, $this->routeparser);
        $progress = $queue->processBatch(null, MailingQueue::KIND_REMINDER);

        return $this->withJson($response, $progress);
    }

    /**
     * Get default filter name
     */
    public function getDefaultFilterName(): string
    {
        return 'members_sendmail';
    }
}
