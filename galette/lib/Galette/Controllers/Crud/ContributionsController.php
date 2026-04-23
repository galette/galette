<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

use Galette\Entity\PaymentType;
use Galette\Entity\ScheduledPayment;
use Analog\Analog;
use Galette\Controllers\CrudController;
use Galette\Filters\ContributionsList;
use Galette\Filters\TransactionsList;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\Transaction;
use Galette\Repository\Members;
use Galette\Entity\ContributionsTypes;
use Galette\Repository\PaymentTypes;

/**
 * Galette contributions controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class ContributionsController extends CrudController
{
    // CRUD - Create

    /**
     * Add/Edit page
     *
     * Only a few things change in add and edit pages,
     * both methods will use this common one.
     *
     * @param string       $type    Contribution type
     * @param Contribution $contrib Contribution instance
     */
    public function addEditPage(
        Request $request,
        Response $response,
        string $type,
        Contribution $contrib
    ): Response {
        $post = $request->getParsedBody();

        // check for ajax mode
        $ajax = false;
        if (
            ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest')
            || isset($post['ajax'])
            && $post['ajax'] == 'true'
        ) {
            $ajax = true;
        }

        // contribution types
        $ct = new ContributionsTypes($this->zdb);
        $contributions_types = $ct->getList($type === Contribution::TYPE_FEE);

        // template variable declaration
        $title = $type === Contribution::TYPE_FEE ? _T("Membership fee") : _T("Donation");

        if ($contrib->id) {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
            $type_amount = $contributions_types[array_key_first($contributions_types)]['amount'];
            if ($contrib->amount === null && $type_amount !== null) {
                $contrib->amount = $type_amount;
            }
        }

        $params = [
            'page_title'        => $title,
            'required'          => $contrib->getRequired(),
            'contribution'      => $contrib,
            'adh_selected'      => $contrib->member,
            'type'              => $type,
            'documentation'     => 'usermanual/contributions.html'
        ];

        // contribution types
        $params['type_cotis_options'] = $contributions_types;

        if ($contrib->id) {
            $params['scheduled'] = new ScheduledPayment($this->zdb, $contrib->id);
        }

        // members
        $m = new Members();
        $members = $m->getDropdownMembers(
            $this->zdb,
            $this->login,
            $contrib->member > 0 ? $contrib->member : null
        );

        $params['members'] = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];

        if (count($members)) {
            $params['members']['list'] = $members;
        }

        $ext_membership = '';
        if ($contrib->isFee() || $type === Contribution::TYPE_FEE) {
            $ext_membership = $this->preferences->pref_membership_ext;
        }
        $params['pref_membership_ext'] = $ext_membership;
        $params['autocomplete_options'] = true;
        $params['mode'] = ($ajax ? 'ajax' : '');

        // display page
        $this->view->render(
            $response,
            'pages/contribution_form.html.twig',
            $params
        );
        return $response;
    }

    /**
     * Add page
     *
     * @param string|null $type Contribution type
     */
    public function add(Request $request, Response $response, ?string $type = null): Response
    {
        if ($this->session->contribution !== null) {
            $contrib = $this->session->contribution;
            $this->session->contribution = null;
        } else {
            $get = $request->getQueryParams();

            $ct = new ContributionsTypes($this->zdb);
            $contributions_types = $ct->getList($type === Contribution::TYPE_FEE);
            if (!count($contributions_types)) {
                return $this->redirectWithErrors(
                    response: $response,
                    errors: [_T('No related contribution type available, please create a new one.')],
                    redirect_url: $this->routeparser->urlFor('contributions', ['type' => 'contributions'])
                );
            }

            $cparams = ['type' => array_keys($contributions_types)[0]];

            //member id
            if (isset($get[Adherent::PK]) && $get[Adherent::PK] > 0) {
                $cparams['adh'] = (int)$get[Adherent::PK];
            }

            //transaction id
            if (isset($get[Transaction::PK]) && $get[Transaction::PK] > 0) {
                $cparams['trans'] = $get[Transaction::PK];
            }

            if (isset($get['montant_cotis']) && $get['montant_cotis'] > 0) {
                $cparams['amount'] = (int)$get['montant_cotis'];
            }

            $contrib = new Contribution(
                $this->zdb,
                $this->login,
                $cparams
            );
        }

        if (!$contrib->canCreate($this->login)) {
            Analog::log(
                'Trying to add contribution without appropriate ACLs',
                Analog::WARNING
            );
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('slash')
                );
        }

        return $this->addEditPage($request, $response, $type, $contrib);
    }

    /**
     * Add action
     *
     * @param string|null $type Contribution type
     */
    public function doAdd(Request $request, Response $response, ?string $type = null): Response
    {
        $post = $request->getParsedBody();
        $args = [
            'type' => $post[ContributionsTypes::PK],
            'adh' => $post[Adherent::PK]
        ];
        $contrib = new Contribution($this->zdb, $this->login, $args);

        if (!$contrib->canCreate($this->login)) {
            Analog::log(
                'Trying to add contribution without appropriate ACLs',
                Analog::WARNING
            );
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('slash')
                );
        }

        return $this->store($request, $response, 'add', $type, $contrib);
    }

    /**
     * Choose contribution type to mass add contribution
     */
    public function massAddChooseType(Request $request, Response $response): Response
    {
        $filters = $this->session->{$this->getFilterName('members')};
        $data = [
            'id'            => $filters->selected,
            'redirect_uri'  => $this->routeparser->urlFor('members')
        ];

        // display page
        $this->view->render(
            $response,
            'modals/mass_choose_contributions_type.html.twig',
            [
                'mode'          => ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : '',
                'page_title'   => sprintf(
                    _T('Mass add contribution on %1$s members'),
                    (string)count($data['id'])
                ),
                'data'          => $data,
                'form_url'      => $this->routeparser->urlFor('massAddContributions'),
                'cancel_uri'    => $this->routeparser->urlFor('members')
            ]
        );
        return $response;
    }

    /**
     * Massive change page
     */
    public function massAddContributions(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $filters = $this->session->{$this->getFilterName('members')};
        $type = $post['type'];

        $ct = new ContributionsTypes($this->zdb);
        $contributions_types = $ct->getList($type === Contribution::TYPE_FEE);

        $contribution = new Contribution(
            $this->zdb,
            $this->login,
            ['type' => array_keys($contributions_types)[0]]
        );

        $data = [
            'id'            => $filters->selected,
            'redirect_uri'  => $this->routeparser->urlFor('members'),
            'type'          => $type
        ];

        // display page
        $this->view->render(
            $response,
            'modals/mass_add_contributions.html.twig',
            [
                'mode'          => ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : '',
                'page_title'    => sprintf(
                    _T('Mass add contribution on %1$s members'),
                    (string)count($data['id'])
                ),
                'form_url'      => $this->routeparser->urlFor('doMassAddContributions'),
                'cancel_uri'    => $this->routeparser->urlFor('members'),
                'data'          => $data,
                'contribution'  => $contribution,
                'type'          => $type,
                'require_mass'  => true,
                'required'      => $contribution->getRequired(),
                'type_cotis_options' => $contributions_types
            ]
        );
        return $response;
    }

    /**
     * Do massive contribution add
     */
    public function doMassAddContributions(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $members_ids = $post['id'];
        unset($post['id']);

        $error_detected = [];

        // flagging required fields for first step only
        $disabled = [];

        foreach ($members_ids as $member_id) {
            $post[Adherent::PK] = (int)$member_id;
            $contrib = new Contribution($this->zdb, $this->login);
            $contrib->disableEvents();

            // regular fields
            $valid = $contrib->check($post, $contrib->getRequired(), $disabled);
            if ($valid !== true) {
                $error_detected = array_merge($error_detected, $valid);
            }

            //all goes well, we can proceed
            if (count($error_detected) == 0) {
                $store = $contrib->store();
                if ($store === true) {
                    $files_res = $contrib->handleFiles($request->getUploadedFiles());
                    if (is_array($files_res)) {
                        $error_detected = array_merge($error_detected, $files_res);
                    }
                }
            }
        }

        if (count($error_detected) == 0) {
            $redirect_url = $this->routeparser->urlFor('members');
        } else {
            //something went wrong.
            $redirect_url = $this->routeparser->urlFor('massAddContributions');
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            errors: $error_detected
        );
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param string|null     $option One of 'page', 'order' or 'member'
     * @param int|string|null $value  Value of the option
     * @param ?string         $type   One of 'transactions' or 'contributions'
     */
    public function list(
        Request $request,
        Response $response,
        ?string $option = null,
        int|string|null $value = null,
        ?string $type = null
    ): Response {
        $ajax = false;
        $get = $request->getQueryParams();

        switch ($type) {
            case 'transactions':
                $raw_type = 'transactions';
                $documentation = 'usermanual/contributions.html#transactions';
                break;
            case 'contributions':
            default:
                $raw_type = 'contributions';
                $documentation = 'usermanual/contributions.html';
                break;
        }

        $filter_args = [];
        if (
            ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest')
            || isset($get['ajax'])
            && $get['ajax'] == 'true'
        ) {
            $ajax = true;
            $filter_args['suffix'] = 'ajax';
        }
        $filter_name = $this->getFilterName($raw_type, $filter_args);

        if (isset($this->session->$filter_name)) {
            /** @var ContributionsList|TransactionsList $filters */
            $filters = $this->session->$filter_name;
        } else {
            $filter_class = '\\Galette\\Filters\\' . ucwords($raw_type . 'List');
            /** @var ContributionsList|TransactionsList $filters */
            $filters = new $filter_class();
        }

        //member id
        if (isset($get[Adherent::PK]) && $get[Adherent::PK] > 0) {
            $filters->filtre_cotis_adh = (int)$get[Adherent::PK];
        }

        if ($type === 'contributions' && isset($request->getQueryParams()['max_amount'])) {
            $filters->filtre_transactions = true;
            $filters->max_amount = (int)$request->getQueryParams()['max_amount'];
        }

        if ($type === 'contributions' && isset($get[Transaction::PK])) {
            $filters->from_transaction = (int)$get[Transaction::PK];
        }

        switch ($option) {
            case 'page':
                $filters->current_page = (int)$value;
                break;
            case 'order':
                $filters->orderby = $value;
                break;
            case 'member':
                $filters->filtre_cotis_adh = ($value === 'all' ? null : (int)$value);
                break;
            default:
                break;
        }

        if (!$this->login->isAdmin() && !$this->login->isStaff() && $value != $this->login->id) {
            if ($value === 'all' || empty($value)) {
                $value = $this->login->id;
            } else {
                $member = new Adherent(
                    $this->zdb,
                    (int)$value,
                    [
                        'picture' => false,
                        'groups' => false,
                        'dues' => false,
                        'parent' => true
                    ]
                );
                if (
                    !$member->hasParent()
                    || $member->parent->id != $this->login->id
                ) {
                    Analog::log(
                        'Trying to display ' . $type . ' for member #' . $value
                        . ' without appropriate ACLs',
                        Analog::WARNING
                    );
                    $value = $this->login->id;
                }
            }
            //FIXME: children is set here even for no parent members
            $filters->filtre_cotis_children = (int)$value;
        }

        $class = '\\Galette\\Entity\\' . ucwords(trim($raw_type, 's'));
        $contrib = new $class($this->zdb, $this->login);

        if (!$contrib->canShow($this->login)) {
            Analog::log(
                'Trying to display contributions without appropriate ACLs',
                Analog::WARNING
            );
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('me')
                );
        }

        $class = '\\Galette\\Repository\\' . ucwords($raw_type);
        $contrib = new $class($this->zdb, $this->login, $filters);
        $contribs_list = $contrib->getList(true);

        //store filters into session
        if ($ajax === false) {
            $this->session->$filter_name = $filters;
        }

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view);

        $tpl_vars = [
            'page_title'        => $raw_type === 'contributions'
                                    ? _T("List of contributions") : _T("List of transactions"),
            'contribs'          => $contrib,
            'list'              => $contribs_list,
            'nb'                => $contrib->getCount(),
            'filters'           => $filters,
            'mode'              => ($ajax === true ? 'ajax' : 'std'),
            'documentation'     => $documentation
        ];

        if ($filters->filtre_cotis_adh != null) {
            $member = new Adherent($this->zdb);
            $member->enableDep('children');
            $member->load($filters->filtre_cotis_adh);
            $tpl_vars['member'] = $member;
        }

        if ($filters->filtre_cotis_children !== false) {
            $member = new Adherent(
                $this->zdb,
                $filters->filtre_cotis_children,
                [
                    'picture'   => false,
                    'groups'    => false,
                    'dues'      => false,
                    'parent'    => true
                ]
            );
            $tpl_vars['pmember'] = $member;
        }

        // contribution types
        $ct = new ContributionsTypes($this->zdb);
        $contributions_types = $ct->getList();
        $tpl_vars['type_cotis_options'] = $contributions_types;

        // hide column action in ajax mode
        if ($ajax === true) {
            $tpl_vars['no_action'] = true;
        }

        // display page
        $this->view->render(
            $response,
            'pages/' . $raw_type . '_list.html.twig',
            $tpl_vars
        );
        return $response;
    }

    /**
     * List page for logged-in member
     *
     * @param string|null $type One of 'transactions' or 'contributions'
     */
    public function myList(Request $request, Response $response, ?string $type = null): Response
    {
        return $this->list(
            $request->withQueryParams(
                $request->getQueryParams() + [
                    Adherent::PK => $this->login->id
                ]
            ),
            $response,
            null,
            null,
            $type
        );
    }

    /**
     * Filtering
     *
     * @param string|null $type One of 'transactions' or 'contributions'
     */
    public function filter(Request $request, Response $response, ?string $type = null): Response
    {
        $ajax = false;
        $filter_args = [];
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $ajax = true;
            $filter_args['suffix'] = 'ajax';
        }
        $filter_name = $this->getFilterName($type, $filter_args);

        $post = $request->getParsedBody();
        $error_detected = [];

        if ($this->session->$filter_name !== null) {
            $filters = $this->session->$filter_name;
        } else {
            $filter_class = '\\Galette\\Filters\\' . ucwords((string)$type) . 'List';
            $filters = new $filter_class();
        }

        if (isset($post['clear_filter'])) {
            $filters->reinit($ajax);
        } else {
            if (!isset($post['max_amount'])) {
                $filters->max_amount = null;
            }

            if (isset($post['nbshow']) && is_numeric($post['nbshow'])) {
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
                $ptlist = $ptypes->getList();
                if (isset($ptlist[$ptf])) {
                    $filters->payment_type_filter = $ptf;
                } elseif ($ptf == -1) {
                    $filters->payment_type_filter = null;
                } else {
                    $error_detected[] = _T("- Unknown payment type!");
                }
            }

            if (isset($post['contrib_type_filter'])) {
                $ctf = (int)$post['contrib_type_filter'];
                $ct = new ContributionsTypes($this->zdb);
                $ctlist = $ct->getList();
                if (isset($ctlist[$ctf])) {
                    $filters->contrib_type_filter = $ctf;
                } elseif ($ctf == 0) {
                    $filters->contrib_type_filter = null;
                } else {
                    $error_detected[] = _T("- Unknown contribution type!");
                }
            }
        }

        $this->session->$filter_name = $filters;

        return $this->redirect(
            response: $response,
            redirect_url: $this->routeparser->urlFor('contributions', ['type' => $type]),
            errors: $error_detected
        );
    }

    /**
     * Batch actions handler
     *
     * @param string $type One of 'transactions' or 'contributions'
     */
    public function handleBatch(Request $request, Response $response, string $type): Response
    {
        $filter_name = $this->getFilterName($type);
        $post = $request->getParsedBody();

        if (isset($post['entries_sel'])) {
            $filter_class = '\\Galette\\Filters\\' . ucwords($type . 'List');
            $filters = $this->session->$filter_name ?? new $filter_class();
            $filters->selected = $post['entries_sel'];

            if (isset($post['csv'])) {
                $this->session->{$this->getFilterName($type, ['suffix' => 'csvexport'])} = $filters;
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('csv-contributionslist', ['type' => $type]));
            }

            if (isset($post['delete'])) {
                $this->session->{$this->getFilterName($type, ['suffix' => 'delete'])} = $filters;
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('removeContributions', ['type' => $type]));
            }

            throw new \RuntimeException('Does not know what to batch :(');
        } else {
            return $this->redirectWithErrors(
                response: $response,
                errors: [_T("No contribution was selected, please check at least one.")],
                redirect_url: $this->routeparser->urlFor('contributions', ['type' => $type])
            );
        }
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param ?int        $id   Contribution id
     * @param string|null $type Contribution type
     */
    public function edit(Request $request, Response $response, ?int $id, ?string $type = null): Response
    {
        if ($this->session->contribution !== null) {
            $contrib = $this->session->contribution;
            $this->session->contribution = null;
        } else {
            $contrib = new Contribution($this->zdb, $this->login);
            if (!$contrib->load($id)) {
                //not possible to load contribution, exit
                return $this->redirectWithErrors(
                    response: $response,
                    errors: [
                        str_replace(
                            '%id',
                            (string)$id,
                            _T("Unable to load contribution #%id!")
                        )
                    ],
                    redirect_url: $this->routeparser->urlFor('contributions', ['type' => 'contributions'])
                );
            }
        }

        if (!$contrib->canEdit($this->login)) {
            Analog::log(
                'Trying to edit contribution without appropriate ACLs',
                Analog::WARNING
            );
            return $this->redirect(
                response: $response,
                redirect_url: $this->routeparser->urlFor('slash')
            );
        }

        return $this->addEditPage($request, $response, $type, $contrib);
    }

    /**
     * Edit action
     *
     * @param int         $id   Contribution id
     * @param string|null $type Contribution type
     */
    public function doEdit(Request $request, Response $response, int $id, ?string $type = null): Response
    {
        $contrib = new Contribution($this->zdb, $this->login, $id);
        if (!$contrib->canEdit($this->login)) {
            Analog::log(
                'Trying to edit contribution without appropriate ACLs',
                Analog::WARNING
            );
            return $this->redirect(
                response: $response,
                redirect_url: $this->routeparser->urlFor('slash')
            );
        }

        return $this->store($request, $response, 'edit', $type, $contrib, $id);
    }

    /**
     * Store contribution (new or existing)
     *
     * @param string       $action  Action ('edit' or 'add')
     * @param string       $type    Contribution type
     * @param Contribution $contrib Contribution instance
     * @param ?int         $id      Contribution id
     */
    public function store(Request $request, Response $response, string $action, string $type, Contribution $contrib, ?int $id = null): Response
    {
        $post = $request->getParsedBody();
        $url_args = [
            'action'    => $action,
            'type'      => $type
        ];
        if ($id !== null) {
            $url_args['id'] = (string)$id;
        }

        $disabled = [];

        // regular fields
        $valid = $contrib->check($post, $contrib->getRequired(), $disabled);
        //store entity in session
        $this->session->contribution = $contrib;
        $redirect_url = $this->routeparser->urlFor($action . 'Contribution', $url_args);

        if ($valid !== true) {
            return $this->redirectWithErrors(
                $response,
                $valid,
                $redirect_url
            );
        }

        // send email to member
        if (isset($post['mail_confirm']) && $post['mail_confirm'] == '1') {
            $contrib->setSendmail(); //flag to send creation email
        }

        //all goes well, we can proceed
        $store = $contrib->store();
        if (!$store) {
            //something went wrong :'(
            return $this->redirectWithErrors(
                $response,
                [_T("An error occurred while storing the contribution.")],
                $redirect_url
            );
        }

        $this->session->contribution = null;
        $success_detected = [_T('Contribution has been successfully stored')];
        $error_detected = [];
        $files_res = $contrib->handleFiles($request->getUploadedFiles());
        if (is_array($files_res)) {
            foreach ($files_res as $res) {
                $error_detected[] = $res;
            }
        }

        if ($contrib->isTransactionPart() && $contrib->transaction->getMissingAmount() > 0) {
            //if part of a transaction, and transaction is not fully allocated, create a new contribution
            $redirect_url = $this->routeparser->urlFor(
                'addContribution',
                [
                    'type' => $post['contrib_type'] ?? $type
                ]
            ) . '?' . Transaction::PK . '=' . $contrib->transaction->id
            . '&' . Adherent::PK . '=' . $contrib->member;
        } elseif ($contrib->payment_type === PaymentType::SCHEDULED && !$contrib->isScheduleFullyAllocated()) {
            //if payment type is a payment schedule, and schedule is not fully allocated, create a new schedule entry
            $redirect_url = $this->routeparser->urlFor(
                'addScheduledPayment',
                [
                    Contribution::PK => (string)$contrib->id
                ]
            );
        } elseif ($this->login->isAdmin() || $this->login->isStaff()) {
            //contributions list (for member if admin or staff member)
            $redirect_url = $this->routeparser->urlFor(
                'contributions',
                [
                    'type'      => 'contributions'
                ]
            ) . '?' . Adherent::PK . '=' . $contrib->member;
        } else {
            $redirect_url = $this->routeparser->urlFor('slash');
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            successes: $success_detected,
            errors: $error_detected
        );
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
        return $this->routeparser->urlFor('contributions', ['type' => $args['type']]);
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'doRemoveContribution',
            $args
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function confirmRemoveTitle(array $args): string
    {
        $raw_type = null;

        switch ($args['type']) {
            case 'transactions':
                $raw_type = 'transactions';
                break;
            case 'contributions':
                $raw_type = 'contributions';
                break;
        }

        if (isset($args['ids'])) {
            return sprintf(
                _T('Remove %1$s %2$s'),
                count($args['ids']),
                ($raw_type === 'contributions') ? _T('contributions') : _T('transactions')
            );
        } else {
            return sprintf(
                _T('Remove %1$s #%2$s'),
                ($raw_type === 'contributions') ? _T('contribution') : _T('transaction'),
                $args['id']
            );
        }
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    protected function doDelete(array $args, array $post): bool
    {
        $raw_type = match ($args['type']) {
            'transactions' => 'transactions',
            'contributions' => 'contributions',
            default => throw new \OverflowException('Unknown type ' . $args['type']),
        };

        $class = '\\Galette\Repository\\' . ucwords($raw_type);
        $contribs = new $class($this->zdb, $this->login);
        return $contribs->remove($args['ids'] ?? (int)$args['id'], $this->history);
    }

    // /CRUD - Delete
    // /CRUD
}
