<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

namespace Galette\Filters;

use Analog\Analog;
use Galette\Core\Pagination;
use Galette\Entity\Group;
use Galette\Repository\Members;
use Slim\Views\Twig;

/**
 * Members list filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string $filter_str
 * @property ?integer $field_filter
 * @property ?integer $membership_filter
 * @property ?integer $filter_account
 * @property ?integer $email_filter
 * @property ?integer $group_filter
 * @property integer[] $selected
 * @property integer[] $unreachable
 * @property string $query
 */

class MembersList extends Pagination
{
    //filters
    private ?string $filter_str = null;
    private ?int $field_filter = null;
    private ?int $membership_filter = null;
    private ?int $filter_account = null;
    private ?int $email_filter = null;
    private ?int $group_filter = null;

    /** @var array<int> */
    private array $selected = [];
    /** @var array<int> */
    private array $unreachable = [];

    protected string $query = '';

    /** @var array<string> */
    protected array $memberslist_fields = [
        'filter_str',
        'field_filter',
        'membership_filter',
        'filter_account',
        'email_filter',
        'group_filter',
        'selected',
        'unreachable',
        'query'
    ];

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
        $this->filter_str = null;
        $this->field_filter = null;
        $this->membership_filter = null;
        $this->filter_account = $preferences->pref_filter_account;
        $this->email_filter = Members::FILTER_DC_EMAIL;
        $this->group_filter = null;
        $this->selected = [];
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } elseif (in_array($name, $this->memberslist_fields)) {
            return $this->$name;
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                static::class,
                $name
            )
        );
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
    public function __set(string $name, mixed $value): void
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
                        $this->$name = $value;
                    } elseif ($value !== null) {
                        Analog::log(
                            '[MembersList] Value for property `' . $name
                            . '` should be an array (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'filter_str':
                    $this->$name = $value;
                    break;
                case 'field_filter':
                case 'membership_filter':
                case 'filter_account':
                    if (is_numeric($value)) {
                        $this->$name = (int)$value;
                    } elseif ($value !== null) {
                        Analog::log(
                            '[MembersList] Value for property `' . $name
                            . '` should be an integer (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'email_filter':
                    switch ($value) {
                        case Members::FILTER_DC_EMAIL:
                        case Members::FILTER_W_EMAIL:
                        case Members::FILTER_WO_EMAIL:
                            $this->email_filter = (int)$value;
                            break;
                        default:
                            Analog::log(
                                '[MembersList] Value for email filter should be either '
                                . Members::FILTER_DC_EMAIL . ', '
                                . Members::FILTER_W_EMAIL . ' or '
                                . Members::FILTER_WO_EMAIL . ' (' . $value . ' given)',
                                Analog::WARNING
                            );
                            break;
                    }
                    break;
                case 'group_filter':
                    if (is_numeric($value) && $value > 0) {
                        $value = (int)$value;
                        //check group existence
                        $g = new Group();
                        $res = $g->load($value);
                        if ($res === true) {
                            $this->group_filter = $value;
                        } else {
                            Analog::log(
                                'Group #' . $value . ' does not exists!',
                                Analog::WARNING
                            );
                        }
                    } elseif ($value === null) {
                        $this->group_filter = null;
                    } else {
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
                        '[MembersList] Unable to set property `' . $name . '`',
                        Analog::WARNING
                    );
                    break;
            }
        }
    }

    /**
     * Set commons filters for templates
     *
     * @param Twig $view Template reference
     *
     * @return void
     */
    public function setViewCommonsFilters(Twig $view): void
    {
        $filter_options = [
            Members::FILTER_NAME            => _T("Name"),
            Members::FILTER_NUMBER          => _T("Member number"),
            Members::FILTER_COMPANY_NAME    => _T("Company name"),
            Members::FILTER_ADDRESS         => _T("Address"),
            Members::FILTER_MAIL            => _T("Email,URL,IM"),
            Members::FILTER_JOB             => _T("Job"),
            Members::FILTER_INFOS           => _T("Infos"),
            Members::FILTER_ID              => _T("Member ID")
        ];

        $view->getEnvironment()->addGlobal(
            'field_filter_options',
            $filter_options
        );

        $view->getEnvironment()->addGlobal(
            'membership_filter_options',
            [
                Members::MEMBERSHIP_ALL     => _T("All members"),
                Members::MEMBERSHIP_UP2DATE => _T("Up to date members"),
                Members::MEMBERSHIP_NEARLY  => _T("Close expiries"),
                Members::MEMBERSHIP_LATE    => _T("Latecomers"),
                Members::MEMBERSHIP_NEVER   => _T("Never contributed"),
                Members::MEMBERSHIP_STAFF   => _T("Staff members"),
                Members::MEMBERSHIP_ADMIN   => _T("Administrators"),
                Members::MEMBERSHIP_NONE    => _T("Non members")
            ]
        );

        $view->getEnvironment()->addGlobal(
            'filter_accounts_options',
            [
                Members::ALL_ACCOUNTS       => _T("All accounts"),
                Members::ACTIVE_ACCOUNT     => _T("Active accounts"),
                Members::INACTIVE_ACCOUNT   => _T("Inactive accounts")
            ]
        );
    }
}
