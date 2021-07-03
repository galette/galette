<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette history controller
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
 */

namespace Galette\Controllers;

use Throwable;
use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Core\History;
use Galette\Filters\HistoryList;
use Analog\Analog;

/**
 * Galette history controller
 *
 * @category  Controllers
 * @name      HistoryController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
 */

class HistoryController extends AbstractController
{
    /**
     * History page
     *
     * @param Request        $request  PSR Request
     * @param Response       $response PSR Response
     * @param string         $option   One of 'page' or 'order'
     * @param string|integer $value    Value of the option
     *
     * @return Response
     */
    public function list(
        Request $request,
        Response $response,
        $option = null,
        $value = null
    ): Response {
        if (isset($this->session->filter_history)) {
            $filters = $this->session->filter_history;
        } else {
            $filters = new HistoryList();
        }

        if (isset($request->getQueryParams()['nbshow'])) {
            $filters->show = $request->getQueryParams()['nbshow'];
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

        $this->session->filter_history = $filters;

        $this->history->setFilters($filters);
        $logs = $this->history->getHistory();

        //assign pagination variables to the template and add pagination links
        $this->history->filters->setSmartyPagination($this->router, $this->view->getSmarty());

        // display page
        $this->view->render(
            $response,
            'history.tpl',
            array(
                'page_title'        => _T("Logs"),
                'logs'              => $logs,
                'history'           => $this->history
            )
        );
        return $response;
    }

    /**
     * History filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function historyFilter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $error_detected = [];

        if ($this->session->filter_history !== null) {
            $filters = $this->session->filter_history;
        } else {
            $filters = new HistoryList();
        }

        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            if (
                (isset($post['nbshow']) && is_numeric($post['nbshow']))
            ) {
                $filters->show = (int)$post['nbshow'];
            }

            if (isset($post['end_date_filter']) || isset($post['start_date_filter'])) {
                try {
                    if (isset($post['start_date_filter'])) {
                        $field = _T("start date filter");
                        $filters->start_date_filter = $post['start_date_filter'];
                    }
                    if (isset($post['end_date_filter'])) {
                        $field = _T("end date filter");
                        $filters->end_date_filter = $post['end_date_filter'];
                    }
                } catch (Throwable $e) {
                    $error_detected[] = $e->getMessage();
                }
            }

            if (isset($post['user_filter'])) {
                $filters->user_filter = $post['user_filter'];
            }

            if (isset($post['action_filter'])) {
                $filters->action_filter = $post['action_filter'];
            }
        }

        $this->session->filter_history = $filters;

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
            ->withHeader('Location', $this->router->pathFor('history'));
    }

    /**
     * History flush
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function flushHistory(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $ajax = isset($post['ajax']) && $post['ajax'] === 'true';
        $success = false;

        $uri = isset($post['redirect_uri']) ?
            $post['redirect_uri'] : $this->router->pathFor('slash');

        if (!isset($post['confirm'])) {
            $this->flash->addMessage(
                'error_detected',
                _T("Removal has not been confirmed!")
            );
        } else {
            try {
                $this->history->clean();
                //reinitialize object after flush
                $this->history = new History($this->zdb, $this->login, $this->preferences);
                $filters = new HistoryList();
                $this->session->filter_history = $filters;

                $this->flash->addMessage(
                    'success_detected',
                    _T('Logs have been flushed!')
                );
                $success = true;
            } catch (Throwable $e) {
                $this->zdb->connection->rollBack();
                Analog::log(
                    'An error occurred flushing logs | ' . $e->getMessage(),
                    Analog::ERROR
                );

                $this->flash->addMessage(
                    'error_detected',
                    _T('An error occurred trying to flush logs :(')
                );
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

    /**
     * History flush confirmation
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function confirmHistoryFlush(Request $request, Response $response): Response
    {
        $data = [
            'redirect_uri'  => $this->router->pathFor('history')
        ];

        // display page
        $this->view->render(
            $response,
            'confirm_removal.tpl',
            array(
                'mode'          => $request->isXhr() ? 'ajax' : '',
                'page_title'    => _T('Flush the logs'),
                'form_url'      => $this->router->pathFor('doFlushHistory'),
                'cancel_uri'    => $data['redirect_uri'],
                'data'          => $data
            )
        );
        return $response;
    }
}
