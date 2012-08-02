<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members class
 *
 * PHP version 5
 *
 * Copyright © 2009-2012 The Galette Team
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
 * @copyright 2009-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-28
 */

namespace Galette\Repository;

use Galette\Common\KLogger as KLogger;
use Galette\Entity\Adherent as Adherent;
use Galette\Entity\Contribution as Contribution;
use Galette\Entity\Transaction as Transaction;
use Galette\Filters\MembersList as MembersList;
use Galette\Core\Picture as Picture;
use Galette\Entity\Group as Group;
use Galette\Entity\Status as Status;

/**
 * Members class for galette
 *
 * @name Members
 * @category  Repository
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2012 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */
class Members
{
    const TABLE = Adherent::TABLE;
    const PK = Adherent::PK;

    const SHOW_LIST = 0;
    const SHOW_PUBLIC_LIST = 1;
    const SHOW_ARRAY_LIST = 2;
    const SHOW_STAFF = 3;
    const SHOW_MANAGED = 4;

    const FILTER_NAME = 0;
    const FILTER_ADRESS = 1;
    const FILTER_MAIL = 2;
    const FILTER_JOB = 3;
    const FILTER_INFOS = 4;
    const FILTER_DC_EMAIL = 5;
    const FILTER_W_EMAIL = 6;
    const FILTER_WO_EMAIL = 7;
    const FILTER_COMPANY_NAME = 8;

    const ORDERBY_NAME = 0;
    const ORDERBY_NICKNAME = 1;
    const ORDERBY_STATUS = 2;
    const ORDERBY_FEE_STATUS = 3;
    const ORDERBY_MODIFDATE = 4;

    const NON_STAFF_MEMBERS = 30;

    private $_filter = null;
    private $_count = null;

