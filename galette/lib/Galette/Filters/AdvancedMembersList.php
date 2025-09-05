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

use Galette\Helpers\DatesHelper;
use Throwable;
use Analog\Analog;
use Galette\Entity\Status;
use Galette\Entity\ContributionsTypes;
use Galette\Repository\Members;
use Galette\DynamicFields\DynamicField;
use Galette\Repository\PaymentTypes;

/**
 * Members list filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string $creation_date_begin
 * @property ?string $creation_date_end
 * @property ?string $modif_date_begin
 * @property ?string $modif_date_end
 * @property ?string $due_date_begin
 * @property ?string $due_date_end
 * @property ?string $birth_date_begin
 * @property ?string $birth_date_end
 * @property int $show_public_infos
 * @property integer[]|integer $status
 * @property ?string $contrib_creation_date_begin
 * @property ?string $contrib_creation_date_end
 * @property ?string $contrib_begin_date_begin
 * @property ?string $contrib_begin_date_end
 * @property ?string $contrib_end_date_begin
 * @property ?string $contrib_end_date_end
 * @property integer[] $contributions_types
 * @property integer[] $payments_types
 * @property ?float $contrib_min_amount
 * @property ?float $contrib_max_amount
 * @property array<int, mixed> $contrib_dynamic
 * @property array<mixed, mixed> $free_search
 * @property array<mixed, mixed> $groups_search
 * @property integer $groups_search_log_op
 *
 * @property-read ?string $rcreation_date_begin
 * @property-read ?string $rcreation_date_end
 * @property-read ?string $rmodif_date_begin
 * @property-read ?string $rmodif_date_end
 * @property-read ?string $rdue_date_begin
 * @property-read ?string $rdue_date_end
 * @property-read ?string $rbirth_date_begin
 * @property-read ?string $rbirth_date_end
 * @property-read ?string $rcontrib_creation_date_begin
 * @property-read ?string $rcontrib_creation_date_end
 * @property-read ?string $rcontrib_begin_date_begin
 * @property-read ?string $rcontrib_begin_date_end
 * @property-read ?string $rcontrib_end_date_begin
 * @property-read ?string $rcontrib_end_date_end
 * @property-read string[] $search_fields
 */

class AdvancedMembersList extends MembersList
{
    use DatesHelper;

    public const OP_AND = 0;
    public const OP_OR = 1;

    public const OP_EQUALS = 0;
    public const OP_CONTAINS = 1;
    public const OP_NOT_EQUALS = 2;
    public const OP_NOT_CONTAINS = 3;
    public const OP_STARTS_WITH = 4;
    public const OP_ENDS_WITH = 5;
    public const OP_BEFORE = 6;
    public const OP_AFTER = 7;

    private ?string $creation_date_begin = null; //@phpstan-ignore-line
    private ?string $creation_date_end = null; //@phpstan-ignore-line
    private ?string $modif_date_begin = null; //@phpstan-ignore-line
    private ?string $modif_date_end = null; //@phpstan-ignore-line
    private ?string $due_date_begin = null; //@phpstan-ignore-line
    private ?string $due_date_end = null; //@phpstan-ignore-line
    private ?string $birth_date_begin = null; //@phpstan-ignore-line
    private ?string $birth_date_end = null; //@phpstan-ignore-line
    private int $show_public_infos = Members::FILTER_DC_PUBINFOS;
    /** @var array<int> */
    private array $status = [];
    private ?string $contrib_creation_date_begin = null; //@phpstan-ignore-line
    private ?string $contrib_creation_date_end = null; //@phpstan-ignore-line
    private ?string $contrib_begin_date_begin = null; //@phpstan-ignore-line
    private ?string $contrib_begin_date_end = null; //@phpstan-ignore-line
    private ?string $contrib_end_date_begin = null; //@phpstan-ignore-line
    private ?string $contrib_end_date_end = null; //@phpstan-ignore-line
    /** @var array<int> */
    private array $contributions_types = [];
    /** @var array<int> */
    private array $payments_types = [];
    private ?float $contrib_min_amount = null;
    private ?float $contrib_max_amount = null;

