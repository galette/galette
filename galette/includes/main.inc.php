<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's instanciation and routes
 *
 * PHP version 5
 *
 * Copyright © 2012-2014 The Galette Team
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
 * @copyright 2012-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.8.2dev 2014-11-10
 */

use \Slim\Slim;
use \Slim\Route;
use Galette\Core\Smarty;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Entity\Group;
use Galette\Entity\Contribution;
use Galette\Repository\Groups;
use Galette\Repository\Contributions;
use \Analog\Analog as Analog;

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

$app = new Slim(
    array(
        'view'              => new Smarty(
            $plugins,
            $i18n,
            $preferences,
            $logo,
            $login,
            $session
        ),
        'templates.path'    => GALETTE_ROOT . GALETTE_TPL_SUBDIR,
        'mode'              => GALETTE_MODE
    )
);

$app->configureMode(
    'DEV',
    function () use ($app) {
        $app->config(
            array(
                'debug' => true
            )
        );
    }
);

//set default conditions
Route::setDefaultConditions(
    array(
        'id' => '\d+'
    )
);

$smarty = $app->view()->getInstance();
require_once GALETTE_ROOT . 'includes/smarty.inc.php';

$acls = [
    'preferences'       => 'admin',
    'store-preferences' => 'admin',
    'dashboard'         => 'staff',
    'sysinfos'          => 'staff',
    'charts'            => 'staff',
    'plugins'           => 'admin',
    'history'           => 'staff',
    'members'           => 'groupmanager',
    'filter-memberslist'=> 'groupmanager',
    'advanced-search'   => 'groupmanager',
    'batch-memberslist' => 'groupmanager',
    'mailing'           => 'staff',
    'csv-memberslist'   => 'staff',
    'groups'            => 'groupmanager',
    'me'                => 'member',
    'member'            => 'member',
    'pdf-members-cards' => 'member',
    'pdf-members-labels'=> 'groupmanager',
    'mailings'          => 'staff',
    'contributions'     => 'staff'
];

//load user defined ACLs
if ( file_exists(GALETTE_CONFIG_PATH  . 'local_acls.inc.php') ) {
    $acls = array_merge($acls, $local_acls);
}

$authenticate = function () use (&$session, $acls, $app) {
    return function () use ($app, &$session, $acls) {
        if ( isset($session['login']) ) {
            $login = unserialize($session['login']);
        } else {
            $login = new Login();
        }
        if ( !$login->isLogged() ) {
            $session['urlRedirect'] = $app->request()->getPathInfo();
            $app->flash('error', _T("Login required"));
            $app->redirect($app->urlFor('slash'), 403);
        } else {
            //check for ACLs
            $cur_route = getCurrentRoute($app);
            if ( isset($acls[$cur_route]) ) {
                $acl = $acls[$cur_route];
                $go = false;
                switch ( $acl ) {
                case 'superadmin':
                    if ( $login->isSuperAdmin() ) {
                        $go = true;
                    }
                    break;
                case 'admin':
                    if ( $login->isSuperAdmin()
                        || $login->isAdmin()
                    ) {
                        $go = true;
                    }
                    break;
                case 'staff':
                    if ( $login->isSuperAdmin()
                        || $login->isAdmin()
                        || $login->isStaff()
                    ) {
                        $go = true;
                    }
                    break;
                case 'groupmanager':
                    if ( $login->isSuperAdmin()
                        || $login->isAdmin()
                        || $login->isStaff()
                        || $login->isGroupManager()
                    ) {
                        $go = true;
                    }
                    break;
                case 'member':
                    if ( $login->isLogged() ) {
                        $go = true;
                    }
                    break;
                default:
                    throw new \RuntimeException(
                        str_replace(
                            '%acl',
                            $acl,
                            _T("Unknown ACL rule '%acl'!")
                        )
                    );
                    break;
                }
                if ( !$go ) {
                    $app->flash(
                        'error_detected', [
                            _T("You do not have permission for requested URL.")
                        ]
                    );
                    $app->redirect($app->urlFor('slash'), 403);
                }
            } else {
                throw new \RuntimeException(
                    str_replace(
                        '%name',
                        $cur_route,
                        _T("Route '%name' is not registered in ACLs!")
                    )
                );
            }
        }
    };
};

