<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members class
 *
 * PHP version 5
 *
 * Copyright © 2009-2014 The Galette Team
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
 * @category  Repository
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-28
 */

namespace Galette\Repository;

use Galette\Entity\DynamicFields;

use Analog\Analog;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\Db\Sql\Predicate\Operator;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\Transaction;
use Galette\Entity\Reminder;
use Galette\Filters\MembersList;
use Galette\Filters\AdvancedMembersList;
use Galette\Core\Picture;
use Galette\Entity\Group;
use Galette\Repository\Groups;
use Galette\Entity\Status;

/**
 * Members class for galette
 *
 * @name Members
 * @category  Repository
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class Members
{
    const TABLE = Adherent::TABLE;
    const PK = Adherent::PK;

    const ALL_ACCOUNTS = 0;
    const ACTIVE_ACCOUNT = 1;
    const INACTIVE_ACCOUNT = 2;

    const SHOW_LIST = 0;
    const SHOW_PUBLIC_LIST = 1;
    const SHOW_ARRAY_LIST = 2;
    const SHOW_STAFF = 3;
    const SHOW_MANAGED = 4;
    const SHOW_EXPORT = 5;

    const FILTER_NAME = 0;
    const FILTER_ADDRESS = 1;
    const FILTER_MAIL = 2;
    const FILTER_JOB = 3;
    const FILTER_INFOS = 4;
    const FILTER_DC_EMAIL = 5;
    const FILTER_W_EMAIL = 6;
    const FILTER_WO_EMAIL = 7;
    const FILTER_COMPANY_NAME = 8;
    const FILTER_DC_PUBINFOS = 9;
    const FILTER_W_PUBINFOS = 10;
    const FILTER_WO_PUBINFOS = 11;
    const FILTER_NUMBER = 12;

    const MEMBERSHIP_ALL = 0;
    const MEMBERSHIP_UP2DATE = 3;
    const MEMBERSHIP_NEARLY = 1;
    const MEMBERSHIP_LATE = 2;
    const MEMBERSHIP_NEVER = 4;
    const MEMBERSHIP_STAFF = 5;
    const MEMBERSHIP_ADMIN = 6;
    const MEMBERSHIP_NONE = 7;

    const ORDERBY_NAME = 0;
    const ORDERBY_NICKNAME = 1;
    const ORDERBY_STATUS = 2;
    const ORDERBY_FEE_STATUS = 3;
    const ORDERBY_MODIFDATE = 4;
    const ORDERBY_ID = 5;

    const NON_STAFF_MEMBERS = 30;

    private $filters = false;
    private $count = null;
    private $errors = array();

    /**
     * Default constructor
     *
     * @param MembersList $filters Filtering
     */
    public function __construct($filters = null)
    {
        if ($filters === null) {
            $this->filters = new MembersList();
        } else {
            $this->filters = $filters;
        }
    }

    /**
     * Get staff members list
     *
     * @param bool    $as_members return the results as an array of
     *                               Member object.
     * @param array   $fields     field(s) name(s) to get. Should be a string or
     *                               an array. If null, all fields will be
     *                               returned
     * @param boolean $count      true if we want to count members
     * @param boolean $limit      true to LIMIT query
     *
     * @return Adherent[]|ResultSet
     */
    public function getStaffMembersList(
        $as_members = false,
        $fields = null,
        $count = true,
        $limit = true
    ) {
        return $this->getMembersList(
            $as_members,
            $fields,
            $count,
            true,
            false,
            $limit
        );
    }

    /**
     * Get managed members list (for groups managers)
     *
     * @param bool    $as_members return the results as an array of
     *                               Member object.
     * @param array   $fields     field(s) name(s) to get. Should be a string or
     *                               an array. If null, all fields will be
     *                               returned
     * @param boolean $count      true if we want to count members
     * @param boolean $limit      true to LIMIT query
     *
     * @return Adherent[]|ResultSet
     */
    public function getManagedMembersList(
        $as_members = false,
        $fields = null,
        $count = true,
        $limit = true
    ) {
        return $this->getMembersList(
            $as_members,
            $fields,
            $count,
            false,
            true,
            $limit
        );
    }
    /**
     * Get members list
     *
     * @param bool    $as_members return the results as an array of
     *                               Member object.
     * @param array   $fields     field(s) name(s) to get. Should be a string or
     *                               an array. If null, all fields will be
     *                               returned
     * @param boolean $count      true if we want to count members
     * @param boolean $staff      true if we want only staff members
     * @param boolean $managed    true if we want only managed groups
     * @param boolean $limit      true if we want records pagination
     * @param boolean $export     true if we are exporting
     *
     * @return Adherent[]|ResultSet
     */
    public function getMembersList(
        $as_members = false,
        $fields = null,
        $count = true,
        $staff = false,
        $managed = false,
        $limit = true,
        $export = false
    ) {
        global $zdb;

        try {
            $_mode = self::SHOW_LIST;
            if ($staff !== false) {
                $_mode = self::SHOW_STAFF;
            }
            if ($managed !== false) {
                $_mode = self::SHOW_MANAGED;
            }
            if ($export !== false) {
                $_mode = self::SHOW_EXPORT;
            }

            $select = $this->buildSelect(
                $_mode,
                $fields,
                false,
                $count
            );

            //add limits to retrieve only relavant rows
            if ($limit === true) {
                $this->filters->setLimit($select);
            }

            $rows = $zdb->execute($select);
            $this->filters->query = $zdb->query_string;

            $members = array();
            if ($as_members) {
                $deps = array(
                    'picture'   => false,
                    'groups'    => false
                );
                foreach ($rows as $row) {
                    $members[] = new Adherent($zdb, $row, $deps);
                }
            } else {
                $members = $rows;
            }
            return $members;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list members | ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }

    /**
     * Remove specified members
     *
     * @param integer|array $ids Members identifiers to delete
     *
     * @return boolean
     */
    public function removeMembers($ids)
    {
        global $zdb, $hist;

        $list = array();
        if (is_numeric($ids)) {
            //we've got only one identifier
            $list[] = $ids;
        } else {
            $list = $ids;
        }

        if (is_array($list)) {
            try {
                $zdb->connection->beginTransaction();

                //Retrieve some informations
                $select = $zdb->select(self::TABLE);
                $select->columns(
                    array(self::PK, 'nom_adh', 'prenom_adh')
                )->where->in(self::PK, $list);

                $results = $zdb->execute($select);

                $infos = null;
                foreach ($results as $member) {
                    $str_adh = $member->id_adh . ' (' . $member->nom_adh . ' ' .
                        $member->prenom_adh . ')';
                    $infos .=  $str_adh . "\n";

                    $p = new Picture($member->id_adh);
                    if ($p->hasPicture()) {
                        if (!$p->delete(false)) {
                            Analog::log(
                                'Unable to delete picture for member ' . $str_adh,
                                Analog::ERROR
                            );
                            throw new \Exception(
                                'Unable to delete picture for member ' .
                                $str_adh
                            );
                        } else {
                            $hist->add(
                                _T("Member Picture deleted"),
                                $str_adh
                            );
                        }
                    }
                }

                //delete contributions
                $del_qry = $zdb->delete(Contribution::TABLE);
                $del_qry->where->in(
                    self::PK,
                    $list
                );
                $del = $zdb->execute($del_qry);

                //delete transactions
                $del_qry = $zdb->delete(Transaction::TABLE);
                $del_qry->where->in(self::PK, $list);
                $del = $zdb->execute($del_qry);

                //delete groups membership/mamagmentship
                $del = Groups::removeMemberFromGroups((int)$member->id_adh);

                //delete reminders
                $del_qry = $zdb->delete(Reminder::TABLE);
                $del_qry->where->in(
                    'reminder_dest',
                    $list
                );
                $del = $zdb->execute($del_qry);

                //delete members
                $del_qry = $zdb->delete(self::TABLE);
                $del_qry->where->in(
                    self::PK,
                    $list
                );
                $del = $zdb->execute($del_qry);

                //commit all changes
                $zdb->connection->commit();

                //add an history entry
                $hist->add(
                    _T("Delete members cards, transactions and dues"),
                    $infos
                );

                return true;
            } catch (\Exception $e) {
                $zdb->connection->rollBack();
                if ($e instanceof \Zend_Db_Statement_Exception
                    && $e->getCode() == 23000
                ) {
                    Analog::log(
                        'Member still have existing dependencies in the ' .
                        'database, maybe a mailing or some content from a ' .
                        'plugin. Please remove dependencies before trying ' .
                        'to remove him.',
                        Analog::ERROR
                    );
                    $this->errors[] = _T("Cannot remove a member who still have dependencies (mailings, ...)");
                } else {
                    Analog::log(
                        'Unable to delete selected member(s) |' .
                        $e->getMessage(),
                        Analog::ERROR
                    );
                }
                return false;
            }
        } else {
            //not numeric and not an array: incorrect.
            Analog::log(
                'Asking to remove members, but without providing an array or a single numeric value.',
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Get members list
     *
     * @param bool  $as_members return the results as an array of
     *                          Member object.
     * @param array $fields     field(s) name(s) to get. Should be a string or
     *                          an array. If null, all fields will be
     *                          returned
     *
     * @return Adherent[]|ResultSet
     */
    public function getList($as_members = false, $fields = null)
    {
        return $this->getMembersList(
            $as_members,
            $fields,
            false,
            false,
            false,
            false,
            false
        );
    }

    /**
     * Get members list with public informations available
     *
     * @param boolean $with_photos get only members which have uploaded a
     *                                photo (for trombinoscope)
     * @param array   $fields      fields list
     *
     * @return Adherent[]
     */
    public function getPublicList($with_photos, $fields)
    {
        global $zdb;

        try {
            $select = $this->buildSelect(
                self::SHOW_PUBLIC_LIST,
                $fields,
                $with_photos
            );

            if ($this->filters) {
                $select->order($this->buildOrderClause($fields));
            }

            $this->proceedCount($select);

            $this->filters->setLimit($select);

            $results = $zdb->execute($select);
            $members = array();
            foreach ($results as $row) {
                $deps = array(
                    'groups'    => false,
                    'dues'      => false,
                    'picture'   => $with_photos
                );
                $members[] = new Adherent($zdb, $row, $deps);
            }
            return $members;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list members with public informations (photos: '
                . $with_photos . ') | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Get list of members that has been selected
     *
     * @param array   $ids         an array of members id that has been selected
     * @param array   $orderby     SQL order clause (optionnal)
     * @param boolean $with_photos Should photos be loaded?
     * @param boolean $as_members  Return Adherent[] or simple ResultSet
     * @param array   $fields      Fields to use
     * @param boolean $export      True if we are exporting
     * @param boolean $dues        True if load dues as Adherent dependency
     * @param boolean $parent      True if load parent as Adherent dependency
     *
     * @return Adherent[]
     */
    public function getArrayList(
        $ids,
        $orderby = null,
        $with_photos = false,
        $as_members = true,
        $fields = null,
        $export = false,
        $dues = false,
        $parent = false
    ) {
        global $zdb;

        if (!is_array($ids) || count($ids) < 1) {
            Analog::log('No member selected for labels.', Analog::INFO);
            return false;
        }

        try {
            $damode = self::SHOW_ARRAY_LIST;
            if ($export === true) {
                $damode = self::SHOW_EXPORT;
            }
            $select = $this->buildSelect(
                $damode,
                $fields,
                false,
                false
            );
            $select->where->in('a.' . self::PK, $ids);
            if ($orderby != null && count($orderby) > 0) {
                if (is_array($orderby)) {
                    foreach ($orderby as $o) {
                        $select->order($o);
                    }
                } else {
                    $select->order($orderby);
                }
            }

            $results = $zdb->execute($select);

            $members = array();
            foreach ($results as $o) {
                $deps = array(
                    'picture'   => $with_photos,
                    'groups'    => false,
                    'dues'      => $dues,
                    'parent'    => $parent
                );
                if ($as_members === true) {
                    $members[] = new Adherent($zdb, $o, $deps);
                } else {
                    $members[] = $o;
                }
            }
            return $members;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot load members form ids array | ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }

    /**
     * Builds the SELECT statement
     *
     * @param int   $mode   the current mode (see self::SHOW_*)
     * @param array $fields fields list to retrieve
     * @param bool  $photos true if we want to get only members with photos
     *                      Default to false, only relevant for SHOW_PUBLIC_LIST
     * @param bool  $count  true if we want to count members, defaults to false
     *
     * @return Select SELECT statement
     */
    private function buildSelect($mode, $fields, $photos, $count = false)
    {
        global $zdb, $login;

        try {
            $fieldsList = ( $fields != null )
                            ? (( !is_array($fields) || count($fields) < 1 ) ? (array)'*'
                            : $fields) : (array)'*';

            $select = $zdb->select(self::TABLE, 'a');
            $select->columns($fieldsList);

            $select->quantifier('DISTINCT');

            switch ($mode) {
                case self::SHOW_STAFF:
                case self::SHOW_LIST:
                case self::SHOW_ARRAY_LIST:
                    $select->join(
                        array('p' => PREFIX_DB . Status::TABLE),
                        'a.' . Status::PK . '=p.' . Status::PK,
                        array()
                    );
                    break;
                case self::SHOW_EXPORT:
                    //basically the same as above, but without any fields
                    $select->join(
                        array('p' => PREFIX_DB . Status::TABLE),
                        'a.' . Status::PK . '=p.' . Status::PK,
                        array()
                    );
                    break;
                case self::SHOW_MANAGED:
                    $select->join(
                        array('p' => PREFIX_DB . Status::TABLE),
                        'a.' . Status::PK . '=p.' . Status::PK
                    )->join(
                        array('gr' => PREFIX_DB . Group::GROUPSUSERS_TABLE),
                        'a.' . Adherent::PK . '=gr.' . Adherent::PK,
                        array()
                    )->join(
                        array('m' => PREFIX_DB . Group::GROUPSMANAGERS_TABLE),
                        'gr.' . Group::PK . '=m.' . Group::PK,
                        array()
                    )->where('m.' . Adherent::PK . ' = ' . $login->id);
                    break;
                case self::SHOW_PUBLIC_LIST:
                    if ($photos) {
                        $select->join(
                            array('p' => PREFIX_DB . Picture::TABLE),
                            'a.' . self::PK . '= p.' . self::PK
                        );
                    }
                    break;
            }

            //check for contributions filtering
            if ($this->filters instanceof AdvancedMembersList
                && $this->filters->withinContributions()
            ) {
                $select->join(
                    array('ct' => PREFIX_DB . Contribution::TABLE),
                    'ct.' . self::PK . '=a.' . self::PK,
                    array(),
                    $select::JOIN_LEFT
                );
            }

            //check if there are dynamic fields in filter
            $hasDf = false;
            $hasCdf = false;
            $dfs = array();
            $cdfs = array();

            if ($this->filters instanceof AdvancedMembersList
                && $this->filters->free_search
                && count($this->filters->free_search) > 0
                && !isset($this->filters->free_search['empty'])
            ) {
                $free_searches = $this->filters->free_search;
                foreach ($free_searches as $fs) {
                    if (strpos($fs['field'], 'dyn_') === 0) {
                        // simple dynamic fields
                        $hasDf = true;
                        $dfs[] = str_replace('dyn_', '', $fs['field']);
                    }
                    if (strpos($fs['field'], 'dync_') === 0) {
                        // choice dynamic fields
                        $hasCdf = true;
                        $cdfs[] = str_replace('dync_', '', $fs['field']);
                    }
                }
            }

            //check if there are dynamic fields for contributions in filter
            $hasDfc = false;
            $hasCdfc = false;
            $cdfcs = array();

            if ($this->filters instanceof AdvancedMembersList
                && $this->filters->withinContributions()
            ) {
                if ($this->filters->contrib_dynamic
                    && count($this->filters->contrib_dynamic) > 0
                    && !isset($this->filters->contrib_dynamic['empty'])
                ) {
                    $hasDfc = true;

                    //check if there are dynamic fields in the filter
                    foreach ($this->filters->contrib_dynamic as $k => $cd) {
                        if (is_array($cd)) {
                            $hasCdfc = true;
                            $cdfcs[] = $k;
                        }
                    }
                }
            }

            if ($hasDfc === true || $hasCdfc === true) {
                $select->join(
                    array('dfc' => PREFIX_DB . DynamicFields::TABLE),
                    'dfc.item_id=ct.' . Contribution::PK,
                    array(),
                    $select::JOIN_LEFT
                );
            }

            // simple dynamic fields
            if ($hasDf === true) {
                foreach ($dfs as $df) {
                    $subselect = $zdb->select(DynamicFields::TABLE, 'df');
                    $subselect->columns(
                        [
                            'item_id' => 'item_id',
                            'val' => 'field_val'
                        ]
                    );
                    $subselect->where('df.field_form = \'adh\'');
                    $subselect->where('df.field_id = ' . $df);
                    $select->join(
                        array('df' . $df => $subselect),
                        'a.id_adh = df' . $df . '.item_id',
                        array(),
                        $select::JOIN_LEFT
                    );
                }
            }

            // choice dynamic fields
            if ($hasCdf === true || $hasCdfc === true) {
                $cdf_field = 'cdf.id';
                if (TYPE_DB === 'pgsql') {
                    $cdf_field .= '::text';
                }
                foreach ($cdfs as $cdf) {
                    $subselect = $zdb->select(DynamicFields::TABLE, 'df');
                    $subselect->columns(array('item_id'));
                    $subselect->join(
                        array('dfc' . $cdf  => DynamicFields::getFixedValuesTableName($cdf, true)),
                        "df.field_val=id",
                        array('val'),
                        $select::JOIN_LEFT
                    );
                    $subselect->where('df.field_form = \'adh\'');
                    $subselect->where('df.field_id = ' . $cdf);
                    $select->join(
                        array('df' . $cdf => $subselect),
                        'a.id_adh = df' . $cdf . '.item_id',
                        array(),
                        $select::JOIN_LEFT
                    );
                }

                $cdf_field = 'cdfc.id';
                if (TYPE_DB === 'pgsql') {
                    $cdf_field .= '::text';
                }
                foreach ($cdfcs as $cdf) {
                    $rcdf_field = str_replace(
                        'cdfc.',
                        'cdfc' . $cdf . '.',
                        $cdf_field
                    );
                    $select->join(
                        array('cdfc' . $cdf => DynamicFields::getFixedValuesTableName($cdf, true)),
                        $rcdf_field . '=dfc.field_val',
                        array(),
                        $select::JOIN_LEFT
                    );
                }
            }

            if ($mode == self::SHOW_LIST || $mode == self::SHOW_MANAGED) {
                if ($this->filters !== false) {
                    $this->buildWhereClause($select);
                }
                $select->order($this->buildOrderClause($fields));
            } elseif ($mode == self::SHOW_PUBLIC_LIST) {
                $select->where(
                    array(
                        new PredicateSet(
                            array(
                                new Operator(
                                    'date_echeance',
                                    '>=',
                                    date('Y-m-d')
                                ),
                                new Operator(
                                    'bool_exempt_adh',
                                    '=',
                                    new Expression('true')
                                )
                            ),
                            PredicateSet::OP_OR
                        ),
                        new PredicateSet(
                            array(
                                new Operator(
                                    'bool_display_info',
                                    '=',
                                    new Expression('true')
                                ),
                                new Operator(
                                    'activite_adh',
                                    '=',
                                    new Expression('true')
                                )
                            ),
                            PredicateSet::OP_AND
                        )
                    )
                );
            }

            if ($mode === self::SHOW_STAFF) {
                $select->where->lessThan(
                    'p.priorite_statut',
                    self::NON_STAFF_MEMBERS
                );
            }

            if ($count) {
                $this->proceedCount($select);
            }

            //Fix for #687, but only for MySQL (break on PostgreSQL)
            //$select->group('a.' . Adherent::PK);

            return $select;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot build SELECT clause for members | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Count members from the query
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function proceedCount($select)
    {
        global $zdb;

        try {
            $countSelect = clone $select;
            $countSelect->reset($countSelect::COLUMNS);
            $countSelect->reset($countSelect::ORDER);
            $countSelect->reset($countSelect::HAVING);
            $countSelect->columns(
                array(
                    'count' => new Expression('count(DISTINCT a.' . self::PK . ')')
                )
            );

            $have = $select->having;
            if ($have->count() > 0) {
                foreach ($have->getPredicates() as $h) {
                    $countSelect->where($h);
                }
            }

            $results = $zdb->execute($countSelect);

            $this->count = $results->current()->count;
            if (isset($this->filters) && $this->count > 0) {
                $this->filters->setCounter($this->count);
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot count members | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Builds the order clause
     *
     * @param array $fields Fields list to ensure ORDER clause
     *                      references selected fields. Optionnal.
     *
     * @return string SQL ORDER clause
     */
    private function buildOrderClause($fields = null)
    {
        $order = array();

        switch ($this->filters->orderby) {
            case self::ORDERBY_NICKNAME:
                if ($this->canOrderBy('pseudo_adh', $fields)) {
                    $order[] = 'pseudo_adh ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_STATUS:
                if ($this->canOrderBy('priorite_statut', $fields)) {
                    $order[] = 'priorite_statut ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_MODIFDATE:
                if ($this->canOrderBy('date_modif_adh', $fields)) {
                    $order[] = 'date_modif_adh ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_FEE_STATUS:
                if ($this->canOrderBy('bool_exempt_adh', $fields)) {
                    $order[] = 'bool_exempt_adh ' . $this->filters->getDirection();
                }

                if ($this->canOrderBy('date_echeance', $fields)) {
                    $order[] = 'date_echeance ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_ID:
                if ($this->canOrderBy('id_adh', $fields)) {
                    $order[] = 'id_adh ' . $this->filters->getDirection();
                }
                break;
        }

        //anyways, we want to order by firstname, lastname
        if ($this->canOrderBy('nom_adh', $fields)) {
            $order[] = 'nom_adh ' . $this->filters->getDirection();
        }
        if ($this->canOrderBy('prenom_adh', $fields)) {
            $order[] = 'prenom_adh ' . $this->filters->getDirection();
        }
        return $order;
    }

    /**
     * Is field allowed to order? it shoulsd be present in
     * provided fields list (those that are SELECT'ed).
     *
     * @param string $field_name Field name to order by
     * @param array  $fields     SELECTE'ed fields
     *
     * @return boolean
     */
    private function canOrderBy($field_name, $fields)
    {
        if (!is_array($fields)) {
            return true;
        } elseif (in_array($field_name, $fields)) {
            return true;
        } else {
            Analog::log(
                'Trying to order by ' . $field_name  . ' while it is not in ' .
                'selected fields.',
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Builds where clause, for filtering on simple list mode
     *
     * @param Select $select Original select
     *
     * @return string SQL WHERE clause
     */
    private function buildWhereClause($select)
    {
        global $zdb, $login;

        try {
            if ($this->filters->email_filter == self::FILTER_W_EMAIL) {
                $select->where('email_adh != \'\'');
            }
            if ($this->filters->email_filter == self::FILTER_WO_EMAIL) {
                $select->where('(email_adh = \'\' OR email_adh IS NULL)');
            }

            if ($this->filters->filter_str != '') {
                $token = $zdb->platform->quoteValue(
                    '%' . strtolower($this->filters->filter_str) . '%'
                );
                switch ($this->filters->field_filter) {
                    case self::FILTER_NAME:
                        if (TYPE_DB === 'pgsql') {
                            $sep = " || ' ' || ";
                            $pre = '';
                            $post = '';
                        } else {
                            $sep = ', " ", ';
                            $pre = 'CONCAT(';
                            $post=')';
                        }
                        //$sep = ( TYPE_DB === 'pgsql' ) ? " || ' ' || " : ', " ", ';
                        $select->where(
                            '(' .
                            $pre . 'LOWER(nom_adh)' . $sep .
                            'LOWER(prenom_adh)' . $sep .
                            'LOWER(pseudo_adh)' . $post  . ' LIKE ' .
                            $token
                            . ' OR ' .
                            $pre . 'LOWER(prenom_adh)' . $sep .
                            'LOWER(nom_adh)' . $sep .
                            'LOWER(pseudo_adh)' . $post  . ' LIKE ' .
                            $token
                            . ')'
                        );
                        break;
                    case self::FILTER_COMPANY_NAME:
                        $select->where(
                            'LOWER(societe_adh) LIKE ' .
                            $token
                        );
                        break;
                    case self::FILTER_ADDRESS:
                        $select->where(
                            '(' .
                            'LOWER(adresse_adh) LIKE ' . $token
                            . ' OR ' .
                            'LOWER(adresse2_adh) LIKE ' . $token
                            . ' OR ' .
                            'cp_adh LIKE ' . $token
                            . ' OR ' .
                            'LOWER(ville_adh) LIKE ' . $token
                            . ' OR ' .
                            'LOWER(pays_adh) LIKE ' . $token
                            . ')'
                        );
                        break;
                    case self::FILTER_MAIL:
                        $select->where(
                            '(' .
                            'LOWER(email_adh) LIKE ' . $token
                            . ' OR ' .
                            'LOWER(url_adh) LIKE ' . $token
                            . ' OR ' .
                            'LOWER(msn_adh) LIKE ' . $token
                            . ' OR ' .
                            'LOWER(icq_adh) LIKE ' . $token
                            . ' OR ' .
                            'LOWER(jabber_adh) LIKE ' . $token
                            . ')'
                        );
                        break;
                    case self::FILTER_JOB:
                        $select->where(
                            'LOWER(prof_adh) LIKE ' . $token
                        );
                        break;
                    case self::FILTER_INFOS:
                        $more = '';
                        if ($login->isAdmin() || $login->isStaff()) {
                            $more = ' OR LOWER(info_adh) LIKE ' . $token;
                        }
                        $select->where(
                            '(LOWER(info_public_adh) LIKE ' .
                            $token . $more . ')'
                        );
                        break;
                    case self::FILTER_NUMBER:
                        $select->where->equalTo('a.id_adh', $this->filters->filter_str);
                        break;
                }
            }

            if ($this->filters->membership_filter) {
                switch ($this->filters->membership_filter) {
                    case self::MEMBERSHIP_NEARLY:
                        $now = new \DateTime();
                        $duedate = new \DateTime();
                        $duedate->modify('+1 month');
                        $select->where
                            ->greaterThan(
                                'date_echeance',
                                $now->format('Y-m-d')
                            )->lessThan(
                                'date_echeance',
                                $duedate->format('Y-m-d')
                            );
                        break;
                    case self::MEMBERSHIP_LATE:
                        $select->where
                            ->lessThan(
                                'date_echeance',
                                date('Y-m-d', time())
                            )->equalTo('bool_exempt_adh', new Expression('false'));
                        break;
                    case self::MEMBERSHIP_UP2DATE:
                        $select->where(
                            '(' . 'date_echeance > \'' . date('Y-m-d', time())
                            . '\' OR bool_exempt_adh=true)'
                        );
                        break;
                    case self::MEMBERSHIP_NEVER:
                        $select->where('date_echeance IS NULL')
                            ->where('bool_exempt_adh = false');
                        break;
                    case self::MEMBERSHIP_STAFF:
                        $select->where->lessThan(
                            'p.priorite_statut',
                            self::NON_STAFF_MEMBERS
                        );
                        break;
                    case self::MEMBERSHIP_ADMIN:
                        $select->where->equalTo('bool_admin_adh', true);
                        break;
                    case self::MEMBERSHIP_NONE:
                        $select->where->equalTo('a.id_statut', Status::DEFAULT_STATUS);
                        break;
                }
            }

            if ($this->filters->account_status_filter) {
                switch ($this->filters->account_status_filter) {
                    case self::ACTIVE_ACCOUNT:
                        $select->where('activite_adh=true');
                        break;
                    case self::INACTIVE_ACCOUNT:
                        $select->where('activite_adh=false');
                        break;
                }
            }

            if ($this->filters->group_filter) {
                $select->join(
                    array('g' => PREFIX_DB . Group::GROUPSUSERS_TABLE),
                    'a.' . Adherent::PK . '=g.' . Adherent::PK,
                    array('*'),
                    $select::JOIN_LEFT
                )->join(
                    array('gs' => PREFIX_DB . Group::TABLE),
                    'gs.' . Group::PK . '=g.' . Group::PK,
                    array('*'),
                    $select::JOIN_LEFT
                )->where(
                    '(g.' . Group::PK . ' = ' . $this->filters->group_filter .
                    ' OR gs.parent_group = NULL OR gs.parent_group = ' .
                    $this->filters->group_filter . ')'
                );
            }

            if ($this->filters instanceof AdvancedMembersList) {
                if ($this->filters->rbirth_date_begin
                    || $this->filters->rbirth_date_end
                ) {
                    if ($this->filters->rbirth_date_begin) {
                        $d = new \DateTime($this->filters->rbirth_date_begin);
                        $select->where->greaterThanOrEqualTo(
                            'ddn_adh',
                            $d->format('Y-m-d')
                        );
                    }
                    if ($this->filters->rbirth_date_end) {
                        $d = new \DateTime($this->filters->rbirth_date_end);
                        $select->where->lessThanOrEqualTo(
                            'ddn_adh',
                            $d->format('Y-m-d')
                        );
                    }
                }

                if ($this->filters->rcreation_date_begin
                    || $this->filters->rcreation_date_end
                ) {
                    if ($this->filters->rcreation_date_begin) {
                        $d = new \DateTime($this->filters->rcreation_date_begin);
                        $select->where->greaterThanOrEqualTo(
                            'date_crea_adh',
                            $d->format('Y-m-d')
                        );
                    }
                    if ($this->filters->rcreation_date_end) {
                        $d = new \DateTime($this->filters->rcreation_date_end);
                        $select->where->lessThanOrEqualTo(
                            'date_crea_adh',
                            $d->format('Y-m-d')
                        );
                    }
                }

                if ($this->filters->rmodif_date_begin
                    || $this->filters->rmodif_date_end
                ) {
                    if ($this->filters->rmodif_date_begin) {
                        $d = new \DateTime($this->filters->rmodif_date_begin);
                        $select->where->greaterThanOrEqualTo(
                            'date_modif_adh',
                            $d->format('Y-m-d')
                        );
                    }
                    if ($this->filters->rmodif_date_end) {
                        $d = new \DateTime($this->filters->rmodif_date_end);
                        $select->where->lessThanOrEqualTo(
                            'date_modif_adh',
                            $d->format('Y-m-d')
                        );
                    }
                }

                if ($this->filters->rdue_date_begin
                    || $this->filters->rdue_date_end
                ) {
                    if ($this->filters->rdue_date_begin) {
                        $d = new \DateTime($this->filters->rdue_date_begin);
                        $select->where->greaterThanOrEqualTo(
                            'date_echeance',
                            $d->format('Y-m-d')
                        );
                    }
                    if ($this->filters->rdue_date_end) {
                        $d = new \DateTime($this->filters->rdue_date_end);
                        $select->where->lessThanOrEqualTo(
                            'date_echeance',
                            $d->format('Y-m-d')
                        );
                    }
                }

                if ($this->filters->show_public_infos) {
                    switch ($this->filters->show_public_infos) {
                        case self::FILTER_W_PUBINFOS:
                            $select->where('bool_display_info = true');
                            break;
                        case self::FILTER_WO_PUBINFOS:
                            $select->where('bool_display_info = false');
                            break;
                        case self::FILTER_DC_PUBINFOS:
                            //nothing to do here.
                            break;
                    }
                }

                if ($this->filters->status) {
                    $select->where->in(
                        'a.id_statut',
                        $this->filters->status
                    );
                }

                if ($this->filters->rcontrib_creation_date_begin
                    || $this->filters->rcontrib_creation_date_end
                ) {
                    if ($this->filters->rcontrib_creation_date_begin) {
                        $d = new \DateTime(
                            $this->filters->rcontrib_creation_date_begin
                        );
                        $select->where->greaterThanOrEqualTo(
                            'ct.date_enreg',
                            $d->format('Y-m-d')
                        );
                    }
                    if ($this->filters->rcontrib_creation_date_end) {
                        $d = new \DateTime(
                            $this->filters->rcontrib_creation_date_end
                        );
                        $select->where->lessThanOrEqualTo(
                            'ct.date_enreg',
                            $d->format('Y-m-d')
                        );
                    }
                }

                if ($this->filters->rcontrib_begin_date_begin
                    || $this->filters->rcontrib_begin_date_end
                ) {
                    if ($this->filters->rcontrib_begin_date_begin) {
                        $d = new \DateTime(
                            $this->filters->rcontrib_begin_date_begin
                        );
                        $select->where->greaterThanOrEqualTo(
                            'ct.date_debut_cotis',
                            $d->format('Y-m-d')
                        );
                    }
                    if ($this->filters->rcontrib_begin_date_end) {
                        $d = new \DateTime(
                            $this->filters->rcontrib_begin_date_end
                        );
                        $select->where->lessThanOrEqualTo(
                            'ct.date_debut_cotis',
                            $d->format('Y-m-d')
                        );
                    }
                }

                if ($this->filters->rcontrib_end_date_begin
                    || $this->filters->rcontrib_end_date_end
                ) {
                    if ($this->filters->rcontrib_end_date_begin) {
                        $d = new \DateTime(
                            $this->filters->rcontrib_end_date_begin
                        );
                        $select->where->greaterThanOrEqualTo(
                            'ct.date_fin_cotis',
                            $d->format('Y-m-d')
                        );
                    }
                    if ($this->filters->rcontrib_end_date_end) {
                        $d = new \DateTime(
                            $this->filters->rcontrib_end_date_end
                        );
                        $select->where->lessThanOrEqualTo(
                            'ct.date_fin_cotis',
                            $d->format('Y-m-d')
                        );
                    }
                }

                if ($this->filters->contrib_min_amount
                    || $this->filters->contrib_max_amount
                ) {
                    if ($this->filters->contrib_min_amount) {
                        $select->where->greaterThanOrEqualTo(
                            'ct.montant_cotis',
                            $this->filters->contrib_min_amount
                        );
                    }
                    if ($this->filters->contrib_max_amount) {
                        $select->where->lessThanOrEqualTo(
                            'ct.montant_cotis',
                            $this->filters->contrib_max_amount
                        );
                    }
                }

                if ($this->filters->contributions_types) {
                    $select->where->in(
                        'ct.id_type_cotis',
                        $this->filters->contributions_types
                    );
                }

                if ($this->filters->payments_types) {
                    $select->where->in(
                        'ct.type_paiement_cotis',
                        $this->filters->payments_types
                    );
                }

                if (count($this->filters->contrib_dynamic) > 0
                    && !isset($this->filters->contrib_dynamic['empty'])
                ) {
                    foreach ($this->filters->contrib_dynamic as $k => $cd) {
                        $qry = '';
                        $prefix = 'a.';
                        $field = null;
                        $qop = ' LIKE ';

                        if (is_array($cd)) {
                            //dynamic choice spotted!
                            $prefix = 'cdfc' . $k . '.';
                            $qry = 'dfc.field_form = \'contrib\' AND ' .
                                'dfc.field_id = ' . $k . ' AND ';
                            $field = 'id';
                            $select->where->in($prefix . $field, $cd);
                        } else {
                            //dynamic field spotted!
                            $prefix = 'dfc.';
                            $qry = 'dfc.field_form = \'contrib\' AND ' .
                                'dfc.field_id = ' . $k . ' AND ';
                            $field = 'field_val';
                            $qry .= 'LOWER(' . $prefix . $field . ') ' .
                                $qop  . ' ' ;
                            $select->where($qry . '%' .strtolower($cd) . '%');
                        }
                    }
                }

                if (count($this->filters->free_search) > 0
                    && !isset($this->filters->free_search['empty'])
                ) {
                    foreach ($this->filters->free_search as $fs) {
                        $fs['search'] = mb_strtolower($fs['search']);
                        $qop = null;
                        switch ($fs['qry_op']) {
                            case AdvancedMembersList::OP_EQUALS:
                                $qop = '=';
                                break;
                            case AdvancedMembersList::OP_CONTAINS:
                                $qop = 'LIKE';
                                $fs['search'] = '%' . $fs['search'] . '%';
                                break;
                            case AdvancedMembersList::OP_NOT_EQUALS:
                                $qop = '!=';
                                break;
                            case AdvancedMembersList::OP_NOT_CONTAINS:
                                $qop = 'NOT LIKE';
                                $fs['search'] = '%' . $fs['search'] . '%';
                                break;
                            case AdvancedMembersList::OP_STARTS_WITH:
                                $qop = 'LIKE';
                                $fs['search'] = $fs['search'] . '%';
                                break;
                            case AdvancedMembersList::OP_ENDS_WITH:
                                $qop = 'LIKE';
                                $fs['search'] = '%' . $fs['search'];
                                break;
                            default:
                                Analog::log(
                                    'Unknown query operator: ' . $fs['qry_op'] .
                                    ' (will fallback to equals)',
                                    Analog::WARNING
                                );
                                $qop = '=';
                                break;
                        }

                        $qry = '';
                        $prefix = 'a.';
                        if (strpos($fs['field'], 'dync_') === 0) {
                            // choice dynamic choice spotted!
                            $index = str_replace('dync_', '', $fs['field']);
                            $prefix = 'df' . $index . '.';
                            $fs['field'] = 'val';
                        } elseif (strpos($fs['field'], 'dyn_') === 0) {
                            // simple dynamic field spotted!
                            $index = str_replace('dyn_', '', $fs['field']);
                            $prefix = 'df' . $index . '.';
                            $fs['field'] = 'val';
                        }

                        if (!strncmp($fs['field'], 'bool_', strlen('bool_'))) {
                            $qry .= $prefix . $fs['field'] . $qop  . ' ' .
                                $fs['search'] ;
                        } else {
                            $qry .= 'LOWER(' . $prefix . $fs['field'] . ') ' .
                                $qop  . ' ' . $zdb->platform->quoteValue(
                                    $fs['search']
                                );
                        }

                        if ($fs['log_op'] === AdvancedMembersList::OP_AND) {
                            $select->where($qry);
                        } elseif ($fs['log_op'] === AdvancedMembersList::OP_OR) {
                            $select->orWhere($qry);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }

    /**
     * Login and password field cannot be empty.
     *
     * If those ones are not required, or if a file has been imported
     * (from a CSV file for example), we fill here random values.
     *
     * @return boolean
     */
    public function emptyLogins()
    {
        global $zdb;

        try {
            $zdb->connection->beginTransaction();
            $select = $zdb->select(Adherent::TABLE);
            $select->columns(
                array('id_adh', 'login_adh', 'mdp_adh')
            )->where(
                array(
                    'login_adh' => new Expression('NULL'),
                    'login_adh' => '',
                    'mdp_adh'   => new Expression('NULL'),
                    'mdp_adh'   => ''
                ),
                PredicateSet::OP_OR
            );

            $results = $zdb->execute($select);

            $processed = 0;
            if ($results->count() > 0) {
                $update = $zdb->update(Adherent::TABLE);
                $update->set(
                    array(
                        'login_adh' => ':login',
                        'mdp_adh'   => ':pass'
                    )
                )->where->equalTo(Adherent::PK, ':id');

                $stmt = $zdb->sql->prepareStatementForSqlObject($update);

                $p = new \Galette\Core\Password($zdb);

                foreach ($results as $m) {
                    $dirty = false;
                    if ($m->login_adh == ''
                        || !isset($m->login_adh)
                        || $m->login_adh == 'NULL'
                    ) {
                        $m->login_adh = $p->makeRandomPassword(15);
                        $dirty = true;
                    }

                    if ($m->mdp_adh == ''
                        || !isset($m->mdp_adh)
                        || $m->mdp_adh == 'NULL'
                    ) {
                        $randomp = $p->makeRandomPassword(15);
                        $m->mdp_adh = password_hash(
                            $randomp,
                            PASSWORD_BCRYPT
                        );
                        $dirty = true;
                    }

                    if ($dirty === true) {
                        /** Why where parameter is named where1 ?? */
                        $stmt->execute(
                            array(
                                'login_adh' => $m->login_adh,
                                'mdp_adh'   => $m->mdp_adh,
                                'where1'    => $m->id_adh
                            )
                        );
                        $processed++;
                    }
                }
            }
            $zdb->connection->commit();
            $this->count = $processed;
            return true;
        } catch (\Exception $e) {
            $zdb->connection->rollBack();
            Analog::log(
                'An error occured trying to retrieve members with ' .
                'empty logins/passwords (' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Loads data to produce a Pie chart based on members state of dues
     *
     * @return void
     */
    public function getRemindersCount()
    {
        global $zdb;

        $reminders = array();

        $soon_date = new \DateTime();
        $soon_date->modify('+30 day');

        $now = new \DateTime();

        $select = $zdb->select(Adherent::TABLE, 'a');
        $select->columns(
            array(
                'cnt' => new Expression('count(a.' . Adherent::PK . ')')
            )
        );
        $select->where
            ->lessThan('date_echeance', $soon_date->format('Y-m-d'))
            ->greaterThanOrEqualTo('date_echeance', $now->format('Y-m-d'));
        $select
            ->where('activite_adh=true')
            ->where('bool_exempt_adh=false');

        $select_wo_mail = clone $select;

        $select->where('email_adh != \'\'');
        $select_wo_mail->where('email_adh = \'\'');

        $results = $zdb->execute($select);
        $res = $results->current();
        $reminders['impending'] = $res->cnt;

        $results_wo_mail = $zdb->execute($select_wo_mail);
        $res_wo_mail = $results_wo_mail->current();
        $reminders['nomail']['impending'] = $res_wo_mail->cnt;

        $select = $zdb->select(Adherent::TABLE, 'a');
        $select->columns(
            array(
                'cnt' => new Expression('count(a.' . Adherent::PK . ')')
            )
        );
        $select->where
            ->lessThan('date_echeance', $now->format('Y-m-d'));
        $select
            ->where('activite_adh=true')
            ->where('bool_exempt_adh=false');

        $select_wo_mail = clone $select;

        $select->where('email_adh != \'\'');
        $select_wo_mail->where('email_adh = \'\'');

        $results = $zdb->execute($select);
        $res = $results->current();
        $reminders['late'] = $res->cnt;

        $results_wo_mail = $zdb->execute($select_wo_mail);
        $res_wo_mail = $results_wo_mail->current();
        $reminders['nomail']['late'] = $res_wo_mail->cnt;

        return $reminders;
    }


    /**
     * Get count for current query
     *
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Get registered errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
