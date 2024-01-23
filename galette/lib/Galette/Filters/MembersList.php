<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members list filters and paginator
 *
 * PHP version 5
 *
 * Copyright © 2009-2024 The Galette Team
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
 *
 * @category  Filters
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 * @since     march, 3rd 2009
 */

namespace Galette\Filters;

use Analog\Analog;
use Galette\Core\Pagination;
use Galette\Core\Preferences;
use Galette\Entity\Group;
use Galette\Repository\Members;
use Slim\Views\Twig;

/**
 * Members list filters and paginator
 *
 * @name      MembersList
 * @category  Filters
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2024 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://galette.eu
 *
 * @property string $filter_str
 * @property integer $field_filter
 * @property integer $membership_filter
 * @property integer $filter_account
 * @property integer $email_filter
 * @property integer $group_filter
 * @property array $selected
 * @property array $unreachable
 * @property string $query
 */

class MembersList extends Pagination
{
    //filters
    private ?string $_filter_str = null;
    private ?int $_field_filter = null;
    private ?int $_membership_filter = null;
    private ?int $_filter_account = null;
    private ?int $_email_filter = null;
    private ?int $_group_filter = null;

    /** @var array<int> */
    private array $_selected = [];
    /** @var array<int> */
    private array $_unreachable = [];

    protected string $query;

    /** @var array<string> */
    protected array $memberslist_fields = array(
        'filter_str',
        'field_filter',
        'membership_filter',
        'filter_account',
        'email_filter',
        'group_filter',
        'selected',
        'unreachable',
        'query'
    );

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->reinit();
    }

    /**
     * Returns the field we want to default set order to
     *
     * @return int|string
     */
    protected function getDefaultOrder(): int|string
    {
        return 'nom_adh';
    }

    /**
     * Reinit default parameters
     *
     * @return void
     */
    public function reinit(): void
    {
        global $preferences;

        parent::reinit();
        $this->_filter_str = null;
        $this->_field_filter = null;
        $this->_membership_filter = null;
        $this->_filter_account = $preferences->pref_filter_account;
        $this->_email_filter = Members::FILTER_DC_EMAIL;
        $this->_group_filter = null;
        $this->_selected = array();
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name)
    {
        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } else {
            if (in_array($name, $this->memberslist_fields)) {
                if ($name === 'query') {
                    return $this->$name;
                } else {
                    $name = '_' . $name;
                    return $this->$name;
                }
            } else {
                Analog::log(
                    '[MembersList] Unable to get property `' . $name . '`',
                    Analog::WARNING
                );
            }
        }
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        if (in_array($name, $this->pagination_fields)) {
            return true;
        } elseif (in_array($name, $this->memberslist_fields)) {
            return true;
        }

        return false;
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     *
     * @return void
     */
    public function __set(string $name, $value): void
    {
        if (in_array($name, $this->pagination_fields)) {
            parent::__set($name, $value);
        } else {
            Analog::log(
                '[MembersList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'selected':
                case 'unreachable':
                    if (is_array($value)) {
                        $name = '_' . $name;
                        $this->$name = $value;
                    } elseif ($value !== null) {
                        Analog::log(
                            '[MembersList] Value for property `' . $name .
                            '` should be an array (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'filter_str':
                    $name = '_' . $name;
                    $this->$name = $value;
                    break;
                case 'field_filter':
                case 'membership_filter':
                case 'filter_account':
                    if (is_numeric($value)) {
                        $name = '_' . $name;
                        $this->$name = $value;
                    } elseif ($value !== null) {
                        Analog::log(
                            '[MembersList] Value for property `' . $name .
                            '` should be an integer (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'email_filter':
                    switch ($value) {
                        case Members::FILTER_DC_EMAIL:
                        case Members::FILTER_W_EMAIL:
                        case Members::FILTER_WO_EMAIL:
                            $this->_email_filter = $value;
                            break;
                        default:
                            Analog::log(
                                '[MembersList] Value for email filter should be either ' .
                                Members::FILTER_DC_EMAIL . ', ' .
                                Members::FILTER_W_EMAIL . ' or ' .
                                Members::FILTER_WO_EMAIL . ' (' . $value . ' given)',
                                Analog::WARNING
                            );
                            break;
                    }
                    break;
                case 'group_filter':
                    if (is_numeric($value) && $value > 0) {
                        //check group existence
                        $g = new Group();
                        $res = $g->load($value);
                        if ($res === true) {
                            $this->_group_filter = $value;
                        } else {
                            Analog::log(
                                'Group #' . $value . ' does not exists!',
                                Analog::WARNING
                            );
                        }
                    } elseif ($value !== null && $value !== '0') {
                        Analog::log(
                            '[MembersList] Value for group filter should be an '
                            . 'integer (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'query':
                    $this->$name = $value;
                    break;
                default:
                    Analog::log(
                        '[MembersList] Unable to set proprety `' . $name . '`',
                        Analog::WARNING
                    );
                    break;
            }
        }
    }

    /**
     * Set commons filters for templates
     *
     * @param Preferences $prefs Preferences instance
     * @param Twig        $view  Template reference
     *
     * @return void
     */
    public function setViewCommonsFilters(Preferences $prefs, Twig $view): void
    {
        $filter_options = array(
            Members::FILTER_NAME            => _T("Name"),
            Members::FILTER_NUMBER          => _T("Member number"),
            Members::FILTER_COMPANY_NAME    => _T("Company name"),
            Members::FILTER_ADDRESS         => _T("Address"),
            Members::FILTER_MAIL            => _T("Email,URL,IM"),
            Members::FILTER_JOB             => _T("Job"),
            Members::FILTER_INFOS           => _T("Infos")
        );

        if ($prefs->pref_show_id) {
            $filter_options[Members::FILTER_ID] = _T("Member ID");
        }

        $view->getEnvironment()->addGlobal(
            'field_filter_options',
            $filter_options
        );

        $view->getEnvironment()->addGlobal(
            'membership_filter_options',
            array(
                Members::MEMBERSHIP_ALL     => _T("All members"),
                Members::MEMBERSHIP_UP2DATE => _T("Up to date members"),
                Members::MEMBERSHIP_NEARLY  => _T("Close expiries"),
                Members::MEMBERSHIP_LATE    => _T("Latecomers"),
                Members::MEMBERSHIP_NEVER   => _T("Never contributed"),
                Members::MEMBERSHIP_STAFF   => _T("Staff members"),
                Members::MEMBERSHIP_ADMIN   => _T("Administrators"),
                Members::MEMBERSHIP_NONE    => _T("Non members")
            )
        );

        $view->getEnvironment()->addGlobal(
            'filter_accounts_options',
            array(
                Members::ALL_ACCOUNTS       => _T("All accounts"),
                Members::ACTIVE_ACCOUNT     => _T("Active accounts"),
                Members::INACTIVE_ACCOUNT   => _T("Inactive accounts")
            )
        );
    }
}
