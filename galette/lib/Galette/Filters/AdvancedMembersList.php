<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members list advanced filters
 *
 * PHP version 5
 *
 * Copyright © 2012-2022 The Galette Team
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
 * @category  Filters
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     0.73dev 2012-10-16
 */

namespace Galette\Filters;

use Throwable;
use Analog\Analog;
use Galette\Entity\Status;
use Galette\Entity\ContributionsTypes;
use Galette\Entity\Contribution;
use Galette\Repository\Members;
use Galette\DynamicFields\DynamicField;
use Galette\Repository\PaymentTypes;

/**
 * Members list filters and paginator
 *
 * @name      AdvancedMembersList
 * @category  Filters
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2022 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 *
 * @property string $creation_date_begin
 * @property string $creation_date_end
 * @property string $modif_date_begin
 * @property string $modif_date_end
 * @property string $due_date_begin
 * @property string $due_date_end
 * @property string $birth_date_begin
 * @property string $birth_date_end
 * @property boolean $show_public_infos
 * @property array|integer $status
 * @property string $contrib_creation_date_begin
 * @property string $contrib_creation_date_end
 * @property string $contrib_begin_date_begin
 * @property string $contrib_begin_date_end
 * @property string $contrib_end_date_begin
 * @property string $contrib_end_date_end
 * @property array $contributions_types
 * @property array $payments_types
 * @property integer $contrib_min_amount
 * @property integer $contrib_max_amount
 * @property string $contrib_dynamic
 * @property array $free_search
 * @property array $groups_search
 * @property integer $groups_search_log_op
 *
 * @property-read string $rcreation_date_begin
 * @property-read string $rcreation_date_end
 * @property-read string $rmodif_date_begin
 * @property-read string $rmodif_date_end
 * @property-read string $rdue_date_begin
 * @property-read string $rdue_date_end
 * @property-read string $rbirth_date_begin
 * @property-read string $rbirth_date_end
 * @property-read string $rcontrib_creation_date_begin
 * @property-read string $rcontrib_creation_date_end
 * @property-read string $rcontrib_begin_date_begin
 * @property-read string $rcontrib_begin_date_end
 * @property-read string $rcontrib_end_date_begin
 * @property-read string $rcontrib_end_date_end
 * @property-read array $search_fields
 */

class AdvancedMembersList extends MembersList
{
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

    private $_creation_date_begin;
    private $_creation_date_end;
    private $_modif_date_begin;
    private $_modif_date_end;
    private $_due_date_begin;
    private $_due_date_end;
    private $_birth_date_begin;
    private $_birth_date_end;
    private $_show_public_infos = Members::FILTER_DC_PUBINFOS;
    private $_status = array();
    private $_contrib_creation_date_begin;
    private $_contrib_creation_date_end;
    private $_contrib_begin_date_begin;
    private $_contrib_begin_date_end;
    private $_contrib_end_date_begin;
    private $_contrib_end_date_end;
    private $_contributions_types;
    private $_payments_types;
    private $_contrib_min_amount;
    private $_contrib_max_amount;

    protected $advancedmemberslist_fields = array(
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
    );

    protected $virtuals_advancedmemberslist_fields = array(
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
    );

    //an empty free search criteria to begin
    private $_free_search = array(
        'empty' => array(
            'field'     => '',
            'search'    => '',
            'log_op'    => self::OP_AND,
            'qry_op'    => self::OP_EQUALS
        )
    );

    //an empty group search criteria to begin
    private $_groups_search = array(
        'empty' => array(
            'group'    => '',
        )
    );

    //defaults to 'OR' for group search
    private $_groups_search_log_op = self::OP_OR;


    //an empty contributions dynamic field criteria to begin
    private $_contrib_dynamic = array();

