<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Controllers;

use Galette\Controllers\Attributes\Route;
use Throwable;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Core\History;
use Galette\Filters\HistoryList;
use Analog\Analog;

/**
 * Galette history controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class HistoryController extends AbstractController
{
    /**
     * History page
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param string|int|null $value  Value of the option
     */
    #[Route(
        name: 'history',
        pattern: '/history[/{option:page|order}/{value}]',
        methods: ['GET']
    )]
    public function list(
        Request $request,
        Response $response,
        ?string $option = null,
        string|int|null $value = null
    ): Response {
        if (isset($this->session->{$this->getFilterName(static::getDefaultFilterName())})) {
            $filters = $this->session->{$this->getFilterName(static::getDefaultFilterName())};
        } else {
            $filters = new HistoryList();
        }

        if (isset($request->getQueryParams()['nbshow'])) {
            $filters->show = $request->getQueryParams()['nbshow'];
        }

        switch ($option) {
            case 'page':
                $filters->current_page = (int)$value;
                break;
            case 'order':
                $filters->orderby = $value;
                break;
            default:
                break;
        }

        $this->session->{$this->getFilterName(static::getDefaultFilterName())} = $filters;

        $this->history->setFilters($filters);
        $logs = $this->history->getHistory();

        //assign pagination variables to the template and add pagination links
        $this->history->filters->setViewPagination($this->routeparser, $this->view);

        // display page
        $this->view->render(
            $response,
            'pages/history.html.twig',
            [
                'page_title'        => _T("Logs"),
                'logs'              => $logs,
                'history'           => $this->history
            ]
        );
        return $response;
    }

    /**
     * History filtering
     */
    #[Route(
        name: 'history_filter',
        pattern: '/history/filter',
        methods: ['POST']
    )]
    public function historyFilter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        if ($this->session->{$this->getFilterName(static::getDefaultFilterName())} !== null) {
            $filters = $this->session->{$this->getFilterName(static::getDefaultFilterName())};
        } else {
            $filters = new HistoryList();
        }

        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            if (isset($post['nbshow']) && is_numeric($post['nbshow'])) {
                $filters->show = (int)$post['nbshow'];
            }

            if (isset($post['end_date_filter']) || isset($post['start_date_filter'])) {
                if (isset($post['start_date_filter'])) {
                    $filters->start_date_filter = $post['start_date_filter'];
                }
                if (isset($post['end_date_filter'])) {
                    $filters->end_date_filter = $post['end_date_filter'];
                }
            }

            if (isset($post['user_filter'])) {
                $filters->user_filter = $post['user_filter'];
            }

            if (isset($post['action_filter'])) {
                $filters->action_filter = $post['action_filter'];
            }
        }

        $this->session->{$this->getFilterName(static::getDefaultFilterName())} = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('history'));
    }

    /**
     * History flush
     */
    #[Route(
        name: 'doFlushHistory',
        pattern: '/history/flush',
        methods: ['POST']
    )]
    public function flushHistory(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $ajax = isset($post['ajax']) && $post['ajax'] === 'true';
        $success = false;

        $uri = $post['redirect_uri'] ?? $this->routeparser->urlFor('slash');

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
                $this->session->{$this->getFilterName(static::getDefaultFilterName())} = $filters;

                $this->flash->addMessage(
                    'success_detected',
                    _T('Logs have been flushed!')
                );
                $success = true;
            } catch (Throwable $e) {
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
            return $this->withJson(
                $response,
                [
                    'success'   => $success
                ]
            );
        }
    }

    /**
     * History flush confirmation
     */
    #[Route(
        name: 'flushHistory',
        pattern: '/history/flush',
        methods: ['GET']
    )]
    public function confirmHistoryFlush(Request $request, Response $response): Response
    {
        $data = [
            'redirect_uri'  => $this->routeparser->urlFor('history')
        ];

        // display page
        $this->view->render(
            $response,
            'modals/confirm_removal.html.twig',
            [
                'mode'          => ($this->isAjax($request)) ? 'ajax' : '',
                'page_title'    => _T('Flush the logs'),
                'form_url'      => $this->routeparser->urlFor('doFlushHistory'),
                'cancel_uri'    => $data['redirect_uri'],
                'data'          => $data
            ]
        );
        return $response;
    }

    /**
     * Get default filter name
     */
    public static function getDefaultFilterName(): string
    {
        return 'history';
    }
}
