<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette contributions controller
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
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-08
 */

namespace Galette\Controllers\Crud;

use Galette\Controllers\CrudController;

use Slim\Http\Request;
use Slim\Http\Response;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\Transaction;



use Galette\Repository\Contributions;
use Galette\Repository\Transactions;
use Galette\Repository\Members;
use Galette\Entity\ContributionsTypes;
use Galette\Core\GaletteMail;
use Galette\Entity\Texts;
use Galette\IO\PdfMembersCards;
use Galette\Repository\PaymentTypes;
use Galette\Core\Links;



use Analog\Analog;

/**
 * Galette contributions controller
 *
 * @category  Controllers
 * @name      ContributionsController
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.4dev - 2020-05-02
 */

class ContributionsController extends CrudController
{
    // CRUD - Create

    /**
     * Add/Edit page
     *
     * Only a few things changes in add and edit pages,
     * boths methods will use this common one.
     *
     * @param Request      $request  PSR Request
     * @param Response     $response PSR Response
     * @param array        $args     Request arguments
     * @param Contribution $contrib  Contribution instance
     *
     * @return Response
     */
    public function addEditPage(
        Request $request,
        Response $response,
        array $args,
        Contribution $contrib
    ) :Response {
        $get = $request->getQueryParams();

        // contribution types
        $ct = new ContributionsTypes($this->zdb);
        $contributions_types = $ct->getList($args['type'] === 'fee');

        $disabled = array();

        if (!is_int($contrib->id)) {
            // initialiser la structure contribution à vide (nouvelle contribution)
            $contribution['duree_mois_cotis'] = $this->preferences->pref_membership_ext;
        }

        // template variable declaration
        $title = null;
        if ($args['type'] === 'fee') {
            $title = _T("Membership fee");
        } else {
            $title = _T("Donation");
        }

        if ($contrib->id != '') {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        // required fields
        $required = [
            'id_type_cotis'     => 1,
            'id_adh'            => 1,
            'date_enreg'        => 1,
            'date_debut_cotis'  => 1,
            'date_fin_cotis'    => $contrib->isCotis(),
            'montant_cotis'     => $contrib->isCotis() ? 1 : 0
        ];

        $params = [
            'page_title'        => $title,
            'required'          => $required,
            'disabled'          => $disabled,
            'contribution'      => $contrib,
            'adh_selected'      => $contrib->member,
            'type'              => $args['type']
        ];

        // contribution types
        $params['type_cotis_options'] = $contributions_types;

        // members
        $m = new Members();
        $members = $m->getSelectizedMembers(
            $this->zdb,
            isset($contrib) && $contrib->member > 0 ? $contrib->member : null
        );

        $params['members'] = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];

        if (count($members)) {
            $params['members']['list'] = $members;
        }

        $ext_membership = '';
        if (isset($contrib) && $contrib->isCotis() || !isset($contrib) && $args['type'] === 'fee') {
            $ext_membership = $this->preferences->pref_membership_ext;
        }
        $params['pref_membership_ext'] = $ext_membership;
        $params['autocomplete'] = true;

        // display page
        $this->view->render(
            $response,
            'ajouter_contribution.tpl',
            $params
        );
        return $response;
    }

