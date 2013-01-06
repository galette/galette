<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's instanciation and routes
 *
 * PHP version 5
 *
 * Copyright © 2012 The Galette Team
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
 * @category  Main
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.7.3dev 2012-11-13
 */

use Galette\Core\Picture as Picture;
use Galette\Repository\Members as Members;
use Galette\Entity\Adherent as Adherent;
use Galette\Entity\Required as Required;
use Galette\Entity\DynamicFields as DynamicFields;
use Galette\Entity\FieldsConfig as FieldsConfig;
use Galette\Filters\MembersList as MembersList;
use Galette\Repository\Groups as Groups;
use \Slim\Extras\Views\Smarty as SmartyView;

$time_start = microtime(true);

//define galette's root directory
if ( !defined('GALETTE_ROOT') ) {
    define('GALETTE_ROOT', __DIR__ . '/../');
}

// define relative base path templating can use
if ( !defined('GALETTE_BASE_PATH') ) {
    define('GALETTE_BASE_PATH', '../');
}

/** @ignore */
require_once GALETTE_ROOT . 'includes/galette.inc.php';

$app = new \Slim\Slim(
    array(
        'view' => new \Slim\Extras\Views\Smarty()/*,
        'log.enable' => true,
        'log.level' => \Slim\Log::DEBUG,
        'debug' => true*/
    )
);

$authenticate = function ($app) use (&$session) {
    return function () use ($app, &$session) {
        if ( isset($session['login']) ) {
            $login = unserialize($session['login']);
        } else {
            $login = new Galette\Core\Login();
        }
        if ( !$login->isLogged() ) {
            $session['urlRedirect'] = $app->request()->getPathInfo();
            $app->flash('error', _T("Login required"));
            $app->redirect($app->urlFor('slash'));
        }
    };
};

$baseRedirect = function ($app) use ($login, &$session) {
    $urlRedirect = null;
    if (isset($session['urlRedirect'])) {
        $urlRedirect = $app->request()->getRootUri() . $session['urlRedirect'];
        unset($session['urlRedirect']);
    }

    /*echo 'logged? ' . $login->isLogged();
    var_dump($login);*/
    if ( $login->isLogged() ) {
        if ( $urlRedirect !== null ) {
            $app->redirect($urlRedirect);
        } else {
            if ( $login->isSuperAdmin()
                || $login->isAdmin()
                || $login->isStaff()
            ) {
                if ( !isset($_COOKIE['show_galette_dashboard'])
                    || $_COOKIE['show_galette_dashboard'] == 1
                ) {
                    $app->redirect($app->urlFor('dashboard'));
                } else {
                    $app->redirect($app->urlFor('members'));
                }
            } else {
                $app->redirect($app->urlFor('me'));
            }
        }
    } else {
        $app->redirect($app->urlFor('login'));
    }
};

$app->hook(
    'slim.before.dispatch',
    function () use ($app, $error_detected, $warning_detected, $success_detected) {
        $curUri = str_replace(
            'index.php',
            '',
            $app->request()->getRootUri()
        );

        //add ending / if missing
        if ( $curUri === ''
            || $curUri !== '/'
            && substr($curUri, -1) !== '/'
        ) {
            $curUri .= '/';
        }

        $v = $app->view();
        $v->setData('galette_base_path', $curUri);
        $v->setData('cur_path', $app->request()->getPathInfo());
        $v->setData('require_tabs', null);
        $v->setData('require_cookie', null);
        $v->setData('contentcls', null);
        $v->setData('require_tabs', null);
        $v->setData('require_cookie', false);
        $v->setData('additionnal_html_class', null);
        $v->setData('require_calendar', null);
        $v->setData('head_redirect', null);
        $v->setData('error_detected', null);
        $v->setData('warning_detected', null);
        $v->setData('success_detected', null);
        $v->setData('color_picker', null);
        $v->setData('require_sorter', null);
        $v->setData('require_dialog', null);
        $v->setData('require_tree', null);
        $v->setData('existing_mailing', null);
        $v->setData('html_editor', null);

        if ( isset($error_detected) ) {
            $v->setData('error_detected', $error_detected);
        }
        if (isset($warning_detected)) {
            $v->setData('warning_detected', $warning_detected);
        }

    }
);

$app->get(
    '/',
    function () use ($app, $baseRedirect) {
        $baseRedirect($app);
    }
)->name('slash');

