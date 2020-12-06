<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette Mailing controller
 *
 * PHP version 5
 *
 * Copyright © 2019-2020 The Galette Team
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
 * @category  Controllers
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-06
 */

namespace Galette\Controllers\Crud;

use Throwable;
use Galette\Controllers\CrudController;
use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Core\GaletteMail;
use Galette\Core\Mailing;
use Galette\Core\MailingHistory;
use Galette\Entity\Adherent;
use Galette\Filters\MailingsList;
use Galette\Filters\MembersList;
use Galette\Repository\Members;
use Analog\Analog;

/**
 * Galette Mailing controller
 *
 * @category  Controllers
 * @name      MailingsController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019-2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2019-12-06
 */

class MailingsController extends CrudController
{
    // CRUD - Create

    /**
     * Add page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function add(Request $request, Response $response, array $args = []): Response
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
            $this->session->redirect_mailing = null;
        }

        $params = array();

        if (
            $this->preferences->pref_mail_method == Mailing::METHOD_DISABLED
            && !GALETTE_MODE === 'DEMO'
        ) {
            $this->history->add(
                _T("Trying to load mailing while email is disabled in preferences.")
            );
            $this->flash->addMessage(
                'error_detected',
                _T("Trying to load mailing while email is disabled in preferences.")
            );
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->router->pathFor('slash'));
        } else {
            if (isset($this->session->filter_mailing)) {
                $filters = $this->session->filter_mailing;
            } elseif (isset($this->session->filter_members)) {
                $filters = $this->session->filter_members;
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
                $mailing = new Mailing($this->preferences, ($members !== false) ? $members : []);
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

                    $this->flash->addMessage(
                        'error_detected',
                        _T('No member selected for mailing!')
                    );

                    if (isset($profiler)) {
                        $profiler->stop();
                    }

                    $redirect_url = ($this->session->redirect_mailing !== null) ?
                        $this->session->redirect_mailing : $this->router->pathFor('members');

                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $redirect_url);
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
                array(
                    'mailing'           => $mailing,
                    'attachments'       => $mailing->attachments,
                    'html_editor'       => true,
                    'html_editor_active' => $this->preferences->pref_editor_enabled
                )
            );
        }

        // display page
        $this->view->render(
            $response,
            'mailing_adherents.tpl',
            array_merge(
                array(
                    'page_title' => _T("Mailing")
                ),
                $params
            )
        );
        return $response;
    }

    /**
     * Add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response, array $args = []): Response
    {
        $post = $request->getParsedBody();
        $error_detected = [];
        $success_detected = [];

        $goto = $this->router->pathFor('mailings');
        $redirect_url = $this->session->redirect_mailing ?? $this->router->pathFor('members');

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
            $this->session->redirect_mailing = null;
            if (isset($this->session->filter_mailing)) {
                $filters = $this->session->filter_mailing;
                $filters->selected = [];
                $this->session->filter_mailing = $filters;
            }

            return $response
                ->withStatus(301)
                ->withHeader('Location', $redirect_url);
        }

        if (
            $this->preferences->pref_mail_method == Mailing::METHOD_DISABLED
            && !GALETTE_MODE === 'DEMO'
        ) {
            $this->history->add(
                _T("Trying to load mailing while email is disabled in preferences.")
            );
            $error_detected[] = _T("Trying to load mailing while email is disabled in preferences.");
            $goto = $this->router->pathFor('slash');
        } else {
            if (isset($this->session->filter_members)) {
                $filters = $this->session->filter_members;
            } else {
                $filters = new MembersList();
            }

            if (
                $this->session->mailing !== null
                && !isset($post['mailing_cancel'])
            ) {
                $mailing = $this->session->mailing;
            } else {
                if (count($filters->selected) == 0) {
                    Analog::log(
                        '[Mailings] No member selected for mailing',
                        Analog::WARNING
                    );

                    $this->flash->addMessage(
                        'error_detected',
                        _T('No member selected for mailing!')
                    );

                    return $response
                        ->withStatus(301)
                        ->withHeader('Location', $redirect_url);
                }
                $m = new Members();
                $members = $m->getArrayList($filters->selected);
                $mailing = new Mailing($this->preferences, ($members !== false) ? $members : null);
            }

            if (
                isset($post['mailing_go'])
                || isset($post['mailing_reset'])
                || isset($post['mailing_confirm'])
                || isset($post['mailing_save'])
            ) {
                if (trim($post['mailing_objet']) == '') {
                    $error_detected[] = _T("Please type an object for the message.");
                } else {
                    $mailing->subject = $post['mailing_objet'];
                }

                if (trim($post['mailing_corps']) == '') {
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

                $mailing->html = (isset($post['mailing_html'])) ? true : false;

                //handle attachments
                if (isset($_FILES['files'])) {
                    $cnt_files = count($_FILES['files']['name']);
                    for ($i = 0; $i < $cnt_files; $i++) {
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
                                $this->logo->getPhpErrorMessage($_FILES['files']['error'][$i]),
                                Analog::WARNING
                            );
                            $error_detected[] = $this->logo->getPhpErrorMessage(
                                $_FILES['files']['error'][$i]
                            );
                        }
                    }
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
                $goto = $this->router->pathFor('mailing');
            }

            if (isset($post['mailing_confirm']) && count($error_detected) == 0) {
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
                    $mlh = new MailingHistory($this->zdb, $this->login, $this->preferences, null, $mailing);
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
                $histo = new MailingHistory($this->zdb, $this->login, $this->preferences, null, $mailing);
                if ($histo->storeMailing() !== false) {
                    $success_detected[] = _T("Mailing has been successfully saved.");
                    $this->session->mailing = null;
                    $this->session->redirect_mailing = null;
                    $goto = $this->router->pathFor('mailings');
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

    // /CRUD - Create
    // CRUD - Read

    /**
     * Mailings history page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function list(Request $request, Response $response, array $args = []): Response
    {
        $option = null;
        if (isset($args['option'])) {
            $option = $args['option'];
        }

        $value = null;
        if (isset($args['value'])) {
            $value = $args['value'];
        }

        if (isset($this->session->filter_mailings)) {
            $filters = $this->session->filter_mailings;
        } else {
            $filters = new MailingsList();
        }

        if (isset($request->getQueryParams()['nbshow'])) {
            $filters->show = $request->getQueryParams()['nbshow'];
        }

        $mailhist = new MailingHistory($this->zdb, $this->login, $this->preferences, $filters);

        if ($option !== null) {
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
                    $mailhist = new MailingHistory($this->zdb, $this->login, $this->preferences, $filters);
                    break;
            }
        }

        $this->session->filter_mailings = $filters;

        //assign pagination variables to the template and add pagination links
        $mailhist->filters->setSmartyPagination($this->router, $this->view->getSmarty());
        $history_list = $mailhist->getHistory();
        //assign pagination variables to the template and add pagination links
        $mailhist->filters->setSmartyPagination($this->router, $this->view->getSmarty());

        // display page
        $this->view->render(
            $response,
            'gestion_mailings.tpl',
            array(
                'page_title'        => _T("Mailings"),
                'logs'              => $history_list,
                'history'           => $mailhist
            )
        );
        return $response;
    }

    /**
     * Mailings filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function filter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $error_detected = [];

        if ($this->session->filter_mailings !== null) {
            $filters = $this->session->filter_mailings;
        } else {
            $filters = new MailingsList();
        }

        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            if (
                (isset($post['nbshow']) && is_numeric($post['nbshow']))
            ) {
                $filters->show = $post['nbshow'];
            }

            if (isset($post['end_date_filter']) || isset($post['start_date_filter'])) {
                try {
                    if (isset($post['start_date_filter'])) {
                        $filters->start_date_filter = $post['start_date_filter'];
                    }
                    if (isset($post['end_date_filter'])) {
                        $filters->end_date_filter = $post['end_date_filter'];
                    }
                } catch (Throwable $e) {
                    $error_detected[] = $e->getMessage();
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

        $this->session->filter_mailings = $filters;

        if (count($error_detected) > 0) {
            //report errors
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->router->pathFor('mailings'));
    }

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, array $args = []): Response
    {
        //TODO
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, array $args = []): Response
    {
        //TODO
    }

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args = [])
    {
        return $this->router->pathFor('mailings');
    }

    /**
     * Get form URI
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args = [])
    {
        return $this->router->pathFor(
            'doRemoveMailing',
            ['id' => $args['id'] ?? null]
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args = [])
    {
        return sprintf(
            _T('Remove mailing #%1$s'),
            $args['id'] ?? ''
        );
    }

    /**
     * Remove object
     *
     * @param array $args Route arguments
     * @param array $post POST values
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post)
    {
        $mailhist = new MailingHistory($this->zdb, $this->login, $this->preferences);
        return $mailhist->removeEntries($args['id'], $this->history);
    }
    // /CRUD - Delete
    // /CRUD

    /**
     * Preview action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function preview(Request $request, Response $response, array $args = []): Response
    {
        $post = $request->getParsedBody();
        // check for ajax mode
        $ajax = false;
        if (
            $request->isXhr()
            || isset($post['ajax'])
            && $post['ajax'] == 'true'
        ) {
            $ajax = true;
        }

        $mailing = null;
        if (isset($args['id'])) {
            $mailing = new Mailing($this->preferences);
            MailingHistory::loadFrom($this->zdb, (int)$args['id'], $mailing, false);
            $attachments = $mailing->attachments;
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
            $attachments = $mailing->attachments;
        }

        // display page
        $this->view->render(
            $response,
            'mailing_preview.tpl',
            [
                'page_title'    => _T("Mailing preview"),
                'mailing_id'    => $args['id'] ?? null,
                'mode'          => ($ajax ? 'ajax' : ''),
                'mailing'       => $mailing,
                'recipients'    => $mailing->recipients,
                'sender'        => $mailing->getSenderName() . ' &lt;' .
                    $mailing->getSenderAddress() . '&gt;',
                'attachments'   => $attachments

            ]
        );
        return $response;
    }

    /**
     * Preview attachement action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function previewAttachment(Request $request, Response $response, array $args = []): Response
    {
        $mailing = new Mailing($this->preferences);
        MailingHistory::loadFrom($this->zdb, (int)$args['id'], $mailing, false);
        $attachments = $mailing->attachments;
        $attachment = $attachments[$args['pos']];
        $filepath = $attachment->getDestDir() . $attachment->getFileName();

        $response = $response->withHeader('Content-type', $attachment->getMimeType($filepath));

        $body = $response->getBody();
        $body->write(file_get_contents($filepath));
        return $response;
    }

    /**
     * Set recipients action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function setRecipients(Request $request, Response $response, array $args = []): Response
    {
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
}