    /**
     * Add page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function add(Request $request, Response $response, array $args = []) :Response
    {
        if ($this->session->contribution !== null) {
            $contrib = $this->session->contribution;
            $this->session->contribution = null;
        } else {
            $ct = new ContributionsTypes($this->zdb);
            $contributions_types = $ct->getList($args['type'] === 'fee');

            $cparams = ['type' => array_keys($contributions_types)[0]];

            //member id
            if (isset($get[Adherent::PK]) && $get[Adherent::PK] > 0) {
                $cparams['adh'] = (int)$get[Adherent::PK];
            }

            //transaction id
            $trans_id = null;
            if (isset($get[Transaction::PK]) && $get[Transaction::PK] > 0) {
                $cparams['trans'] = $get[Transaction::PK];
            }

            $contrib = new Contribution(
                $this->zdb,
                $this->login,
                (count($cparams) > 0 ? $cparams : null)
            );

            if (isset($get['montant_cotis']) && $get['montant_cotis'] > 0) {
                $contrib->amount = $get['montant_cotis'];
            }
        }

        return $this->addEditPage($request, $response, $args, $contrib);
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
    public function doAdd(Request $request, Response $response, array $args = []) :Response
    {
        $args['action'] = 'add';
        return $this->store($request, $response, $args);
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function list(Request $request, Response $response, array $args = []) :Response
    {
        $ajax = false;
        if ($request->isXhr()
            || isset($request->getQueryParams()['ajax'])
            && $request->getQueryParams()['ajax'] == 'true'
        ) {
            $ajax = true;
        }
        $get = $request->getQueryParams();

        $option = $args['option'] ?? null;
        $value = $args['value'] ?? null;
        $raw_type = null;

        switch ($args['type']) {
            case 'transactions':
                $raw_type = 'transactions';
                break;
            case 'contributions':
                $raw_type = 'contributions';
                break;
        }

        $filter_name = 'filter_' . $raw_type;

        if (isset($this->session->$filter_name) && $ajax === false) {
            $filters = $this->session->$filter_name;
        } else {
            $filter_class = '\\Galette\\Filters\\' . ucwords($raw_type . 'List');
            $filters = new $filter_class();
        }

        //member id
        if (isset($get[Adherent::PK]) && $get[Adherent::PK] > 0) {
            $filters->filtre_cotis_adh = (int)$get[Adherent::PK];
        }

        $max_amount = null;
        if (isset($request->getQueryParams()['max_amount'])) {
            $filters->filtre_transactions = true;
            $filters->max_amount = (int)$request->getQueryParams()['max_amount'];
        } else {
            $filters->filtre_transactions = false;
            $filters->max_amount = null;
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
                    if (($this->login->isAdmin()
                        || $this->login->isStaff())
                    ) {
                        if ($value == 'all') {
                            $filters->filtre_cotis_adh = null;
                        } else {
                            $filters->filtre_cotis_adh = $value;
                        }
                    }
                    break;
            }
        }

        if (!$this->login->isAdmin() && !$this->login->isStaff()) {
            $filters->filtre_cotis_adh = $this->login->id;
        }

        $class = '\\Galette\\Repository\\' . ucwords($raw_type);
        $contrib = new $class($this->zdb, $this->login, $filters);
        $contribs_list = $contrib->getList(true);

        //store filters into session
        if ($ajax === false) {
            $this->session->$filter_name = $filters;
        }

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($this->router, $this->view->getSmarty());

        $tpl_vars = [
            'page_title'        => $raw_type === 'contributions' ?
                                    _T("Contributions management") :
                                    _T("Transactions management"),
            'contribs'          => $contrib,
            'list'              => $contribs_list,
            'nb'                => $contrib->getCount(),
            'filters'           => $filters,
            'mode'              => ($ajax === true ? 'ajax' : 'std')
        ];

        if ($filters->filtre_cotis_adh != null) {
            $member = new Adherent($this->zdb);
            $member->load($filters->filtre_cotis_adh);
            $tpl_vars['member'] = $member;
        }

        // display page
        $this->view->render(
            $response,
            'gestion_' . $raw_type . '.tpl',
            $tpl_vars
        );
        return $response;
    }

    /**
     * Filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function filter(Request $request, Response $response, array $args = []) :Response
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

        $type = 'filter_' . $raw_type;
        $post = $request->getParsedBody();
        $error_detected = [];

        if ($this->session->$type !== null) {
            $filters = $this->session->$type;
        } else {
            $filter_class = '\\Galette\\Filters\\' . ucwords($raw_type) . 'List';
            $filters = new $filter_class();
        }

        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            if (isset($post['max_amount'])) {
                $filters->max_amount = null;
            }

            if ((isset($post['nbshow']) && is_numeric($post['nbshow']))
            ) {
                $filters->show = $post['nbshow'];
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
                } catch (Exception $e) {
                    $error_detected[] = $e->getMessage();
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

        $this->session->$type = $filters;

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
            ->withHeader('Location', $this->router->pathFor('contributions', ['type' => $raw_type]));
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function edit(Request $request, Response $response, array $args = []) :Response
    {
        if ($this->session->contribution !== null) {
            $contrib = $this->session->contribution;
            $this->session->contribution = null;
        } else {
            $contrib = new Contribution($this->zdb, $this->login, (int)$args['id']);
            if ($contrib->id == '') {
                //not possible to load contribution, exit
                $this->flash->addMessage(
                    'error_detected',
                    str_replace(
                        '%id',
                        $args['id'],
                        _T("Unable to load contribution #%id!")
                    )
                );
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->router->pathFor(
                        'contributions',
                        ['type' => 'contributions']
                    ));
            }
        }

        return $this->addEditPage($request, $response, $args, $contrib);
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
    public function doEdit(Request $request, Response $response, array $args = []) :Response
    {
        $args['action'] = 'edit';
        return $this->store($request, $response, $args);
    }

    /**
     * Store contribution (new or existing)
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param array    $args     Request arguments
     *
     * @return Response
     */
    public function store(Request $request, Response $response, array $args = []) :Response
    {
        $post = $request->getParsedBody();
        $action = $args['action'];

        if ($action == 'edit' && isset($post['btnreload'])) {
            $redirect_url = $this->router->pathFor($action . 'Contribution', $args);
            $redirect_url .= '?' . Adherent::PK . '=' . $post[Adherent::PK] . '&' .
                ContributionsTypes::PK . '=' . $post[ContributionsTypes::PK] . '&' .
                'montant_cotis=' . $post['montant_cotis'];
            return $response
                ->withStatus(301)
                ->withHeader('Location', $redirect_url);
        }

        $success_detected = [];
        $error_detected = [];
        $warning_detected = [];
        $redirect_url = null;

        $id_cotis = null;
        if (isset($args['id'])) {
            $id_cotis = $args['id'];
        }

        $id_adh = $post['id_adh'];

        if ($this->session->contribution !== null) {
            $contrib = $this->session->contribution;
            $this->session->contribution = null;
        } else {
            if ($id_cotis === null) {
                $contrib = new Contribution($this->zdb, $this->login);
            } else {
                $contrib = new Contribution($this->zdb, $this->login, (int)$id_cotis);
            }
        }

        // flagging required fields for first step only
        $required = [
            'id_type_cotis'     => 1,
            'id_adh'            => 1,
            'date_enreg'        => 1,
            'montant_cotis'     => 1, //TODO: not always required, see #196
            'date_debut_cotis'  => 1,
            'date_fin_cotis'    => ($args['type'] === 'fee')
        ];
        $disabled = [];

        // regular fields
        $valid = $contrib->check($post, $required, $disabled);
        if ($valid !== true) {
            $error_detected = array_merge($error_detected, $valid);
        }

        if (count($error_detected) == 0) {
            //all goes well, we can proceed
            $new = false;
            if ($contrib->id == '') {
                $new = true;
            }

            if (count($error_detected) == 0) {
                $store = $contrib->store();
                if ($store === true) {
                    $success_detected[] = _T('Contribution has been successfully stored');
                    //contribution has been stored :)
                    if ($new) {
                        //if an external script has been configured, we call it
                        if ($this->preferences->pref_new_contrib_script) {
                            $es = new \Galette\IO\ExternalScript($this->preferences);
                            $res = $contrib->executePostScript($es);

                            if ($res !== true) {
                                //send admin an email with all details
                                if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED) {
                                    $mail = new GaletteMail($this->preferences);
                                    $mail->setSubject(
                                        _T("Post contribution script failed")
                                    );

                                    $recipients = [];
                                    foreach ($this->preferences->vpref_email_newadh as $pref_email) {
                                        $recipients[$pref_email] = $pref_email;
                                    }
                                    $mail->setRecipients($recipients);

                                    $message = _T("The configured post contribution script has failed.");
                                    $message .= "\n" . _T("You can find contribution information and script output below.");
                                    $message .= "\n\n";
                                    $message .= $res;

                                    $mail->setMessage($message);
                                    $sent = $mail->send();

                                    if (!$sent) {
                                        $txt = preg_replace(
                                            array('/%name/', '/%email/'),
                                            array($adh->sname, $adh->getEmail()),
                                            _T("A problem happened while sending to admin post contribution notification for user %name (%email) contribution")
                                        );
                                        $this->history->add($txt);
                                        $warning_detected[] = $txt;
                                        //Mails are disabled... We log (not safe, but)...
                                        Analog::log(
                                            'Email to admin has not been sent. Here was the data: ' .
                                            "\n" . print_r($res, true),
                                            Analog::ERROR
                                        );
                                    }
                                } else {
                                    //Mails are disabled... We log (not safe, but)...
                                    Analog::log(
                                        'Post contribution script has failed. Here was the data: ' .
                                        "\n" . print_r($res, true),
                                        Analog::ERROR
                                    );
                                }
                            }
                        }
                    }
                } else {
                    //something went wrong :'(
                    $error_detected[] = _T("An error occurred while storing the contribution.");
                }
            }
        }