    /**
    * Default constructor
    */
    public function __construct()
    {
        global $filters;

        if ( !isset($filters) ) {
            $filters = new MembersList();
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
    * @param boolean $filter     proceed filter, defaults to true
    * @param boolean $count      true if we want to count members
    * @param boolean $limit      true to LIMIT query
    *
    * @return Adherent[]|ResultSet
    */
    public function getStaffMembersList(
        $as_members=false, $fields=null, $filter=true, $count=true, $limit=true
    ) {
        return $this->getMembersList(
            $as_members,
            $fields,
            $filter,
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
    * @param boolean $filter     proceed filter, defaults to true
    * @param boolean $count      true if we want to count members
    * @param boolean $limit      true to LIMIT query
    *
    * @return Adherent[]|ResultSet
    */
    public function getManagedMembersList(
        $as_members=false, $fields=null, $filter=true, $count=true, $limit=true
    ) {
        return $this->getMembersList(
            $as_members,
            $fields,
            $filter,
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
    * @param boolean $filter     proceed filter, defaults to true
    * @param boolean $count      true if we want to count members
    * @param boolean $staff      true if we want only staff members
    * @param boolean $managed    true if we want only managed groups
    * @param boolean $limit      true if we want records pagination
    *
    * @return Adherent[]|ResultSet
    */
    public function getMembersList(
        $as_members=false,
        $fields=null,
        $filter=true,
        $count=true,
        $staff=false,
        $managed=false,
        $limit=true
    ) {
        global $zdb, $log, $galetteLoader, $filters;

        try {
            $_mode = self::SHOW_LIST;
            if ( $staff !== false ) {
                $_mode = self::SHOW_STAFF;
            }
            if ( $managed !== false ) {
                $_mode = self::SHOW_MANAGED;
            }

            $select = self::_buildSelect(
                $_mode, $fields, $filter, false, $count
            );
            if ( $staff !== false ) {
                $select->where('p.priorite_statut < ' . self::NON_STAFF_MEMBERS);
            }

            //add limits to retrieve only relavant rows
            if ( $limit === true && isset($filters) ) {
                $filters->setLimit($select);
            }

            $log->log(
                "The following query will be executed: \n" .
                $select->__toString(),
                KLogger::DEBUG
            );

            $members = array();
            if ( $as_members ) {
                foreach ( $select->query()->fetchAll() as $row ) {
                    $members[] = new Adherent($row);
                }
            } else {
                $members = $select->query()->fetchAll();
            }
            return $members;
        } catch (\Exception $e) {
            /** TODO */
            $log->log(
                'Cannot list members | ' . $e->getMessage(),
                KLogger::WARN
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                KLogger::ERR
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
        global $zdb, $log, $hist;

        $list = array();
        if ( is_numeric($ids) ) {
            //we've got only one identifier
            $list[] = $ids;
        } else {
            $list = $ids;
        }

        if ( is_array($list) ) {
            try {
                $zdb->db->beginTransaction();

                //Retrieve some informations
                $select = new \Zend_Db_Select($zdb->db);
                $select->from(
                    PREFIX_DB . self::TABLE,
                    array(self::PK, 'nom_adh', 'prenom_adh')
                )->where(self::PK . ' IN (?)', $list);

                $members = $select->query()->fetchAll();
                $infos = null;
                foreach ($members as $member ) {
                    $str_adh = $member->id_adh . ' (' . $member->nom_adh . ' ' .
                        $member->prenom_adh . ')';
                    $infos .=  $str_adh . "\n";

                    $p = new Picture($member->id_adh);
                    if ( $p->hasPicture() ) {
                        if ( !$p->delete(false) ) {
                            $log->log(
                                'Unable to delete picture for member ' . $str_adh,
                                KLogger::ERR
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
                $del = $zdb->db->delete(
                    PREFIX_DB . Contribution::TABLE,
                    self::PK . ' IN (' . implode(',', $list) . ')'
                );

                //delete transactions
                $del = $zdb->db->delete(
                    PREFIX_DB . Transaction::TABLE,
                    self::PK . ' IN (' . implode(',', $list) . ')'
                );

                //delete members
                $del = $zdb->db->delete(
                    PREFIX_DB . self::TABLE,
                    self::PK . ' IN (' . implode(',', $list) . ')'
                );

                //commit all changes
                $zdb->db->commit();

                //add an history entry
                $hist->add(
                    _T("Delete members cards, transactions and dues"),
                    $infos
                );

                return true;
            } catch (\Exception $e) {
                $zdb->db->rollBack();
                $log->log(
                    'Unable to delete selected member(s) |' .
                    $e->getMessage(),
                    KLogger::ERR
                );
                return false;
            }
        } else {
            //not numeric and not an array: incorrect.
            $log->log(
                'Asking to remove members, but without providing an array or a single numeric value.',
                KLogger::WARN
            );
            return false;
        }
    }

    /**
    * Get members list
    *
    * @param bool    $as_members return the results as an array of
    *                               Member object.
    * @param array   $fields     field(s) name(s) to get. Should be a string or
    *                               an array. If null, all fields will be
    *                               returned
    * @param boolean $filter     proceed filter, defaults to true
    *
    * @return Adherent[]|ResultSet
    * @static
    */
    public function getList($as_members=false, $fields=null, $filter=true)
    {
        return self::getMembersList(
            $as_members,
            $fields,
            $filter,
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
    *                             photo (for trombinoscope)
    * @param array   $fields      fields list
    *
    * @return Adherent[]
    * @static
    */
    public function getPublicList($with_photos, $fields)
    {
        global $zdb, $log, $filters;

        try {
            $select = self::_buildSelect(
                self::SHOW_PUBLIC_LIST, $fields, false, $with_photos
            );
            $select->where('bool_display_info = ?', true)
                ->where(
                    'date_echeance > ? OR bool_exempt_adh = true',
                    date('Y-m-d')
                );
            $log->log(
                "The following query will be executed: \n" .
                $select->__toString(),
                KLogger::DEBUG
            );

            if ( $filters ) {
                $select->order(self::_buildOrderClause());
            }
            $result = $select->query()->fetchAll();
            $members = array();
            foreach ( $result as $row ) {
                $members[] = new Adherent($row);
            }
            return $members;
        } catch (\Exception $e) {
            /** TODO */
            $log->log(
                'Cannot list members with public informations (photos: '
                . $with_photos . ') | ' . $e->getMessage(),
                KLogger::WARN
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                KLogger::ERR
            );
            return false;
        }
    }

    /**
    * Get list of members that has been selected
    *
    * @param array $ids     an array of members id that has been selected
    * @param array $orderby SQL order clause (optionnal)
    *
    * @return Adherent[]
    * @static
    */
    public static function getArrayList($ids, $orderby = null)
    {
        global $zdb, $log;

        if ( !is_array($ids) || count($ids) < 1 ) {
            $log->log('No member selected for labels.', KLogger::INFO);
            return false;
        }

        try {
            $select = self::_buildSelect(self::SHOW_ARRAY_LIST, null, false, false);
            $select->where(self::PK . ' IN (?)', $ids);
            if ( $orderby != null && count($orderby) > 0 ) {
                if (is_array($orderby)) {
                    foreach ( $orderby as $o ) {
                        $select->order($o);
                    }
                } else {
                    $select->order($orderby);
                }
            }

            $log->log(
                "The following query will be executed: \n" .
                $select->__toString(),
                KLogger::DEBUG
            );

            $result = $select->query();
            $members = array();
            foreach ( $result->fetchAll() as $o) {
                $members[] = new Adherent($o);
            }
            return $members;
        } catch (\Exception $e) {
            /** TODO */
            $log->log(
                'Cannot load members form ids array | ' . $e->getMessage(),
                KLogger::WARN
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                KLogger::ERR
            );
        }
    }

    /**
    * Builds the SELECT statement
    *
    * @param int   $mode   the current mode (see self::SHOW_*)
    * @param array $fields fields list to retrieve
    * @param bool  $filter true if filter is on, false otherwise
    * @param bool  $photos true if we want to get only members with photos
    *                       Default to false, only relevant for SHOW_PUBLIC_LIST
    * @param bool  $count  true if we want to count members
                            (not applicable from static calls), defaults to false
    *
    * @return Zend_Db_Select SELECT statement
    */
    private function _buildSelect($mode, $fields, $filter, $photos, $count = false)
    {
        global $zdb, $log, $login;

        try {
            $fieldsList = ( $fields != null )
                            ? (( !is_array($fields) || count($fields) < 1 ) ? (array)'*'
                            : implode(', ', $fields)) : (array)'*';

            $select = new \Zend_Db_Select($zdb->db);
            $select->from(
                array('a' => PREFIX_DB . self::TABLE),
                $fieldsList
            );

            switch($mode) {
            case self::SHOW_STAFF:
            case self::SHOW_LIST:
            case self::SHOW_ARRAY_LIST:
                $select->join(
                    array('p' => PREFIX_DB . Status::TABLE, Status::PK),
                    'a.' . Status::PK . '=' . 'p.' . Status::PK
                );
                break;
            case self::SHOW_MANAGED:
                $select->join(
                    array('p' => PREFIX_DB . Status::TABLE, Status::PK),
                    'a.' . Status::PK . '=' . 'p.' . Status::PK
                )->join(
                    array('g' => PREFIX_DB . Group::GROUPSUSERS_TABLE),
                    'a.' . Adherent::PK . '=g.' . Adherent::PK,
                    array()
                )->join(
                    array('m' => PREFIX_DB . Group::GROUPSMANAGERS_TABLE),
                    'g.' . Group::PK . '=m.' . Group::PK,
                    array()
                )->where('m.' . Adherent::PK . ' = ?', $login->id);
            case self::SHOW_PUBLIC_LIST:
                if ( $photos ) {
                    $select->join(
                        array('p' => PREFIX_DB . Picture::TABLE),
                        'a.' . self::PK . '= p.' . self::PK
                    );
                }
                break;
            }

            if ( $mode == self::SHOW_LIST || $mode == self::SHOW_MANAGED ) {
                if ( $filter ) {
                    self::_buildWhereClause($select);
                }
                $select->order(self::_buildOrderClause());
            } else if ( $mode == self::SHOW_PUBLIC_LIST ) {
                $select->where('activite_adh=true');
            }

            if ( $count ) {
                $this->_proceedCount($select);
            }

            return $select;
        } catch (\Exception $e) {
            /** TODO */
            $log->log(
                'Cannot build SELECT clause for members | ' . $e->getMessage(),
                KLogger::WARN
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                KLogger::ERR
            );
            return false;
        }
    }

    /**
    * Count members from the query
    *
    * @param Zend_Db_Select $select Original select
    *
    * @return void
    */
    private function _proceedCount($select)
    {
        global $zdb, $log, $filters;

        try {
            $countSelect = clone $select;
            $countSelect->reset(\Zend_Db_Select::COLUMNS);
            $countSelect->reset(\Zend_Db_Select::ORDER);
            $countSelect->reset(\Zend_Db_Select::HAVING);
            $countSelect->columns('count(a.' . self::PK . ') AS ' . self::PK);

            $have = $select->getPart(\Zend_Db_Select::HAVING);
            if ( is_array($have) && count($have) > 0 ) {
                foreach ( $have as $h ) {
                    $countSelect->where($h);
                }
            }

            $result = $countSelect->query()->fetch();

            $k = self::PK;
            $this->_count = $result->$k;
            if ( isset($filters) && $this->_count > 0 ) {
                $filters->setCounter($this->_count);
            }
        } catch (\Exception $e) {
            /** TODO */
            $log->log(
                'Cannot count members | ' . $e->getMessage(),
                KLogger::WARN
            );
            $log->log(
                'Query was: ' . $countSelect->__toString() . ' ' . $e->__toString(),
                KLogger::ERR
            );
            return false;
        }
    }

    /**
     * Builds the order clause
     *
     * @return string SQL ORDER clause
     */
    private function _buildOrderClause()
    {
        global $filters;

        $order = array();

        switch($filters->orderby) {
        case self::ORDERBY_NICKNAME:
            $order[] = 'pseudo_adh ' . $filters->getDirection();
            break;
        case self::ORDERBY_STATUS:
            $order[] = 'priorite_statut ' . $filters->getDirection();
            break;
        case self::ORDERBY_MODIFDATE:
            $order[] = 'date_modif_adh ' . $filters->getDirection();
            break;
        case self::ORDERBY_FEE_STATUS:
            $order[] = 'bool_exempt_adh ' . $filters->getDirection();
            $order[] = 'date_echeance ' . $filters->getDirection();
            break;
        }

        //anyways, we want to order by firstname, lastname
        $order[] = 'nom_adh ' . $filters->getDirection();
        $order[] = 'prenom_adh ' . $filters->getDirection();
        return $order;
    }

    /**
     * Builds where clause, for filtering on simple list mode
     *
     * @param Zend_Db_Select $select Original select
     *
     * @return string SQL WHERE clause
     */
    private function _buildWhereClause($select)
    {
        global $zdb, $login, $filters;

        try {
            if ( $filters->email_filter == self::FILTER_W_EMAIL) {
                $select->where('email_adh != \'\'');
            }
            if ( $filters->email_filter == self::FILTER_WO_EMAIL) {
                $select->where('email_adh = ""');
            }

            if ( $filters->filter_str != '' ) {
                $token = '%' . $filters->filter_str . '%';
                switch( $filters->field_filter ) {
                case self::FILTER_NAME:
                    $sep = ( TYPE_DB === 'pgsql' ) ? " || ' ' || " : ', " ", ';
                    $select->where(
                        '(' . $zdb->db->quoteInto(
                            'CONCAT(LOWER(nom_adh)' . $sep .
                            'LOWER(prenom_adh)' . $sep .
                            'LOWER(pseudo_adh)) LIKE ?',
                            strtolower($token)
                        ) . ' OR ' .
                        $zdb->db->quoteInto(
                            'CONCAT(LOWER(prenom_adh)' . $sep .
                            'LOWER(nom_adh)' . $sep .
                            'LOWER(pseudo_adh)) LIKE ?',
                            strtolower($token)
                        ) . ')'
                    );
                    break;
                case self::FILTER_COMPANY_NAME:
                    $select->where(
                        $zdb->db->quoteInto(
                            'LOWER(societe_adh) LIKE ?',
                            strtolower($token)
                        )
                    );
                    break;
                case self::FILTER_ADRESS:
                    $select->where(
                        '(' . $zdb->db->quoteInto(
                            'LOWER(adresse_adh) LIKE ?',
                            strtolower($token)
                        ) . ' OR ' . $zdb->db->quoteInto(
                            'LOWER(adresse2_adh) LIKE ?',
                            strtolower($token)
                        ) . ' OR ' . $zdb->db->quoteInto(
                            'cp_adh LIKE ?',
                            $token
                        ) . ' OR ' . $zdb->db->quoteInto(
                            'LOWER(ville_adh) LIKE ?',
                            strtolower($token)
                        ) . ' OR ' . $zdb->db->quoteInto(
                            'LOWER(pays_adh) LIKE ?',
                            strtolower($token)
                        ) . ')'
                    );
                    break;
                case self::FILTER_MAIL:
                    $select->where(
                        '(' . $zdb->db->quoteInto(
                            'LOWER(email_adh) LIKE ?',
                            strtolower($token)
                        ) . ' OR ' . $zdb->db->quoteInto(
                            'LOWER(url_adh) LIKE ?',
                            strtolower($token)
                        ) . ' OR ' . $zdb->db->quoteInto(
                            'LOWER(msn_adh) LIKE ?',
                            strtolower($token)
                        ) . ' OR ' . $zdb->db->quoteInto(
                            'LOWER(icq_adh) LIKE ?',
                            strtolower($token)
                        ) . ' OR ' . $zdb->db->quoteInto(
                            'LOWER(jabber_adh) LIKE ?',
                            strtolower($token)
                        ) . ')'
                    );
                    break;
                case self::FILTER_JOB:
                    $select->where(
                        'LOWER(prof_adh) LIKE ?',
                        strtolower($token)
                    );
                    break;
                case self::FILTER_INFOS:
                    $more = '';
                    if ( $login->isAdmin() || $login->isStaff() ) {
                        $more = ' OR ' . $zdb->db->quoteInto(
                            'LOWER(info_adh) LIKE ?',
                            $token
                        );
                    }
                    $select->where(
                        '(' . $zdb->db->quoteInto(
                            'LOWER(info_public_adh) LIKE ?',
                            strtolower($token)
                        ) . $more . ')'
                    );
                    break;
                }
            }

            if ( $filters->membership_filter ) {
                switch($filters->membership_filter) {
                case 1:
                    $select->where('date_echeance > ?', date('Y-m-d', time()))
                        ->where(
                            'date_echeance < ?',
                            date('Y-m-d', time() + (30 *24 * 60 * 60))
                        );
                        //(30 *24 * 60 * 60) => 30 days
                    break;
                case 2:
                    $select->where('date_echeance < ?', date('Y-m-d', time()));
                    break;
                case 3:
                    $select->where(
                        '(' . $zdb->db->quoteInto(
                            'date_echeance > ?',
                            date('Y-m-d', time())
                        ) . ' OR bool_exempt_adh=true)'
                    );
                    break;
                case 4:
                    $select->where('date_echeance IS NULL');
                    break;
                case 5:
                    $select->where('p.priorite_statut < ' . self::NON_STAFF_MEMBERS);
                    break;
                case 6:
                    $select->where('bool_admin_adh = ?', true);
                    break;
                }
            }

            if ( $filters->account_status_filter ) {
                switch($filters->account_status_filter) {
                case 1:
                    $select->where('activite_adh=true');
                    break;
                case 2:
                    $select->where('activite_adh=false');
                    break;
                }
            }

            if ( $filters->group_filter ) {
                $select->joinLeft(
                    array('g' => PREFIX_DB . Group::GROUPSUSERS_TABLE, Adherent::PK),
                    'a.' . Adherent::PK . '=' . 'g.' . Adherent::PK
                )->where('g.' . Group::PK . ' = ?', $filters->group_filter);
            }
        } catch (\Exception $e) {
            /** TODO */
            $log->log(
                __METHOD__ . ' | ' . $e->getMessage(),
                KLogger::WARN
            );
        }
    }

    /**
    * Get count for current query
    *
    * @return int
    */
    public function getCount()
    {
        return $this->_count;
    }
}
?>