    /** @var array<string> */
    protected array $advancedmemberslist_fields = [
        'creation_date_begin',
        'creation_date_end',
        'modif_date_begin',
        'modif_date_end',
        'due_date_begin',
        'due_date_end',
        'birth_date_begin',
        'birth_date_end',
        'show_public_infos',
        'status',
        'contrib_creation_date_begin',
        'contrib_creation_date_end',
        'contrib_begin_date_begin',
        'contrib_begin_date_end',
        'contrib_end_date_begin',
        'contrib_end_date_end',
        'contributions_types',
        'payments_types',
        'contrib_min_amount',
        'contrib_max_amount',
        'contrib_dynamic',
        'free_search',
        'groups_search',
        'groups_search_log_op'
    ];

    /** @var array<string> */
    protected array $virtuals_advancedmemberslist_fields = [
        'rcreation_date_begin',
        'rcreation_date_end',
        'rmodif_date_begin',
        'rmodif_date_end',
        'rdue_date_begin',
        'rdue_date_end',
        'rbirth_date_begin',
        'rbirth_date_end',
        'rcontrib_creation_date_begin',
        'rcontrib_creation_date_end',
        'rcontrib_begin_date_begin',
        'rcontrib_begin_date_end',
        'rcontrib_end_date_begin',
        'rcontrib_end_date_end',
        'search_fields'
    ];

    /**
     * an empty free search criteria to begin
     *
     * @var array<string,mixed>
     */
    private array $free_search = [
        'empty' => [
            'field'     => '',
            'search'    => '',
            'log_op'    => self::OP_AND,
            'qry_op'    => self::OP_EQUALS
        ]
    ];

    /**
     * an empty group search criteria to begin
     *
     * @var array<string,mixed>
     */
    private array $groups_search = [
        'empty' => [
            'group'    => '',
        ]
    ];

    //defaults to 'OR' for group search
    private int $groups_search_log_op = self::OP_OR;

    /**
     * an empty contributions dynamic field criteria to begin
     *
     * @var array<string,mixed>
     */
    private array $contrib_dynamic = [];

    /**
     * Default constructor
     *
     * @param ?MembersList $simple A simple filter search to keep
     */
    public function __construct(?MembersList $simple = null)
    {
        parent::__construct();
        if ($simple instanceof MembersList) {
            foreach ($this->pagination_fields as $pf) {
                $this->$pf = $simple->$pf;
            }
            foreach ($this->memberslist_fields as $mlf) {
                $this->$mlf = $simple->$mlf;
            }
        }
    }