//routes for anonymous users
$app->get(
    '/login',
    function () use ($app, $login, $baseRedirect, &$session) {
        //store redirect path if any
        if ($app->request()->get('r')
            && $app->request()->get('r') != '/logout'
            && $app->request()->get('r') != '/login'
        ) {
            $session['urlRedirect'] = $app->request()->get('r');
        }

        if ( !$login->isLogged() ) {
            // display page
            $app->render(
                'index.tpl',
                array(
                    'page_title'    => _T("Login"),
                )
            );
        } else {
            $baseRedirect($app);
        }
    }
)->name('login');

// Authentication procedure
$app->post(
    '/login',
    function () use ($app, &$session, $hist, $preferences, $login, $baseRedirect) {
        $nick = $app->request()->post('login');
        $password = $app->request()->post('password');

        if ( trim($nick) == '' || trim($password) == '' ) {
            $app->flash(
                'loginfault',
                _T("You must provide both login and password.")
            );
            $app->redirect($app->urlFor('login'));
        }

        if ( $nick === $preferences->pref_admin_login ) {
            $pw_superadmin = password_verify(
                $password,
                $preferences->pref_admin_pass
            );
            if ( !$pw_superadmin ) {
                $pw_superadmin = (
                    md5($password) === $preferences->pref_admin_pass
                );
            }
            if ( $pw_superadmin ) {
                $login->logAdmin($nick);
            }
        } else {
            $login->logIn($nick, $password);
        }

        if ( $login->isLogged() ) {
            $session['login'] = serialize($login);
            $hist->add(_T("Login"));
            $baseRedirect($app);
        } else {
            $app->flash('loginfault', _T("Login failed."));
            $hist->add(_T("Authentication failed"), $nick);
            $app->redirect($app->urlFor('login'));
        }
    }
)->name('dologin');

$app->get(
    '/logout',
    function () use ($app, $login, &$session) {
        $login->logOut();
        $session['login'] = null;
        unset($session['login']);
        $session['history'] = null;
        unset($session['history']);
        $app->redirect($app->urlFor('slash'));
    }
)->name('logout');

$app->get(
    '/logo',
    function () use ($logo) {
        $logo->display();
    }
)->name('logo');

$app->get(
    '/photo/:id',
    function ($id) use ($app, $login) {
        /** FIXME: we load entire member here... No need to do so! */
        $adh = new Adherent((int)$id);

        $picture = null;
        if ( $login->isAdmin()
            || $login->isStaff()
            || $adh->appearsInMembersList()
            || $login->login == $adh->login
        ) {
            $picture = $adh->picture;
        } else {
            $picture = new Picture();
        }
        $picture->display();
    }
)->name('photo')->conditions(array('id' => '\d+'));

$app->get(
    '/subscribe',
    function () use ($app, $preferences, $login, $i18n) {
        if ( !$preferences->pref_bool_selfsubscribe || $login->isLogged() ) {
            $app->redirect($app->urlFor('slash'));
        }

        $dyn_fields = new DynamicFields();

        // flagging required fields
        $requires = new Required();
        $required = $requires->getRequired();

        $member = new Adherent();
        //mark as self membership
        $member->setSelfMembership();

        // disable some fields
        $disabled  = $member->disabled_fields;

        // DEBUT parametrage des champs
        // On recupere de la base la longueur et les flags des champs
        // et on initialise des valeurs par defaut

        $fields = Adherent::getDbFields();

        // - declare dynamic fields for display
        $disabled['dyn'] = array();
        if ( !isset($adherent['dyn']) ) {
            $adherent['dyn'] = array();
        }

        //image to defeat mass filling forms
        $spam_pass = PasswordImage();
        $s = PasswordImageName($spam_pass);
        $spam_img = print_img($s);

        $dynamic_fields = $dyn_fields->prepareForDisplay(
            'adh', $adherent['dyn'], $disabled['dyn'], 1
        );

        /*if ( $has_register ) {
            $tpl->assign('has_register', $has_register);
        }
        if ( isset($head_redirect) ) {
            $tpl->assign('head_redirect', $head_redirect);
        }*/
        // /self_adh specific

        $app->render(
            'member.tpl',
            array(
                'page_title'        => _T("Subscription"),
                'parent_tpl'        => 'public_page.tpl',
                'required'          => $required,
                'disabled'          => $disabled,
                'member'            => $member,
                'self_adh'          => true,
                'dynamic_fields'    => $dynamic_fields,
                'languages'         => $i18n->getList(),
                'require_calendar'  => true,
                // pseudo random int
                'time'              => time(),
                // genre
                'radio_titres'      => Galette\Entity\Politeness::getList(),
                //self_adh specific
                'spam_pass'         => $spam_pass,
                'spam_img'          => $spam_img
            )
        );

    }
)->name('subscribe');

