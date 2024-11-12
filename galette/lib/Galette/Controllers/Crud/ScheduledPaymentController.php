<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
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
 */

declare(strict_types=1);

namespace Galette\Controllers\Crud;

use Galette\Controllers\CrudController;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\ScheduledPayment;
use Galette\Filters\ScheduledPaymentsList;
use Galette\Repository\ScheduledPayments;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Repository\PaymentTypes;

/**
 * Galette payment types controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class ScheduledPaymentController extends CrudController
{
    // CRUD - Create

    /**
     * Add page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id_cotis Contribution id
     *
     * @return Response
     */
    public function add(Request $request, Response $response, int $id_cotis = 0): Response
    {
        if (isset($this->session->scheduled_payment)) {
            $scheduled = $this->session->scheduled_payment;
            unset($this->session->scheduled_payment);
        } else {
            $scheduled = new ScheduledPayment($this->zdb);
        }
        $scheduled->setContribution($id_cotis);
        $mode = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' ? 'ajax' : '';

        if ($scheduled->getMissingAmount() == 0) {
            $this->flash->addMessage(
                'error_detected',
                _T("Contribution is fully scheduled!")
            );
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        'editContribution',
                        [
                            'type' => ($scheduled->getContribution()->isFee() ? Contribution::TYPE_FEE : Contribution::TYPE_DONATION),
                            'id' => (string)$id_cotis
                        ]
                    )
                );
        }

        // display page
        $this->view->render(
            $response,
            'pages/scheduledpayment_form.html.twig',
            [
                'page_title'    => _T("Add scheduled payment"),
                'scheduled'     => $scheduled,
                'mode'          => $mode
            ]
        );
        return $response;
    }

    /**
     * Add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response): Response
    {
        return $this->store($request, $response, null);
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param Request             $request  PSR Request
     * @param Response            $response PSR Response
     * @param string|null         $option   One of 'page' or 'order'
     * @param integer|string|null $value    Value of the option
     *
     * @return Response
     */
    public function list(Request $request, Response $response, ?string $option = null, int|string|null $value = null): Response
    {
        $get = $request->getQueryParams();
        $ajax = false;

        $filter_args = [];
        if (
            ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest')
            || isset($get['ajax'])
            && $get['ajax'] == 'true'
        ) {
            $ajax = true;
            $filter_args['suffix'] = 'ajax';
        }
        $session_varname = $this->getFilterName($this->getDefaultFilterName(), $filter_args);

        if (isset($this->session->$session_varname)) {
            $filters = $this->session->$session_varname;
        } else {
            $filters = new ScheduledPaymentsList();
        }

        if ($ajax && $get[Contribution::PK]) {
            $filters->from_contribution = (int)$get[Contribution::PK];
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

        $scheduled = new ScheduledPayments(
            $this->zdb,
            $this->login,
            $filters
        );
        $list = $scheduled->getList();

        //store filters into session
        $this->session->$session_varname = $filters;

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view);

        // display page
        $this->view->render(
            $response,
            'pages/scheduledpayments_list.html.twig',
            [
                'page_title'        => _T("Scheduled payments management"),
                'scheduled'         => $scheduled,
                'list'              => $list,
                'nb'                => $scheduled->getCount(),
                'filters'           => $filters,
                'mode'              => $ajax ? 'ajax' : ''
            ]
        );
        return $response;
    }

    /**
     * List page for logged-in member
     *
     * @param Request     $request  PSR Request
     * @param Response    $response PSR Response
     * @param string|null $type     One of 'transactions' or 'contributions'
     *
     * @return Response
     */
    public function myList(Request $request, Response $response, ?string $type = null): Response
    {
        return $this->list(
            $request->withQueryParams(
                $request->getQueryParams() + [
                    Adherent::PK => $this->login->id
                ]
            ),
            $response
        );
    }

    /**
     * Scheduled payments filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function filter(Request $request, Response $response): Response
    {
        $ajax = false;
        $filter_args = [];
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $ajax = true;
            $filter_args['suffix'] = 'ajax';
        }
        $filter_name = $this->getFilterName($this->getDefaultFilterName(), $filter_args);

        $post = $request->getParsedBody();
        $error_detected = [];

        if ($this->session->$filter_name !== null) {
            $filters = $this->session->$filter_name;
        } else {
            $filters = new ScheduledPaymentsList();
        }

        if (isset($post['clear_filter'])) {
            $filters->reinit($ajax);
        } else {
            if (
                (isset($post['nbshow']) && is_numeric($post['nbshow']))
            ) {
                $filters->show = $post['nbshow'];
            }

            if (isset($post['date_field'])) {
                $filters->date_field = $post['date_field'];
            }

            if (isset($post['end_date_filter']) || isset($post['start_date_filter'])) {
                if (isset($post['start_date_filter'])) {
                    $filters->start_date_filter = $post['start_date_filter'];
                }
                if (isset($post['end_date_filter'])) {
                    $filters->end_date_filter = $post['end_date_filter'];
                }
            }

            if (isset($post['payment_type_filter'])) {
                $ptf = (int)$post['payment_type_filter'];
                $ptypes = new PaymentTypes(
                    $this->zdb,
                    $this->preferences,
                    $this->login
                );
                $ptlist = $ptypes->getList(false);
                if (isset($ptlist[$ptf])) {
                    $filters->payment_type_filter = $ptf;
                } elseif ($ptf == -1) {
                    $filters->payment_type_filter = null;
                } else {
                    $error_detected[] = _T("- Unknown payment type!");
                }
            }
        }

        $this->session->$filter_name = $filters;

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
            ->withHeader('Location', $this->routeparser->urlFor('scheduledPayments'));
    }

    /**
     * Batch actions handler
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function handleBatch(Request $request, Response $response): Response
    {
        $filter_name = $this->getFilterName($this->getDefaultFilterName());
        $post = $request->getParsedBody();

        if (isset($post['entries_sel'])) {
            $filters = $this->session->$filter_name ?? new ScheduledPaymentsList();
            $filters->selected = $post['entries_sel'];

            if (isset($post['csv'])) {
                $this->session->{$this->getFilterName($this->getDefaultFilterName(), ['suffix' => 'csvexport'])} = $filters;
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('csv-scheduledPaymentslist'));
            }

            if (isset($post['delete'])) {
                $this->session->{$this->getFilterName($this->getDefaultFilterName(), ['suffix' => 'delete'])} = $filters;
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('removeScheduledPayments'));
            }

            throw new \RuntimeException('Does not know what to batch :(');
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("No scheduled payment was selected, please check at least one.")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor('scheduledPayments'));
        }
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Scheduled payment id
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, int $id): Response
    {
        if (isset($this->session->scheduled_payment)) {
            $scheduled = $this->session->scheduled_payment;
            unset($this->session->scheduled_payment);
        } else {
            $scheduled = new ScheduledPayment($this->zdb, $id);
        }
        $mode = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' ? 'ajax' : '';

        // display page
        $this->view->render(
            $response,
            'pages/scheduledpayment_form.html.twig',
            [
                'page_title'    => _T("Edit scheduled payment"),
                'scheduled'     => $scheduled,
                'mode'          => $mode
            ]
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param integer  $id       Type id
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        return $this->store($request, $response, $id);
    }

    /**
     * Store
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?integer $id       Type id
     *
     * @return Response
     */
    public function store(Request $request, Response $response, ?int $id = null): Response
    {
        $post = $request->getParsedBody();

        if (isset($post['cancel'])) {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->cancelUri($this->getArgs($request)));
        }

        $error_detected = [];
        $msg = null;

        $redirect_uri = $this->redirectUri($this->getArgs($request));
        $scheduled = new ScheduledPayment($this->zdb, $id);

        if (!$scheduled->check($post)) {
            $this->session->scheduled_payment = $scheduled;
            if ($id === null) {
                $redirect_uri = $this->routeparser->urlFor(
                    'addScheduledPayment',
                    [
                        Contribution::PK => $post[Contribution::PK]
                    ]
                );
            } else {
                $redirect_uri = $this->routeparser->urlFor(
                    'editScheduledPayment',
                    [
                        'id' => (string)$scheduled->getId()
                    ]
                );
            }
            $error_detected = $scheduled->getErrors();
        } else {
            $res = $scheduled->store();
            if (!$res) {
                $this->session->scheduled_payment = $scheduled;
                if ($id === null) {
                    $error_detected[] = _T("Scheduled payment has not been added!");
                } else {
                    $error_detected[] = _T("Scheduled payment has not been modified!");
                    //redirect to edition
                    $redirect_uri = $this->routeparser->urlFor('editScheduledPayment', ['id' => (string)$id]);
                }
            } else {
                if ($id === null) {
                    $msg = _T("Scheduled payment has been successfully added.");
                } else {
                    $msg = _T("Scheduled payment has been successfully modified.");
                }
            }
        }

        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        } else {
            $this->flash->addMessage(
                'success_detected',
                $msg
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_uri);
    }


    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('scheduledPayments');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveScheduledPayment',
            $args
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     *
     * @return string
     */
    public function confirmRemoveTitle(array $args): string
    {
        return _Tn('Remove scheduled payment', 'Remove scheduled payments', (count($args['ids'] ?? []) > 1 ? 3 : 1));
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post): bool
    {
        $scheduleds = new ScheduledPayments($this->zdb, $this->login);
        $rm = $scheduleds->remove($args['ids'] ?? $args['id'], $this->history);
        return $rm;
    }

    // CRUD - Delete

    /**
     * Get default filter name
     *
     * @return string
     */
    public static function getDefaultFilterName(): string
    {
        return 'scheduled_payments';
    }
}