$baseRedirect = function ($app) use ($login, &$session) {
    if ( $login->isLogged() ) {
        $urlRedirect = null;
        if (isset($session['urlRedirect'])) {
            $urlRedirect = $app->request()->getRootUri() . $session['urlRedirect'];
            unset($session['urlRedirect']);
        }

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

/**
 * Get current URI
 *
 * @param app $app Slim application instance
 *
 * @return string
 */
function getCurrentUri($app)
{
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
    return $curUri;
};

/**
 * Retrieve current route name
 *
 * @param app $app Slim application instance
 *
 * @return string
 */
function getCurrentRoute($app)
{
    $cur_route = $app->router()->getMatchedRoutes(
        'get',
        $app->request()->getPathInfo()
    )[0]->getName();
    return $cur_route;
}

$app->hook(
    'slim.before.dispatch',
    function () use ($app, $error_detected, $warning_detected, $success_detected,
        $authenticate, $acls
    ) {

        if ( GALETTE_MODE === 'DEV' ) {
            //check for routes that are not in ACLs
            $named_routes = $app->router()->getNamedRoutes();
            $missing_acls = [];
            $excluded_names = [
                'public_members',
                'public_trombinoscope'
            ];
            foreach ( $named_routes as $name=>$route ) {
                //check if route has $authenticate middleware
                $middlewares = $route->getMiddleware();
                if ( count($middlewares) > 0 ) {
                    foreach ( $middlewares as $middleware ) {
                        if ( !in_array($name, array_keys($acls))
                            && !in_array($name, $excluded_names)
                            && !in_array($name, $missing_acls)
                        ) {
                            $missing_acls[] = $name;
                        }
                    }
                }
            }
            if ( count($missing_acls) > 0 ) {
                $msg = str_replace(
                    '%routes',
                    implode(', ', $missing_acls),
                    _T("Routes '%routes' are missing in ACLs!")
                );
                Analog::log($msg, Analog::ERROR);
                //FIXME: with flash(), message is only shown on the seconde round,
                //with flashNow(), thas just does not work :(
                $app->flash('error_detected', [$msg]);
            }
        }

        $v = $app->view();

        $v->setData('galette_base_path', getCurrentUri($app));
        $v->setData('cur_route', getCurrentRoute($app));
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

        //FIXME: no longer works, should be set with $app::flash()
        if ( isset($error_detected) ) {
            $v->setData('error_detected', $error_detected);
        }
        //FIXME: no longer works, should be set with $app::flash()
        if (isset($warning_detected)) {
            $v->setData('warning_detected', $warning_detected);
        }
        //FIXME: no longer works, should be set with $app::flash()
        if (isset($success_detected)) {
            $v->setData('success_detected', $success_detected);
        }
    }
);

require_once GALETTE_ROOT . 'includes/routes/main.routes.php';
require_once GALETTE_ROOT . 'includes/routes/authentication.routes.php';
require_once GALETTE_ROOT . 'includes/routes/management.routes.php';
require_once GALETTE_ROOT . 'includes/routes/members.routes.php';
require_once GALETTE_ROOT . 'includes/routes/public_pages.routes.php';
require_once GALETTE_ROOT . 'includes/routes/ajax.routes.php';

//custom error handler
//will not be used if mode is DEV.
$app->error(
    function (\Exception $e) use ($app) {
        //ensure error is logged
        $etype = get_class($e);
        Analog::log(
            'exception \'' . $etype . '\' with message \'' . $e->getMessage() .
            '\' in ' . $e->getFile() . ':' . $e->getLine() .
            "\nStack trace:\n" . $e->getTraceAsString(),
            Analog::ERROR
        );

        $app->render(
            '500.tpl',
            array(
                'page_title'        => _T("Error"),
                'exception'         => $e,
                'galette_base_path' => getCurrentUri($app)
            )
        );
    }
);

//custom 404 handler
$app->notFound(
    function () use ($app) {
        $app->render(
            '404.tpl',
            array(
                'page_title'        => _T("Page not found :("),
                'cur_route'         => null,
                'galette_base_path' => getCurrentUri($app)
            )
        );
    }
);


//routes for authenticated users
//routes for admins
//routes for admins/staff

$app->get(
    '/groups',
    $authenticate(),
    function () use ($app, $login, &$session) {

        $groups = new Groups();
        $group = new Group();

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
    $authenticate(),
    function () use ($app, $login, &$session) {

        if ( isset($session['contributions'])) {
            $contribs = unserialize($session['contributions']);
        } else {
            $contribs = new Contributions();
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
                if ( $ptf == Contribution::PAYMENT_OTHER
                    || $ptf == Contribution::PAYMENT_CASH
                    || $ptf == Contribution::PAYMENT_CREDITCARD
                    || $ptf == Contribution::PAYMENT_CHECK
                    || $ptf == Contribution::PAYMENT_TRANSFER
                    || $ptf == Contribution::PAYMENT_PAYPAL
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

        $smarty = $app->view()->getInstance();

        //assign pagination variables to the template and add pagination links
        $contribs->setSmartyPagination($smarty);

        /*if ( $contribs->filtre_cotis_adh != null && !$ajax ) {
            $member = new Adherent();
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

if ( isset($profiler) ) {
    $profiler->stop();
}