$app->get(
    '/password-lost',
    function () use ($app) {
        $app->render(
            'lostpasswd.tpl',
            array(
                'page_title' => _T("Password recovery")
            )
        );
    }
)->name('password-lost');

$app->post(
    '/retrieve-pass',
    function () use ($app) {
        $app->redirect($app->urlFor('slash'));
    }
)->name('retrieve-pass');

$app->get(
    '/public/members',
    function () use ($app, &$session) {
        if ( isset($session['public_filters']['members']) ) {
            $filters = unserialize($session['public_filters']['members']);
        } else {
            $filters = new MembersList();
        }

        /*// Filters
        if (isset($_GET['page'])) {
            $filters->current_page = (int)$_GET['page'];
        }

        if ( isset($_GET['clear_filter']) ) {
            $filters->reinit();
        }

        //numbers of rows to display
        if ( isset($_GET['nbshow']) && is_numeric($_GET['nbshow'])) {
            $filters->show = $_GET['nbshow'];
        }

        // Sorting
        if ( isset($_GET['tri']) ) {
            $filters->orderby = $_GET['tri'];
        }*/


        $m = new Members();
        $members = $m->getPublicList(false, null);

        $session['public_filters']['members'] = serialize($filters);

        $smarty = SmartyView::getInstance();

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($smarty);

        $app->render(
            'liste_membres.tpl',
            array(
                'page_title'    => _T("Members list"),
                'members'       => $members,
                'filters'       => $filters
            )
        );
    }
)->name('public_members');

$app->get(
    '/public/trombinoscope',
    function () use ($app) {
        $m = new Members('trombinoscope_');
        $members = $m->getPublicList(true, null);

        $app->render(
            'trombinoscope.tpl',
            array(
                'page_title'                => _T("Trombinoscope"),
                'additionnal_html_class'    => 'trombinoscope',
                'members'                   => $members,
                'time'                      => time()
            )
        );
    }
)->name('public_trombinoscope');

//routes for authenticated users
//routes for admins
//routes for admins/staff
$app->get(
    '/dashboard',
    $authenticate($app),
    function () use ($app, $preferences) {
        $news = new Galette\IO\News($preferences->pref_rss_url);

        $app->render(
            'desktop.tpl',
            array(
                'page_title'        => _T("Dashboard"),
                'contentcls'        => 'desktop',
                'news'              => $news->getPosts(),
                'show_dashboard'    => $_COOKIE['show_galette_dashboard'],
                'require_cookie'    => true
            )
        );
    }
)->name('dashboard');

$app->get(
    '/members',
    $authenticate($app),
    function () use ($app, $login, &$session) {
        /*if ( isset($session['filters']['members'])
            && !isset($_POST['mailing'])
            && !isset($_POST['mailing_new'])
        ) {*/
        if ( isset($session['filters']['members']) ) {
            $filters = unserialize($session['filters']['members']);
        } else {
            $filters = new MembersList();
        }

        $members = new Galette\Repository\Members();

        $members_list = array();
        if ( $login->isAdmin() || $login->isStaff() ) {
            $members_list = $members->getMembersList(true);
        } else {
            $members_list = $members->getManagedMembersList(true);
        }

        $groups = new Galette\Repository\Groups();
        $groups_list = $groups->getList();

        $session['filters']['members'] = serialize($filters);

        $smarty = SmartyView::getInstance();

        //assign pagination variables to the template and add pagination links
        $filters->setSmartyPagination($smarty);

        $app->render(
            'gestion_adherents.tpl',
            array(
                'page_title'            => _T("Members management"),
                'require_dialog'        => true,
                'require_calendar'      => true,
                'members'               => $members_list,
                'filter_groups_options' => $groups_list,
                'nb_members'            => $members->getCount(),
                'filters'               => $filters,
                'filter_field_options'  => array(
                    Members::FILTER_NAME            => _T("Name"),
                    Members::FILTER_COMPANY_NAME    => _T("Company name"),
                    Members::FILTER_ADRESS          => _T("Address"),
                    Members::FILTER_MAIL            => _T("Email,URL,IM"),
                    Members::FILTER_JOB             => _T("Job"),
                    Members::FILTER_INFOS           => _T("Infos")
                ),
                'filter_membership_options' => array(
                    0 => _T("All members"),
                    3 => _T("Up to date members"),
                    1 => _T("Close expiries"),
                    2 => _T("Latecomers"),
                    4 => _T("Never contributed"),
                    5 => _T("Staff members"),
                    6 => _T("Administrators")
                ),
                'filter_accounts_options'   => array(
                    0 => _T("All accounts"),
                    1 => _T("Active accounts"),
                    2 => _T("Inactive accounts")
                )
            )
        );
    }
)->name('members');

