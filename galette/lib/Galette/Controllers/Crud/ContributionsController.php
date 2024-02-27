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

namespace Galette\Controllers\Crud;

use Galette\Features\BatchList;
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
    use BatchList;

    // CRUD - Create

    /**
     * Add/Edit page
     *
     * Only a few things changes in add and edit pages,
     * boths methods will use this common one.
     *
     * @param Request      $request  PSR Request
     * @param Response     $response PSR Response
     * @param string       $type     Contribution type
     * @param Contribution $contrib  Contribution instance
     *
     * @return Response
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
        $title = null;
        if ($type === Contribution::TYPE_FEE) {
            $title = _T("Membership fee");
        } else {
            $title = _T("Donation");
        }

        if ($contrib->id != '') {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        $params = [
            'page_title'        => $title,
            'required'          => $contrib->getRequired(),
            'contribution'      => $contrib,
            'adh_selected'      => $contrib->member,
            'type'              => $type
        ];

        // contribution types
        $params['type_cotis_options'] = $contributions_types;

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
        $params['autocomplete'] = true;
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
     * @param Request     $request  PSR Request
     * @param Response    $response PSR Response
     * @param string|null $type     Contribution type
     *
     * @return Response
     */
    public function add(Request $request, Response $response, string $type = null): Response
    {
        if ($this->session->contribution !== null) {
            $contrib = $this->session->contribution;
            $this->session->contribution = null;
        } else {
            $get = $request->getQueryParams();

            $ct = new ContributionsTypes($this->zdb);
            $contributions_types = $ct->getList($type === Contribution::TYPE_FEE);

            $cparams = ['type' => array_keys($contributions_types)[0]];

            //member id
            if (isset($get[Adherent::PK]) && $get[Adherent::PK] > 0) {
                $cparams['adh'] = (int)$get[Adherent::PK];
            }

            //transaction id
            if (isset($get[Transaction::PK]) && $get[Transaction::PK] > 0) {
                $cparams['trans'] = $get[Transaction::PK];
            }

            $contrib = new Contribution(
                $this->zdb,
                $this->login,
                $cparams
            );

            if (isset($cparams['adh'])) {
                $contrib->member = $cparams['adh'];
            }

            if (isset($get['montant_cotis']) && $get['montant_cotis'] > 0) {
                $contrib->amount = $get['montant_cotis'];
            }
        }

        return $this->addEditPage($request, $response, $type, $contrib);
    }

    /**
     * Add action
     *
     * @param Request     $request  PSR Request
     * @param Response    $response PSR Response
     * @param string|null $type     Contribution type
     *
     * @return Response
     */
    public function doAdd(Request $request, Response $response, string $type = null): Response
    {
        return $this->store($request, $response, 'add', $type);
    }

    /**
     * Choose contribution type to mass add contribution
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function massAddChooseType(Request $request, Response $response): Response
    {
        $filters = $this->session->filter_members;
        $data = [
            'id'            => $filters->selected,
            'redirect_uri'  => $this->routeparser->urlFor('members')
        ];

        // display page
        $this->view->render(
            $response,
            'modals/mass_choose_contributions_type.html.twig',
            array(
                'mode'          => ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : '',
                'page_title'    => str_replace(
                    '%count',
                    (string)count($data['id']),
                    _T('Mass add contribution on %count members')
                ),
                'data'          => $data,
                'form_url'      => $this->routeparser->urlFor('massAddContributions'),
                'cancel_uri'    => $this->routeparser->urlFor('members')
            )
        );
        return $response;
    }

    /**
     * Massive change page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function massAddContributions(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $filters = $this->session->filter_members;
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
            array(
                'mode'          => ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') ? 'ajax' : '',
                'page_title'    => str_replace(
                    '%count',
                    (string)count($data['id']),
                    _T('Mass add contribution on %count members')
                ),
                'form_url'      => $this->routeparser->urlFor('doMassAddContributions'),
                'cancel_uri'    => $this->routeparser->urlFor('members'),
                'data'          => $data,
                'contribution'  => $contribution,
                'type'          => $type,
                'require_mass'  => true,
                'required'      => $contribution->getRequired(),
                'type_cotis_options' => $contributions_types
            )
        );
        return $response;
    }

    /**
     * Do massive contribution add
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function doMassAddContributions(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $members_ids = $post['id'];
        unset($post['id']);

        $error_detected = [];

        // flagging required fields for first step only
        $disabled = [];
        $success = 0;
        $errors = 0;

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
                    ++$success;
                    $files_res = $contrib->handleFiles($_FILES);
                    if (is_array($files_res)) {
                        $error_detected = array_merge($error_detected, $files_res);
                    }
                } else {
                    ++$errors;
                }
            }
        }

        if (count($error_detected) == 0) {
            $redirect_url = $this->routeparser->urlFor('members');
        } else {
            //something went wrong.
            //store entity in session
            $redirect_url = $this->routeparser->urlFor('massAddContributions');
            //report errors
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        //redirect to calling action
        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
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
     * @param ?string             $type     One of 'transactions' or 'contributions'
     *
     * @return Response
     */
    public function list(
        Request $request,
        Response $response,
        string $option = null,
        int|string $value = null,
        string $type = null
    ): Response {
        $ajax = false;
        $get = $request->getQueryParams();

        switch ($type) {
            case 'transactions':
                $raw_type = 'transactions';
                break;
            case 'contributions':
                $raw_type = 'contributions';
                break;
            default:
                Analog::log(
                    'Trying to load unknown contribution type ' . $type,
                    Analog::WARNING
                );
                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->routeparser->urlFor('me')
                    );
        }

        $filter_name = 'filter_' . $raw_type;
        if (
            ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest')
            || isset($get['ajax'])
            && $get['ajax'] == 'true'
        ) {
            $ajax = true;
            $filter_name .= '_ajax';
        }

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

        if ($type === 'contributions') {
            if (isset($request->getQueryParams()['max_amount'])) {
                $filters->filtre_transactions = true;
                $filters->max_amount = (int)$request->getQueryParams()['max_amount'];
            }
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
                case 'member':
                    $filters->filtre_cotis_adh = ($value === 'all' ? null : $value);
                    break;
            }
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
                    !$member->hasParent() ||
                    $member->parent->id != $this->login->id
                ) {
                    $value = $this->login->id;
                    Analog::log(
                        'Trying to display contributions for member #' . $value .
                        ' without appropriate ACLs',
                        Analog::WARNING
                    );
                }
            }
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
            'page_title'        => $raw_type === 'contributions' ?
                                    _T("Contributions management") : _T("Transactions management"),
            'contribs'          => $contrib,
            'list'              => $contribs_list,
            'nb'                => $contrib->getCount(),
            'filters'           => $filters,
            'mode'              => ($ajax === true ? 'ajax' : 'std')
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
     * @param Request     $request  PSR Request
     * @param Response    $response PSR Response
     * @param string|null $type     One of 'transactions' or 'contributions'
     *
     * @return Response
     */
    public function myList(Request $request, Response $response, string $type = null): Response
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
     * @param Request     $request  PSR Request
     * @param Response    $response PSR Response
     * @param string|null $type     One of 'transactions' or 'contributions'
     *
     * @return Response
     */
    public function filter(Request $request, Response $response, string $type = null): Response
    {
        $ajax = false;
        $filter_name = 'filter_' . $type;
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $ajax = true;
            $filter_name .= '_ajax';
        }

        $post = $request->getParsedBody();
        $error_detected = [];

        if ($this->session->$filter_name !== null) {
            $filters = $this->session->$filter_name;
        } else {
            $filter_class = '\\Galette\\Filters\\' . ucwords($type) . 'List';
            $filters = new $filter_class();
        }

        if (isset($post['clear_filter'])) {
            $filters->reinit($ajax);
        } else {
            if (!isset($post['max_amount'])) {
                $filters->max_amount = null;
            }

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
                $ptlist = $ptypes->getList();
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
            ->withHeader('Location', $this->routeparser->urlFor('contributions', ['type' => $type]));
    }

    /**
     * Batch actions handler
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $type     One of 'transactions' or 'contributions'
     *
     * @return Response
     */
    public function handleBatch(Request $request, Response $response, string $type): Response
    {
        $filter_name = 'filter_' . $type;
        $post = $request->getParsedBody();

        if (isset($post['entries_sel'])) {
            $filter_class = '\\Galette\\Filters\\' . ucwords($type . 'List');
            $filters = $this->session->$filter_name ?? new $filter_class();
            $filters->selected = $post['entries_sel'];
            $this->session->$filter_name = $filters;

            if (isset($post['csv'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('csv-contributionslist', ['type' => $type]));
            }

            if (isset($post['delete'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('removeContributions', ['type' => $type]));
            }

            throw new \RuntimeException('Does not know what to batch :(');
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("No contribution was selected, please check at least one.")
            );

            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor('contributions', ['type' => $type]));
        }
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request     $request  PSR Request
     * @param Response    $response PSR Response
     * @param int         $id       Contribution id
     * @param string|null $type     Contribution type
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, int $id, string $type = null): Response
    {
        if ($this->session->contribution !== null) {
            $contrib = $this->session->contribution;
            $this->session->contribution = null;
        } else {
            $contrib = new Contribution($this->zdb, $this->login, $id);
            if ($contrib->id == '') {
                //not possible to load contribution, exit
                $this->flash->addMessage(
                    'error_detected',
                    str_replace(
                        '%id',
                        (string)$id,
                        _T("Unable to load contribution #%id!")
                    )
                );
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor(
                        'contributions',
                        ['type' => 'contributions']
                    ));
            }
        }

        return $this->addEditPage($request, $response, $type, $contrib);
    }

    /**
     * Edit action
     *
     * @param Request     $request  PSR Request
     * @param Response    $response PSR Response
     * @param integer     $id       Contribution id
     * @param string|null $type     Contribution type
     *
     * @return Response
     */
    public function doEdit(Request $request, Response $response, int $id, string $type = null): Response
    {
        return $this->store($request, $response, 'edit', $type, $id);
    }

    /**
     * Store contribution (new or existing)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $action   Action ('edit' or 'add')
     * @param string   $type     Contribution type
     * @param ?integer $id       Contribution id
     *
     * @return Response
     */
    public function store(Request $request, Response $response, string $action, string $type, int $id = null): Response
    {
        $post = $request->getParsedBody();
        $url_args = [
            'action'    => $action,
            'type'      => $type
        ];
        if ($id !== null) {
            $url_args['id'] = $id;
        }

        if ($action == 'edit' && isset($post['btnreload'])) {
            $redirect_url = $this->routeparser->urlFor($action . 'Contribution', $url_args);
            $redirect_url .= '?' . Adherent::PK . '=' . $post[Adherent::PK] . '&' .
                ContributionsTypes::PK . '=' . $post[ContributionsTypes::PK] . '&' .
                'montant_cotis=' . $post['montant_cotis'];
            return $response
                ->withStatus(301)
                ->withHeader('Location', $redirect_url);
        }

        $error_detected = [];

        if ($this->session->contribution !== null) {
            $contrib = $this->session->contribution;
            $this->session->contribution = null;
        } else {
            if ($id === null) {
                $args = [
                    'type' => $post[ContributionsTypes::PK],
                    'adh' => $post[Adherent::PK]
                ];
                $contrib = new Contribution($this->zdb, $this->login, $args);
            } else {
                $contrib = new Contribution($this->zdb, $this->login, $id);
            }
        }

        $disabled = [];

        // regular fields
        $valid = $contrib->check($post, $contrib->getRequired(), $disabled);
        if ($valid !== true) {
            $error_detected = array_merge($error_detected, $valid);
        }

        // send email to member
        if (isset($post['mail_confirm']) && $post['mail_confirm'] == '1') {
            $contrib->setSendmail(); //flag to send creation email
        }

        //all goes well, we can proceed
        if (count($error_detected) == 0) {
            $store = $contrib->store();
            if ($store === true) {
                $this->flash->addMessage(
                    'success_detected',
                    _T('Contribution has been successfully stored')
                );
            } else {
                //something went wrong :'(
                $error_detected[] = _T("An error occurred while storing the contribution.");
            }
        }

        if (count($error_detected) === 0) {
            $files_res = $contrib->handleFiles($_FILES);
            if (is_array($files_res)) {
                $error_detected = array_merge($error_detected, $files_res);
            }
        }

        if (count($error_detected) == 0) {
            $this->session->contribution = null;
            if ($contrib->isTransactionPart() && $contrib->transaction->getMissingAmount() > 0) {
                //new contribution
                $redirect_url = $this->routeparser->urlFor(
                    'addContribution',
                    [
                        'type'      => $post['contrib_type'] ?? $type
                    ]
                ) . '?' . Transaction::PK . '=' . $contrib->transaction->id .
                '&' . Adherent::PK . '=' . $contrib->member;
            } else {
                //contributions list for member
                $redirect_url = $this->routeparser->urlFor(
                    'contributions',
                    [
                        'type'      => 'contributions'
                    ]
                ) . '?' . Adherent::PK . '=' . $contrib->member;
            }
        } else {
            //something went wrong.
            //store entity in session
            $this->session->contribution = $contrib;
            $redirect_url = $this->routeparser->urlFor($action . 'Contribution', $url_args);

            //report errors
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        //redirect to calling action
        return $response
            ->withStatus(301)
            ->withHeader('Location', $redirect_url);
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
        return $this->routeparser->urlFor('contributions', ['type' => $args['type']]);
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
            'doRemoveContribution',
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
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post): bool
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

        $class = '\\Galette\Repository\\' . ucwords($raw_type);
        $contribs = new $class($this->zdb, $this->login);
        $rm = $contribs->remove($args['ids'] ?? $args['id'], $this->history);
        return $rm;
    }

    // /CRUD - Delete
    // /CRUD

    /**
     * Get filter name in session
     *
     * @param array<string,mixed>|null $args Route arguments
     *
     * @return string
     */
    public function getFilterName(array $args = null): string
    {
        return 'filter_' . $args['type'];
    }
}