    /**
     * Do we want to filter within contributions?
     *
     * @return boolean
     */
    public function withinContributions(): bool
    {
        if (
            $this->contrib_creation_date_begin != null
            || $this->contrib_creation_date_end != null
            || $this->contrib_begin_date_begin != null
            || $this->contrib_begin_date_end != null
            || $this->contrib_end_date_begin != null
            || $this->contrib_end_date_end != null
            || $this->contrib_min_amount != null
            || $this->contrib_max_amount != null
            || count($this->contrib_dynamic) > 0
            || count($this->contributions_types) > 0
            || count($this->payments_types) > 0
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Reinit default parameters
     *
     * @return void
     */
    public function reinit(): void
    {
        parent::reinit();

        $this->creation_date_begin = null;
        $this->creation_date_end = null;
        $this->modif_date_begin = null;
        $this->modif_date_end = null;
        $this->due_date_begin = null;
        $this->due_date_end = null;
        $this->birth_date_begin = null;
        $this->birth_date_end = null;
        $this->show_public_infos = Members::FILTER_DC_PUBINFOS;
        $this->status = [];

        $this->contrib_creation_date_begin = null;
        $this->contrib_creation_date_end = null;
        $this->contrib_begin_date_begin = null;
        $this->contrib_begin_date_end = null;
        $this->contrib_end_date_begin = null;
        $this->contrib_begin_date_end = null;
        $this->contributions_types = [];
        $this->payments_types = [];

        $this->free_search = [
            'empty' => [
                'field'     => '',
                'search'    => '',
                'log_op'    => self::OP_AND,
                'qry_op'    => self::OP_EQUALS
            ]
        ];

        $this->contrib_dynamic = [];

        $this->groups_search = [
            'empty' => [
                'group'     => '',
            ]
        ];

        $this->groups_search_log_op = self::OP_OR;
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
        if (
            in_array($name, $this->pagination_fields)
            || in_array($name, $this->memberslist_fields)
        ) {
            return parent::__get($name);
        } elseif (
            in_array($name, $this->advancedmemberslist_fields)
            || in_array($name, $this->virtuals_advancedmemberslist_fields)
        ) {
            switch ($name) {
                case 'creation_date_begin':
                case 'creation_date_end':
                case 'modif_date_begin':
                case 'modif_date_end':
                case 'due_date_begin':
                case 'due_date_end':
                case 'birth_date_begin':
                case 'birth_date_end':
                case 'contrib_creation_date_begin':
                case 'contrib_creation_date_end':
                case 'contrib_begin_date_begin':
                case 'contrib_begin_date_end':
                case 'contrib_end_date_begin':
                case 'contrib_end_date_end':
                    return $this->getDate($name);
                case 'rcreation_date_begin':
                case 'rcreation_date_end':
                case 'rmodif_date_begin':
                case 'rmodif_date_end':
                case 'rdue_date_begin':
                case 'rdue_date_end':
                case 'rbirth_date_begin':
                case 'rbirth_date_end':
                case 'rcontrib_creation_date_begin':
                case 'rcontrib_creation_date_end':
                case 'rcontrib_begin_date_begin':
                case 'rcontrib_begin_date_end':
                case 'rcontrib_end_date_begin':
                case 'rcontrib_end_date_end':
                    $rname = substr($name, 1);
                    return $this->getDate($rname, true, false);
                case 'search_fields':
                    $search_fields = array_merge($this->memberslist_fields, $this->advancedmemberslist_fields);
                    $key = array_search('selected', $search_fields);
                    unset($search_fields[$key]);
                    $key = array_search('unreachable', $search_fields);
                    unset($search_fields[$key]);
                    $key = array_search('query', $search_fields);
                    unset($search_fields[$key]);
                    return $search_fields;
            }
            return $this->$name;
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                self::class,
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
        return
            in_array($name, $this->pagination_fields)
            || in_array($name, $this->memberslist_fields)
            || in_array($name, $this->advancedmemberslist_fields)
            || in_array($name, $this->virtuals_advancedmemberslist_fields);
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
        global $zdb, $preferences, $login;

        if (
            in_array($name, $this->pagination_fields)
            || in_array($name, $this->memberslist_fields)
        ) {
            parent::__set($name, $value);
        } else {
            Analog::log(
                '[AdvancedMembersList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'creation_date_begin':
                case 'creation_date_end':
                case 'modif_date_begin':
                case 'modif_date_end':
                case 'due_date_begin':
                case 'due_date_end':
                case 'birth_date_begin':
                case 'birth_date_end':
                case 'contrib_creation_date_begin':
                case 'contrib_creation_date_end':
                case 'contrib_begin_date_begin':
                case 'contrib_begin_date_end':
                case 'contrib_end_date_begin':
                case 'contrib_end_date_end':
                    $this->setFilterDate($name, (string)$value, str_contains($name, 'begin'));
                    break;
                case 'contrib_min_amount':
                case 'contrib_max_amount':
                    if (is_float($value)) {
                        $this->$name = $value;
                    } elseif ($value !== null) {
                        Analog::log(
                            'Incorrect amount for ' . $name . '! '
                            . 'Should be a float (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'show_public_infos':
                    if (is_numeric($value)) {
                        $this->$name = (int)$value;
                    } else {
                        Analog::log(
                            '[AdvancedMembersList] Value for property `' . $name
                            . '` should be an integer (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'status':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $this->status = [];
                    foreach ($value as $v) {
                        if (is_numeric($v)) {
                            //check status existence
                            $s = new Status($zdb);
                            $res = $s->get((int)$v);
                            if ($res !== false) {
                                $this->status[] = $v;
                            } else {
                                Analog::log(
                                    'Status #' . $v . ' does not exists!',
                                    Analog::WARNING
                                );
                            }
                        } else {
                            Analog::log(
                                '[AdvancedMembersList] Value for status filter should be an '
                                . 'integer (' . gettype($v) . ' given',
                                Analog::WARNING
                            );
                        }
                    }
                    break;
                case 'contributions_types':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $this->contributions_types = [];
                    foreach ($value as $v) {
                        if (is_numeric($v)) {
                            //check type existence
                            $s = new ContributionsTypes($zdb);
                            $res = $s->get((int)$v);
                            if ($res !== false) {
                                $this->contributions_types[] = $v;
                            } else {
                                Analog::log(
                                    'Contribution type #' . $v . ' does not exists!',
                                    Analog::WARNING
                                );
                            }
                        } else {
                            Analog::log(
                                '[AdvancedMembersList] Value for contribution type '
                                . 'filter should be an integer (' . gettype($v)
                                . ' given',
                                Analog::WARNING
                            );
                        }
                    }
                    break;
                case 'payments_types':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $this->payments_types = [];
                    $ptypes = new PaymentTypes(
                        $zdb,
                        $preferences,
                        $login
                    );
                    $ptlist = $ptypes->getList();

                    foreach ($value as $v) {
                        if (is_numeric($v)) {
                            if (isset($ptlist[$v])) {
                                $this->payments_types[] = $v;
                            } else {
                                Analog::log(
                                    'Payment type #' . $v . ' does not exists!',
                                    Analog::WARNING
                                );
                            }
                        } else {
                            Analog::log(
                                '[AdvancedMembersList] Value for payment type filter should be an '
                                . 'integer (' . gettype($v) . ' given',
                                Analog::WARNING
                            );
                        }
                    }
                    break;
                case 'free_search':
                    if (isset($this->free_search['empty']) && !isset($value['empty'])) {
                        unset($this->free_search['empty']);
                    }

                    if (static::isValidFreeSearch($value)) {
                        //should this happen?
                        $values = [$value];
                    } else {
                        $values = $value;
                    }

                    foreach ($values as $value) {
                        if (static::isValidFreeSearch($value)) {
                            $id = $value['idx'];

                            //handle value according to type
                            switch ($value['type']) {
                                case DynamicField::DATE:
                                    if ($value['search'] !== null && trim((string) $value['search']) !== '') {
                                        try {
                                            $value['search'] = $this->buildDate($value['search']);
                                        } catch (Throwable) {
                                            Analog::log(
                                                'Incorrect date format for ' . $value['field']
                                                . '! was: ' . $value['search'],
                                                Analog::WARNING
                                            );
                                        }
                                    }
                                    break;
                            }

                            $this->free_search[$id] = $value;
                        } else {
                            Analog::log(
                                '[AdvancedMembersList] bad construct for free filter',
                                Analog::WARNING
                            );
                        }
                    }
                    break;
                case 'contrib_dynamic':
                    if (is_array($value)) {
                        $this->contrib_dynamic = $value;
                    } else {
                        Analog::log(
                            '[AdvancedMembersList] Value for dynamic contribution fields filter should be an '
                            . 'array (' . gettype($value) . ' given',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'groups_search':
                    if (isset($this->groups_search['empty'])) {
                        unset($this->groups_search['empty']);
                    }
                    if (is_array($value)) {
                        if (
                            isset($value['group'])
                            && isset($value['idx'])
                        ) {
                            $id = $value['idx'];
                            unset($value['idx']);
                            $this->groups_search[$id] = $value;
                        } else {
                            Analog::log(
                                '[AdvancedMembersList] bad construct for group filter',
                                Analog::WARNING
                            );
                        }
                    } else {
                        Analog::log(
                            '[AdvancedMembersList] Value for group filter should be an '
                            . 'array (' . gettype($value) . ' given',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'groups_search_log_op':
                    if ($value == self::OP_AND || $value == self::OP_OR) {
                        $this->groups_search_log_op = $value;
                    } else {
                        Analog::log(
                            '[AdvancedMembersList] Value for group filter logical operator should be '
                            . ' in [0,1] (' . gettype($value) . '-> ' . $value . ' given )',
                            Analog::WARNING
                        );
                    }
                    break;
                default:
                    if (
                        str_starts_with($name, 'cds_')
                        || str_starts_with($name, 'cdsc_')
                    ) {
                        if (is_array($value) || trim((string) $value) !== '') {
                            $id = str_starts_with($name, 'cdsc_') ? substr($name, 5, strlen($name)) : substr($name, 4, strlen($name));
                            $dyn_field = DynamicField::loadFieldType($zdb, (int)$id);
                            if ($dyn_field instanceof \Galette\DynamicFields\Date) {
                                $value = $this->buildDate($value);
                            }
                            $this->contrib_dynamic[$id] = $value;
                        }
                    } else {
                        Analog::log(
                            '[AdvancedMembersList] Unable to set property `'
                            . $name . '`',
                            Analog::WARNING
                        );
                    }
                    break;
            }
        }
    }

    /**
     * Validate free search internal array
     *
     * @param array<string,mixed> $data Array to validate
     *
     * @return boolean
     */
    public static function isValidFreeSearch(array $data): bool
    {
        return isset($data['field'])
            && isset($data['search'])
            && isset($data['log_op'])
            && isset($data['qry_op'])
            && isset($data['idx'])
            && isset($data['type']);
    }
}