    /**
     * Default constructor
     *
     * @param MembersList $simple A simple filter search to keep
     */
    public function __construct($simple = null)
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
    public function withinContributions()
    {
        if (
            $this->_contrib_creation_date_begin != null
            || $this->_contrib_creation_date_end != null
            || $this->_contrib_begin_date_begin != null
            || $this->_contrib_begin_date_end != null
            || $this->_contrib_end_date_begin != null
            || $this->_contrib_begin_date_end != null
            || $this->_contrib_min_amount != null
            || $this->_contrib_max_amount != null
            || count($this->_contrib_dynamic) > 0
            || count($this->_contributions_types) > 0
            || count($this->_payments_types) > 0
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
    public function reinit()
    {
        parent::reinit();

        $this->_creation_date_begin = null;
        $this->_creation_date_end = null;
        $this->_modif_date_begin = null;
        $this->_modif_date_end = null;
        $this->_due_date_begin = null;
        $this->_due_date_end = null;
        $this->_birth_date_begin = null;
        $this->_birth_date_end = null;
        $this->_show_public_infos = Members::FILTER_DC_PUBINFOS;
        $this->_status = array();

        $this->_contrib_creation_date_begin = null;
        $this->_contrib_creation_date_end = null;
        $this->_contrib_begin_date_begin = null;
        $this->_contrib_begin_date_end = null;
        $this->_contrib_end_date_begin = null;
        $this->_contrib_begin_date_end = null;
        $this->_contributions_types = array();
        $this->_payments_types = array();

        $this->_free_search = array(
            'empty' => array(
                'field'     => '',
                'search'    => '',
                'log_op'    => self::OP_AND,
                'qry_op'    => self::OP_EQUALS
            )
        );

        $this->_contrib_dynamic = array();

        $this->_groups_search = array(
            'empty' => array(
                'group'     => '',
            )
        );

        $this->_groups_search_log_op = self::OP_OR;
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return object the called property
     */
    public function __get($name)
    {

        Analog::log(
            '[AdvancedMembersList] Getting property `' . $name . '`',
            Analog::DEBUG
        );

        if (
            in_array($name, $this->pagination_fields)
            || in_array($name, $this->memberslist_fields)
        ) {
            return parent::__get($name);
        } else {
            if (
                in_array($name, $this->advancedmemberslist_fields)
                || in_array($name, $this->virtuals_advancedmemberslist_fields)
            ) {
                $rname = '_' . $name;
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
                        try {
                            if ($this->$rname !== null) {
                                $d = new \DateTime($this->$rname);
                                return $d->format(__("Y-m-d"));
                            }
                        } catch (Throwable $e) {
                            //oops, we've got a bad date :/
                            Analog::log(
                                'Bad date (' . $this->$rname . ') | ' .
                                $e->getMessage(),
                                Analog::INFO
                            );
                            return $this->$rname;
                        }
                        break;
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
                        //same as above, but raw format
                        $rname = '_' . substr($name, 1);
                        return $this->$rname;
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
                return $this->$rname;
            } else {
                Analog::log(
                    '[AdvancedMembersList] Unable to get proprety `' . $name . '`',
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
     * @return object the called property
     */
    public function __isset($name)
    {
        if (
            in_array($name, $this->pagination_fields)
            || in_array($name, $this->memberslist_fields)
        ) {
            return true;
        } else {
            if (
                in_array($name, $this->advancedmemberslist_fields)
                || in_array($name, $this->virtuals_advancedmemberslist_fields)
            ) {
                return true;
            }
        }

        return false;
    }
    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param object $value a relevant value for the property
     *
     * @return void
     */
    public function __set($name, $value)
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

            $prop = '_' . $name;

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
                    if ($value !== null && trim($value) !== '') {
                        try {
                            $d = \DateTime::createFromFormat(__("Y-m-d"), $value);
                            if ($d === false) {
                                throw new \Exception('Incorrect format');
                            }
                            $this->$prop = $d->format('Y-m-d');
                        } catch (Throwable $e) {
                            Analog::log(
                                'Incorrect date format for ' . $name .
                                '! was: ' . $value,
                                Analog::WARNING
                            );
                        }
                    }
                    break;
                case 'contrib_min_amount':
                case 'contrib_max_amount':
                    if (is_float($value)) {
                        $this->$prop = $value;
                    } else {
                        if ($value !== null) {
                            Analog::log(
                                'Incorrect amount for ' . $name . '! ' .
                                'Should be a float (' . gettype($value) . ' given)',
                                Analog::WARNING
                            );
                        }
                    }
                    break;
                case 'show_public_infos':
                    if (is_numeric($value)) {
                        $this->$prop = $value;
                    } else {
                        Analog::log(
                            '[AdvancedMembersList] Value for property `' . $name .
                            '` should be an integer (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'status':
                    if (!is_array($value)) {
                        $value = array($value);
                    }
                    $this->_status = array();
                    foreach ($value as $v) {
                        if (is_numeric($v)) {
                            //check status existence
                            $s = new Status($zdb);
                            $res = $s->get($v);
                            if ($res !== false) {
                                $this->_status[] = $v;
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
                        $value = array($value);
                    }
                    $this->_contributions_types = array();
                    foreach ($value as $v) {
                        if (is_numeric($v)) {
                            //check type existence
                            $s = new ContributionsTypes($zdb);
                            $res = $s->get($v);
                            if ($res !== false) {
                                $this->_contributions_types[] = $v;
                            } else {
                                Analog::log(
                                    'Contribution type #' . $v . ' does not exists!',
                                    Analog::WARNING
                                );
                            }
                        } else {
                            Analog::log(
                                '[AdvancedMembersList] Value for contribution type '
                                . 'filter should be an integer (' . gettype($v) .
                                ' given',
                                Analog::WARNING
                            );
                        }
                    }
                    break;
                case 'payments_types':
                    if (!is_array($value)) {
                        $value = array($value);
                    }
                    $this->_payments_types = array();
                    $ptypes = new PaymentTypes(
                        $zdb,
                        $preferences,
                        $login
                    );
                    $ptlist = $ptypes->getList();

                    foreach ($value as $v) {
                        if (is_numeric($v)) {
                            if (isset($ptlist[$v])) {
                                $this->_payments_types[] = $v;
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
                    if (isset($this->_free_search['empty']) && !isset($value['empty'])) {
                        unset($this->_free_search['empty']);
                    }

                    if ($this->isValidFreeSearch($value)) {
                        //should this happen?
                        $values = [$value];
                    } else {
                        $values = $value;
                    }

                    foreach ($values as $value) {
                        if ($this->isValidFreeSearch($value)) {
                            $id = $value['idx'];

                            //handle value according to type
                            switch ($value['type']) {
                                case DynamicField::DATE:
                                    if ($value['search'] !== null && trim($value['search']) !== '') {
                                        try {
                                            $d = \DateTime::createFromFormat(__("Y-m-d"), $value['search']);
                                            if ($d === false) {
                                                throw new \Exception('Incorrect format');
                                            }
                                            $value['search'] = $d->format('Y-m-d');
                                        } catch (Throwable $e) {
                                            Analog::log(
                                                'Incorrect date format for ' . $value['field'] .
                                                '! was: ' . $value['search'],
                                                Analog::WARNING
                                            );
                                        }
                                    }
                                    break;
                            }

                            $this->_free_search[$id] = $value;
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
                        $this->_contrib_dynamic = $value;
                    } else {
                        Analog::log(
                            '[AdvancedMembersList] Value for dynamic contribution fields filter should be an '
                            . 'array (' . gettype($value) . ' given',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'groups_search':
                    if (isset($this->_groups_search['empty'])) {
                        unset($this->_groups_search['empty']);
                    }
                    if (is_array($value)) {
                        if (
                            isset($value['group'])
                            && isset($value['idx'])
                        ) {
                            $id = $value['idx'];
                            unset($value['idx']);
                            $this->_groups_search[$id] = $value;
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
                        $this->_groups_search_log_op = $value;
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
                        substr($name, 0, 4) === 'cds_'
                        || substr($name, 0, 5) === 'cdsc_'
                    ) {
                        if (is_array($value) || trim($value) !== '') {
                            $id = null;
                            if (substr($name, 0, 5) === 'cdsc_') {
                                $id = substr($name, 5, strlen($name));
                            } else {
                                $id = substr($name, 4, strlen($name));
                            }
                            $this->_contrib_dynamic[$id] = $value;
                        }
                    } else {
                        Analog::log(
                            '[AdvancedMembersList] Unable to set proprety `' .
                            $name . '`',
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
     * @param array $data Array to validate
     *
     * @return boolean
     */
    public static function isValidFreeSearch($data)
    {
        if (!is_array($data)) {
            Analog::log(
                '[AdvancedMembersList] Value for free filter should be an '
                . 'array (' . gettype($data) . ' given',
                Analog::WARNING
            );
            return false;
        }
        return isset($data['field'])
            && isset($data['search'])
            && isset($data['log_op'])
            && isset($data['qry_op'])
            && isset($data['idx'])
            && isset($data['type']);
    }
}
