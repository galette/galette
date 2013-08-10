<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members list advanced filters
 *
 * PHP version 5
 *
 * Copyright © 2012-2013 The Galette Team
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
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     0.73dev 2012-10-16
 */

namespace Galette\Filters;

use Analog\Analog as Analog;
use Galette\Entity\Status as Status;
use Galette\Entity\ContributionsTypes as ContributionsTypes;
use Galette\Entity\Contribution as Contribution;
use Galette\Repository\Members as Members;

/**
 * Members list filters and paginator
 *
 * @name      AdvancedMembersList
 * @category  Filters
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2012-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

class AdvancedMembersList extends MembersList
{

    const OP_AND = 0;
    const OP_OR = 1;

    const OP_EQUALS = 0;
    const OP_CONTAINS = 1;
    const OP_NOT_EQUALS = 2;
    const OP_NOT_CONTAINS = 3;
    const OP_STARTS_WITH = 4;
    const OP_ENDS_WITH = 5;

    private $_creation_date_begin;
    private $_creation_date_end;
    private $_modif_date_begin;
    private $_modif_date_end;
    private $_due_date_begin;
    private $_due_date_end;
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
        'free_search'
    );

    protected $virtuals_advancedmemberslist_fields = array(
        'rcreation_date_begin',
        'rcreation_date_end',
        'rmodif_date_begin',
        'rmodif_date_end',
        'rdue_date_begin',
        'rdue_date_end',
        'rcontrib_creation_date_begin',
        'rcontrib_creation_date_end',
        'rcontrib_begin_date_begin',
        'rcontrib_begin_date_end',
        'rcontrib_end_date_begin',
        'rcontrib_end_date_end'
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

    //an empty contributions dynamic field criteria to begin
    private $_contrib_dynamic = array(
        'empty' => array(
            'field'     => '',
            'search'    => '',
            'log_op'    => self::OP_AND,
            'qry_op'    => self::OP_EQUALS
        )
    );

    /**
     * Default constructor
     *
     * @param MembersList $simple A simple filter search to keep
     */
    public function __construct($simple = null)
    {
        parent::__construct();
        if ( $simple instanceof MembersList ) {
            foreach ( $this->pagination_fields as $pf ) {
                $this->$pf = $simple->$pf;
            }
            foreach ( $this->memberslist_fields as $mlf ) {
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
        if ( $this->_contrib_creation_date_begin != null
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

        $this->_contrib_dynamic = array(
            'empty' => array(
                'field'     => '',
                'search'    => '',
                'log_op'    => self::OP_AND,
                'qry_op'    => self::OP_EQUALS
            )
        );

    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrive
     *
     * @return object the called property
     */
    public function __get($name)
    {

        Analog::log(
            '[AdvancedMembersList] Getting property `' . $name . '`',
            Analog::DEBUG
        );

        if ( in_array($name, $this->pagination_fields)
            || in_array($name, $this->memberslist_fields)
        ) {
            return parent::__get($name);
        } else {
            if (in_array($name, $this->advancedmemberslist_fields)
                || in_array($name, $this->virtuals_advancedmemberslist_fields)
            ) {
                $rname = '_' . $name;
                switch ( $name ) {
                case 'creation_date_begin':
                case 'creation_date_end':
                case 'modif_date_begin':
                case 'modif_date_end':
                case 'due_date_begin':
                case 'due_date_end':
                case 'contrib_creation_date_begin':
                case 'contrib_creation_date_end':
                case 'contrib_begin_date_begin':
                case 'contrib_begin_date_end':
                case 'contrib_end_date_begin':
                case 'contrib_end_date_end':
                    try {
                        if ( $this->$rname !== null ) {
                            $d = new \DateTime($this->$rname);
                            return $d->format(_T("Y-m-d"));
                        }
                    } catch (\Exception $e) {
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
                case 'rcontrib_creation_date_begin':
                case 'rcontrib_creation_date_end':
                case 'rcontrib_begin_date_begin':
                case 'rcontrib_begin_date_end':
                case 'rcontrib_end_date_begin':
                case 'rcontrib_end_date_end':
                    //same as above, but raw format
                    $rname = '_' . substr($name, 1);
                    return $this->$rname;
                }
                return $this->$rname;
            } else {
                Analog::log(
                    '[AdvancedMembersList] Unable to get proprety `' .$name . '`',
                    Analog::WARNING
                );
            }
        }
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

        if ( in_array($name, $this->pagination_fields)
            || in_array($name, $this->memberslist_fields)
        ) {
            parent::__set($name, $value);
        } else {
            Analog::log(
                '[AdvancedMembersList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            $prop = '_' . $name;

            switch($name) {
            case 'creation_date_begin':
            case 'creation_date_end':
            case 'modif_date_begin':
            case 'modif_date_end':
            case 'due_date_begin':
            case 'due_date_end':
            case 'contrib_creation_date_begin':
            case 'contrib_creation_date_end':
            case 'contrib_begin_date_begin':
            case 'contrib_begin_date_end':
            case 'contrib_end_date_begin':
            case 'contrib_end_date_end':
                try {
                    $d = \DateTime::createFromFormat(_T("Y-m-d"), $value);
                    if ( $d === false ) {
                        throw new \Exception('Incorrect format');
                    }
                    $this->$prop = $d->format('Y-m-d');
                } catch ( \Exception $e ) {
                    Analog::log(
                        'Incorrect date format for ' . $name . '! was: ' . $value,
                        Analog::WARNING
                    );
                }
                break;
            case 'contrib_min_amount':
            case 'contrib_max_amount':
                if ( is_float($value) ) {
                    $this->$prop = $value;
                } else {
                    if ( $value !== null ) {
                        Analog::log(
                            'Incorrect amount for ' . $name  . '! ' .
                            'Should be a float (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                }
                break;
            case 'show_public_infos':
                if ( is_numeric($value) ) {
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
                if ( !is_array($value) ) {
                    $value = array($value);
                }
                $this->_status = array();
                foreach ( $value as $v ) {
                    if ( is_numeric($v) ) {
                        //check status existence
                        $s = new Status();
                        $res = $s->get($v);
                        if ( $res !== false ) {
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
                            .'integer (' . gettype($v) . ' given',
                            Analog::WARNING
                        );
                    }
                }
                break;
            case 'contributions_types':
                if ( !is_array($value) ) {
                    $value = array($value);
                }
                $this->_contributions_types = array();
                foreach ( $value as $v ) {
                    if ( is_numeric($v) ) {
                        //check type existence
                        $s = new ContributionsTypes();
                        $res = $s->get($v);
                        if ( $res !== false ) {
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
                if ( !is_array($value) ) {
                    $value = array($value);
                }
                $this->_payments_types = array();
                foreach ( $value as $v ) {
                    if ( is_numeric($v) ) {
                        if ( $v == Contribution::PAYMENT_OTHER
                            || $v == Contribution::PAYMENT_CASH
                            || $v == Contribution::PAYMENT_CREDITCARD
                            || $v == Contribution::PAYMENT_CHECK
                            || $v == Contribution::PAYMENT_TRANSFER
                            || $v == Contribution::PAYMENT_PAYPAL
                        ) {
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
                            .'integer (' . gettype($v) . ' given',
                            Analog::WARNING
                        );
                    }
                }
                break;
            case 'free_search':
                if ( isset($this->_free_search['empty']) ) {
                    unset($this->_free_search['empty']);
                }
                if ( is_array($value) ) {
                    if ( isset($value['field'])
                        && isset($value['search'])
                        && isset($value['log_op'])
                        && isset($value['qry_op'])
                        && isset($value['idx'])
                    ) {
                        $id = $value['idx'];
                        unset($value['idx']);
                        $this->_free_search[$id] = $value;
                    } else {
                        Analog::log(
                            '[AdvancedMembersList] bad construct for free filter',
                            Analog::WARNING
                        );
                    }
                } else {
                    Analog::log(
                        '[AdvancedMembersList] Value for free filter should be an '
                        .'array (' . gettype($value) . ' given',
                        Analog::WARNING
                    );
                }
                break;
            default:
                if ( substr($name, 0, 4) === 'cds_'
                    || substr($name, 0, 5) === 'cdsc_'
                ) {
                    if (is_array($value) || trim($value) !== '' ) {
                        if ( isset($this->_contrib_dynamic['empty']) ) {
                            unset($this->_contrib_dynamic['empty']);
                        }

                        $id = null;
                        if ( substr($name, 0, 5) === 'cdsc_' ) {
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
}