$app->get(
    '/member/:id',
    $authenticate($app),
    function ($id) use ($app, $login, $session, $i18n, $preferences) {
        $dyn_fields = new DynamicFields();

        $member = new Adherent();
        $member->load($id);

        if ( $login->id != $id && !$login->isAdmin() && !$login->isStaff() ) {
            //check if requested member is part of managed groups
            $groups = $member->groups;
            $is_managed = false;
            foreach ( $groups as $g ) {
                if ( $login->isGroupManager($g->getId()) ) {
                    $is_managed = true;
                    break;
                }
            }
            if ( $is_managed !== true ) {
                //requested member is not part of managed groups, fall back to logged
                //in member
                $member->load($login->id);
            }
        }

        $navigate = array();

        if ( isset($session['filters']['members']) ) {
            $filters =  unserialize($session['filters']['members']);
        } else {
            $filters = new MembersList();
        }

        if ( ($login->isAdmin() || $login->isStaff()) && count($filters) > 0 ) {
            $m = new Members();
            $ids = $m->getList(false, array(Adherent::PK));
            //print_r($ids);
            foreach ( $ids as $k=>$m ) {
                if ( $m->id_adh == $member->id ) {
                    $navigate = array(
                        'cur'  => $m->id_adh,
                        'count' => count($ids),
                        'pos' => $k+1
                    );
                    if ( $k > 0 ) {
                        $navigate['prev'] = $ids[$k-1]->id_adh;
                    }
                    if ( $k < count($ids)-1 ) {
                        $navigate['next'] = $ids[$k+1]->id_adh;
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

        //if we got a mail warning when adding/editing a member,
        //we show it and delete it from session
        /*if ( isset($session['mail_warning']) ) {
            $warning_detected[] = $session['mail_warning'];
            unset($session['mail_warning']);
        }
        $tpl->assign('warning_detected', $warning_detected);
        if ( isset($session['account_success']) ) {
            $success_detected = unserialize($session['account_success']);
            unset($session['account_success']);
        }*/

        // flagging fields visibility
        $fc = new FieldsConfig(Adherent::TABLE, $member->fields);
        $visibles = $fc->getVisibilities();

        $app->render(
            'voir_adherent.tpl',
            array(
                'page_title'        => _T("Member profile"),
                'require_dialog'    => true,
                'member'            => $member,
                'data'              => $adherent,
                'navigate'          => $navigate,
                'pref_lang_img'     => $i18n->getFlagFromId($member->language),
                'pref_lang'         => ucfirst($i18n->getNameFromId($member->language)),
                'pref_card_self'    => $preferences->pref_card_self,
                'dynamic_fields'    => $dynamic_fields,
                'groups'            => Groups::getSimpleList(),
                'time'              => time(),
                'visibles'          => $visibles
            )
        );

    }
)->name('member');

$app->get(
    '/groups',
    $authenticate($app),
    function () use ($app, $login, &$session) {

        $groups = new Galette\Repository\Groups();
        $group = new Galette\Entity\Group();

        $groups_root = $groups->getList(false);
        $groups_list = $groups->getList();

        $id = $app->request()->get('id');

        if ( $id === null && count($groups_root) > 0 ) {
            $group = $groups_root[0];
            if ( !$login->isGroupManager($group->getId()) ) {
                foreach ( $groups_list as $g ) {
                    if ( $login->isGroupManager($g->getId()) ) {
                        $group = $g;
                        break;
                    }
                }
            }
        }

        $app->render(
            'gestion_groupes.tpl',
            array(
                'page_title'            => _T("Groups"),
                'require_dialog'        => true,
                'require_tabs'          => true,
                'require_tree'          => true,
                'groups_root'           => $groups_root,
                'groups'                => $groups_list,
                'group'                 => $group
            )
        );
    }
)->name('groups');

$app->get(
    '/contributions',
    $authenticate($app),
    function () use ($app, $login, &$session) {

        if ( isset($session['contributions'])) {
            $contribs = unserialize($session['contributions']);
        } else {
            $contribs = new Galette\Repository\Contributions();
        }

        /*if ( $ajax === true ) {
            $contribs->filtre_transactions = true;
            if ( isset($_POST['max_amount']) ) {
                $contribs->max_amount = (int)$_POST['max_amount'];
            } else if ( $_GET['max_amount'] ) {
                $contribs->max_amount = (int)$_GET['max_amount'];
            }
        } else {
            $contribs->max_amount = null;
        }*/
        $contribs->max_amount = null;

        /*if ( isset($_GET['page']) && is_numeric($_GET['page']) ) {
            $contribs->current_page = (int)$_GET['page'];
        }

        if ( (isset($_GET['nbshow']) && is_numeric($_GET['nbshow']))
        ) {
            $contribs->show = $_GET['nbshow'];
        }

        if ( (isset($_POST['nbshow']) && is_numeric($_POST['nbshow']))
        ) {
            $contribs->show = $_POST['nbshow'];
        }

        if ( isset($_GET['tri']) ) {
            $contribs->orderby = $_GET['tri'];
        }

        if ( isset($_GET['clear_filter']) ) {
            $contribs->reinit();
        } else {
            if ( isset($_GET['end_date_filter']) || isset($_GET['start_date_filter']) ) {
                try {
                    if ( isset($_GET['start_date_filter']) ) {
                        $field = _T("start date filter");
                        $contribs->start_date_filter = $_GET['start_date_filter'];
                    }
                    if ( isset($_GET['end_date_filter']) ) {
                        $field = _T("end date filter");
                        $contribs->end_date_filter = $_GET['end_date_filter'];
                    }
                } catch (Exception $e) {
                    $error_detected[] = $e->getMessage();
                }
            }

            if ( isset($_GET['payment_type_filter']) ) {
                $ptf = $_GET['payment_type_filter'];
                if ( $ptf == Galette\Entity\Contribution::PAYMENT_OTHER
                    || $ptf == Galette\Entity\Contribution::PAYMENT_CASH
                    || $ptf == Galette\Entity\Contribution::PAYMENT_CREDITCARD
                    || $ptf == Galette\Entity\Contribution::PAYMENT_CHECK
                    || $ptf == Galette\Entity\Contribution::PAYMENT_TRANSFER
                    || $ptf == Galette\Entity\Contribution::PAYMENT_PAYPAL
                ) {
                    $contribs->payment_type_filter = $ptf;
                } elseif ( $ptf == -1 ) {
                    $contribs->payment_type_filter = null;
                } else {
                    $error_detected[] = _T("- Unknown payment type!");
                }
            }
        }*/

        $id = $app->request()->get('id');
        if ( ($login->isAdmin() || $login->isStaff())
            && isset($id) && $id != ''
        ) {
            if ( $id == 'all' ) {
                $contribs->filtre_cotis_adh = null;
            } else {
                $contribs->filtre_cotis_adh = $id;
            }
        }

        /*if ( $login->isAdmin() || $login->isStaff() ) {
            //delete contributions
            if (isset($_GET['sup']) || isset($_POST['delete'])) {
                if ( isset($_GET['sup']) ) {
                    $contribs->removeContributions($_GET['sup']);
                } else if ( isset($_POST['contrib_sel']) ) {
                    $contribs->removeContributions($_POST['contrib_sel']);
                }
            }
        }*/

        $session['contributions'] = serialize($contribs);
        $list_contribs = $contribs->getContributionsList(true);

        $smarty = SmartyView::getInstance();

        //assign pagination variables to the template and add pagination links
        $contribs->setSmartyPagination($smarty);

        /*if ( $contribs->filtre_cotis_adh != null && !$ajax ) {
            $member = new Galette\Entity\Adherent();
            $member->load($contribs->filtre_cotis_adh);
            $tpl->assign('member', $member);
        }*/

        $app->render(
            'gestion_contributions.tpl',
            array(
                'page_title'            => _T("Contributions management"),
                'require_dialog'        => true,
                'require_calendar'      => true,
                'max_amount'            => $contribs->max_amount,
                'list_contribs'         => $list_contribs,
                'contributions'         => $contribs,
                'nb_contributions'      => $contribs->getCount(),
                'mode'                  => 'std'
            )
        );
    }
)->name('contributions');



$app->run();
