<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members class
 *
 * PHP version 5
 *
 * Copyright © 2009-2011 The Galette Team
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
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2011 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2009-02-28
 */

/** @ignore */
require_once 'adherent.class.php';
require_once 'status.class.php';

/**
 * Members class for galette
 *
 * @name Members
 * @category  Classes
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2009-2011 The Galette Team
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

    const FILTER_NAME = 0;
    const FILTER_ADRESS = 1;
    const FILTER_MAIL = 2;
    const FILTER_JOB = 3;
    const FILTER_INFOS = 4;
    const FILTER_DC_EMAIL = 5;
    const FILTER_W_EMAIL = 6;
    const FILTER_WO_EMAIL = 7;

    const ORDERBY_NAME = 0;
    const ORDERBY_NICKNAME = 1;
    const ORDERBY_STATUS = 2;
    const ORDERBY_FEE_STATUS = 3;
    const ORDERBY_ID = 4;

    const NON_STAFF_MEMBERS = 30;

    private $_filter = null;
    private $_count = null;

    /**
    * Default constructor
    */
    public function __construct()
    {
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
        $limit=true
    ) {
        global $zdb, $log, $varslist;

        try {
            $_mode = self::SHOW_LIST;
            if ( $staff !== false ) {
                $_mode = self::SHOW_STAFF;
            }

            $select = self::_buildSelect(
                $_mode, $fields, $filter, false, $count
            );
            if ( $staff !== false ) {
                $select->where('p.priorite_statut < ' . self::NON_STAFF_MEMBERS);
            }

            //add limits to retrieve only relavant rows
            if ( $limit === true && isset($varslist) ) {
                $varslist->setLimit($select);
            }

            $members = array();
            if ( $as_members ) {
                foreach ( $select->query()->fetchAll() as $row ) {
                    $members[] = new Adherent($row);
                }
            } else {
                $members = $select->query()->fetchAll();
            }
            return $members;
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot list members | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
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
                $select = new Zend_Db_Select($zdb->db);
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
                        if ( !$p->delete() ) {
                            $log->log(
                                'Unable to delete picture for member ' . $str_adh,
                                PEAR_LOG_ERR
                            );
                            throw new Exception('Unable to delete picture for member ' . $str_adh);
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
            } catch (Exception $e) {
                $zdb->db->rollBack();
                $log->log(
                    'Unable to delete selected member(s) |' .
                    $e->getMessage(),
                    PEAR_LOG_ERR
                );
                return false;
            }
        } else {
            //not numeric and not an array: incorrect.
            $log->log(
                'Asking to remove members, but without providing an array or a single numeric value.',
                PEAR_LOG_WARNING
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
    public static function getList($as_members=false, $fields=null, $filter=true)
    {
        return self::getMembersList(
            $as_members,
            $fields,
            $filter,
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
    *                               photo (for trombinoscope)
    * @param array   $fields      fields list
    *
    * @return Adherent[]
    * @static
    */
    public static function getPublicList($with_photos, $fields)
    {
        global $zdb, $log, $varslist;

        try {
            $select = self::_buildSelect(
                self::SHOW_PUBLIC_LIST, $fields, false, $with_photos
            );
            $select->where('bool_display_info = ?', true)
                ->where(
                    'date_echeance > ? OR bool_exempt_adh = true',
                    date('Y-m-d')
                );
            if ( $varslist ) {
                $select->order(self::_buildOrderClause());
            }
            $result = $select->query()->fetchAll();
            foreach ( $result as $row ) {
                $members[] = new Adherent($row);
            }
            return $members;
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot list members with public informations (photos: '
                . $with_photos . ') | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
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
            $log->log('No member selected for labels.', PEAR_LOG_INFO);
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
            $result = $select->query();
            $members = array();
            foreach ( $result->fetchAll() as $o) {
                $members[] = new Adherent($o);
            }
            return $members;
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot load members form ids array | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
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
        global $zdb;

        try {
            $fieldsList = ( $fields != null )
                            ? (( !is_array($fields) || count($fields) < 1 ) ? (array)'*'
                            : implode(', ', $fields)) : (array)'*';

            $select = new Zend_Db_Select($zdb->db);
            $select->from(
                array('a' => PREFIX_DB . self::TABLE),
                $fieldsList
            );

            switch($mode) {
            case self::SHOW_STAFF:
            case self::SHOW_LIST:
                $select->join(
                    array('p' => PREFIX_DB . Status::TABLE, Status::PK),
                    'a.' . Status::PK . '=' . 'p.' . Status::PK
                );
                break;
            case self::SHOW_PUBLIC_LIST:
                if ( $photos ) {
                    $select->join(
                        array('p' => PREFIX_DB . Picture::TABLE),
                        'a.' . self::PK . '= p.' . self::PK
                    );
                }
                break;
            }

            if ( $mode == self::SHOW_LIST ) {
                if ( $filter ) {
                    self::_buildWhereClause($select);
                }
                $select->order(self::_buildOrderClause());
            }

            if ( $count ) {
                $this->_proceedCount($select);
            }

            return $select;
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot build SELECT clause for members | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $select->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
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
        global $zdb, $log, $varslist;

        try {
            $countSelect = clone $select;
            $countSelect->reset(Zend_Db_Select::COLUMNS);
            $countSelect->reset(Zend_Db_Select::ORDER);
            $countSelect->reset(Zend_Db_Select::HAVING);
            $countSelect->columns('count(' . self::PK . ') AS ' . self::PK);

            $have = $select->getPart(Zend_Db_Select::HAVING);
            if ( is_array($have) && count($have) > 0 ) {
                foreach ( $have as $h ) {
                    $countSelect->where($h);
                }
            }

            $result = $countSelect->query()->fetch();

            $k = self::PK;
            $this->_count = $result->$k;
            if ( isset($varslist) && $this->_count > 0 ) {
                $varslist->setCounter($this->_count);
            }
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                'Cannot count members | ' . $e->getMessage(),
                PEAR_LOG_WARNING
            );
            $log->log(
                'Query was: ' . $countSelect->__toString() . ' ' . $e->__toString(),
                PEAR_LOG_ERR
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
        global $varslist;
        $order = array();
        switch($varslist->orderby) {
        case self::ORDERBY_NICKNAME:
            $order[] = 'pseudo_adh ' . $varslist->getDirection();
            break;
        case self::ORDERBY_STATUS:
            $order[] = 'priorite_statut ' . $varslist->getDirection();
            break;
        case self::ORDERBY_ID:
            $order[] = 'id_adh ' . $varslist->getDirection();
            break;
        case self::ORDERBY_FEE_STATUS:
            $order[] = 'date_crea_adh ' . $varslist->getDirection();
            $order[] = 'bool_exempt_adh ' . $varslist->getDirection();
            $order[] = 'date_echeance ' . $varslist->getDirection();
            break;
        }

        //anyways, we want to order by firstname, lastname
        $order[] = 'nom_adh ' . $varslist->getDirection();
        $order[] = 'prenom_adh ' . $varslist->getDirection();
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
        global $zdb, $varslist, $login;

        try {
            if ( $varslist->email_filter == Members::FILTER_W_EMAIL) {
                $select->where('email_adh != ""');
            }
            if ( $varslist->email_filter == Members::FILTER_WO_EMAIL) {
                $select->where('email_adh = ""');
            }

            if ( $varslist->filter_str != '' ) {
                $token = '%' . $varslist->filter_str . '%';
                switch( $varslist->field_filter ) {
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
                    $select->where('prof_adh LIKE ?', $token);
                    break;
                case self::FILTER_INFOS:
                    $select->where('info_public_adh LIKE ?', $token);
                    if ( $login->isAdmin() || $login->isStaff() ) {
                        $select->orWhere('info_adh LIKE ?', $token);
                    }
                    break;
                }
            }

            if ( $varslist->membership_filter ) {
                switch($varslist->membership_filter) {
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

            if ( $varslist->account_status_filter ) {
                switch($varslist->account_status_filter) {
                case 1:
                    $select->having('activite_adh=1');
                    break;
                case 2:
                    $select->having('activite_adh=0');
                    break;
                }
            }
        } catch (Exception $e) {
            /** TODO */
            $log->log(
                __METHOD__ . ' | ' . $e->getMessage(),
                PEAR_LOG_WARNING
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