        if (count($error_detected) == 0) {
            // Get member information
            $adh = new Adherent($this->zdb);
            $adh->load($contrib->member);

            if ($this->preferences->pref_mail_method > GaletteMail::METHOD_DISABLED) {
                $texts = new Texts(
                    $this->preferences,
                    $this->router,
                    array(
                        'name_adh'          => custom_html_entity_decode($adh->sname),
                        'firstname_adh'     => custom_html_entity_decode($adh->surname),
                        'lastname_adh'      => custom_html_entity_decode($adh->name),
                        'mail_adh'          => custom_html_entity_decode($adh->getEmail()),
                        'login_adh'         => custom_html_entity_decode($adh->login),
                        'deadline'          => custom_html_entity_decode($contrib->end_date),
                        'contrib_info'      => custom_html_entity_decode($contrib->info),
                        'contrib_amount'    => custom_html_entity_decode($contrib->amount),
                        'contrib_type'      => custom_html_entity_decode($contrib->type->libelle)
                    )
                );
                if ($new && isset($_POST['mail_confirm'])
                    && $_POST['mail_confirm'] == '1'
                ) {
                    if (GaletteMail::isValidEmail($adh->getEmail())) {
                        $text = 'contrib';
                        if (!$contrib->isCotis()) {
                            $text = 'donation';
                        }
                        $mtxt = $texts->getTexts($text, $adh->language);

                        $mail = new GaletteMail($this->preferences);
                        $mail->setSubject($texts->getSubject());
                        $mail->setRecipients(
                            array(
                                $adh->getEmail() => $adh->sname
                            )
                        );

                        $link_card = '';
                        if (strpos($mtxt->tbody, '{LINK_MEMBERCARD}') !== false) {
                            //member card link is present in mail model, let's add it
                            $links = new Links($this->zdb);
                            if ($hash = $links->generateNewLink(Links::TARGET_MEMBERCARD, $contrib->member)) {
                                $link_card =  $this->preferences->getURL() .
                                    $this->router->pathFor('directlink', ['hash' => $hash]);
                            }
                        }

                        $link_pdf = '';
                        if (strpos($mtxt->tbody, '{LINK_MEMBERCARD}') !== false) {
                            //contribution receipt link is present in mail model, let's add it
                            $links = new Links($this->zdb);
                            $ltype = $contrib->type->isExtension() ? Links::TARGET_INVOICE : Links::TARGET_RECEIPT;
                            if ($hash = $links->generateNewLink($ltype, $contrib->id)) {
                                $link_pdf =  $this->preferences->getURL() .
                                    $this->router->pathFor('directlink', ['hash' => $hash]);
                            }
                        }

                        //set replacements, even if empty, to be sure.
                        $texts->setReplaces([
                            'link_membercard'   => $link_card,
                            'link_contribpdf'   => $link_pdf
                        ]);

                        $mail->setMessage($texts->getBody());
                        $sent = $mail->send();

                        if ($sent) {
                            $this->history->add(
                                preg_replace(
                                    array('/%name/', '/%email/'),
                                    array($adh->sname, $adh->getEmail()),
                                    _T("Email sent to user %name (%email)")
                                )
                            );
                        } else {
                            $txt = preg_replace(
                                array('/%name/', '/%email/'),
                                array($adh->sname, $adh->getEmail()),
                                _T("A problem happened while sending contribution receipt to user %name (%email)")
                            );
                            $this->history->add($txt);
                            $error_detected[] = $txt;
                        }
                    } else {
                        $txt = preg_replace(
                            array('/%name/', '/%email/'),
                            array($adh->sname, $adh->getEmail()),
                            _T("Trying to send an email to a member (%name) with an invalid address: %email")
                        );
                        $this->history->add($txt);
                        $warning_detected[] = $txt;
                    }
                }

                // Sent email to admin if pref checked
                if ($new && $this->preferences->pref_bool_mailadh) {
                    // Get email text in database
                    $text = 'newcont';
                    if (!$contrib->isCotis()) {
                        $text = 'newdonation';
                    }
                    $mtxt = $texts->getTexts($text, $this->preferences->pref_lang);

                    $mail = new GaletteMail($this->preferences);
                    $mail->setSubject($texts->getSubject());

                    $recipients = [];
                    foreach ($this->preferences->vpref_email_newadh as $pref_email) {
                        $recipients[$pref_email] = $pref_email;
                    }
                    $mail->setRecipients($recipients);

                    $mail->setMessage($texts->getBody());
                    $sent = $mail->send();

                    if ($sent) {
                        $this->history->add(
                            preg_replace(
                                array('/%name/', '/%email/'),
                                array($adh->sname, $adh->getEmail()),
                                _T("Email sent to admin for user %name (%email)")
                            )
                        );
                    } else {
                        $txt = preg_replace(
                            array('/%name/', '/%email/'),
                            array($adh->sname, $adh->getEmail()),
                            _T("A problem happened while sending to admin notification for user %name (%email) contribution")
                        );
                        $this->history->add($txt);
                        $warning_detected[] = $txt;
                    }
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


            if (count($warning_detected) > 0) {
                foreach ($warning_detected as $warning) {
                    $this->flash->addMessage(
                        'warning_detected',
                        $warning
                    );
                }
            }

            if (count($error_detected) == 0) {
                if ($contrib->isTransactionPart() && $contrib->transaction->getMissingAmount() > 0) {
                    //new contribution
                    $redirect_url = $this->router->pathFor(
                        'addContribution',
                        [
                            'type'      => $post['contrib_type']
                        ]
                    ) . '?' . Transaction::PK . '=' . $contrib->transaction->id .
                    '&' . Adherent::PK . '=' . $contrib->member;
                } else {
                    //contributions list for member
                    $redirect_url = $this->router->pathFor(
                        'contributions',
                        [
                            'type'      => 'contributions'
                        ]
                    ) . '?' . Adherent::PK . '=' . $contrib->member;
                }
            }
        }

        /* TODO: remove */
        if (!isset($contribution['duree_mois_cotis'])
            || $contribution['duree_mois_cotis'] == ''
        ) {
            // On error restore entered value or default to display the form again
            if (isset($_POST['duree_mois_cotis'])
                && $_POST['duree_mois_cotis'] != ''
            ) {
                $contribution['duree_mois_cotis'] = $_POST['duree_mois_cotis'];
            } else {
                $contribution['duree_mois_cotis'] = $this->preferences->pref_membership_ext;
            }
        }

        if (count($error_detected) > 0) {
            //something went wrong.
            //store entity in session
            $this->session->contribution = $contrib;
            $redirect_url = $this->router->pathFor($args['action'] . 'Contribution', $args);

            //report errors
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        } else {
            $this->session->contribution = null;
            if ($redirect_url === null) {
                $redirect_url = $this->router->pathFor('contributions', ['type' => $args['type']]);
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
     * @param array $args Route arguments
     *
     * @return string
     */
    public function redirectUri(array $args = [])
    {
        return $this->router->pathFor('contributions', ['type' => $args['type']]);
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
            'doRemoveContribution',
            $args
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
     * @param array $args Route arguments
     * @param array $post POST values
     *
     * @return boolean
     */
    protected function doDelete(array $args, array $post)
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
}
