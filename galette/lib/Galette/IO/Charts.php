<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's charts
 *
 * PHP version 5
 *
 * Copyright © 2013 The Galette Team
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
 * @category  IO
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.4dev - 2013-02-02
 */

namespace Galette\IO;

use Analog\Analog;
use Galette\Entity\Status;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\ContributionsTypes;
use Galette\Repository\Members;

/**
 * Charts class for galette
 *
 * @category  Charts
 * @name      Charts
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.4dev - 2013-02-02
 */
class Charts
{
    const DEFAULT_CHART = 'MembersStatusPie';
    const MEMBERS_STATUS_PIE = 'MembersStatusPie';
    const MEMBERS_STATEDUE_PIE = 'MembersStateDuePie';
    const CONTRIBS_TYPES_PIE = 'ContribsTypesPie';
    const COMPANIES_OR_NOT = 'CompaniesOrNot';
    const CONTRIBS_ALLTIME = 'ContribsAllTime';

    private $_types;
    private $_charts;

    /**
     * Default constructor
     *
     * @param array $types Charts types to cache
     */
    public function __construct($types = null)
    {
        if ( $types !== null ) {
            if ( !is_array($types) ) {
                $types = array($types);
            }
            $this->_types = $types;
        } else {
            $this->_types = array(self::DEFAULT_CHART);
        }

        $this->_load();
    }

    /**
     * Loads charts data
     *
     * @return void
     */
    private function _load()
    {
        foreach ( $this->_types as $t ) {
            $classname = "_getChart" . $t;
            $this->$classname();
        }
    }

    /**
     * Retrieve loaded charts
     *
     * @return array
     */
    public function getCharts()
    {
        return $this->_charts;
    }

    /**
     * Loads data to produce a Pie chart based on members status
     *
     * @return void
     */
    private function _getChartMembersStatusPie()
    {
        global $zdb;


        $select = new \Zend_Db_Select($zdb->db);
        $select->from(
            array('a' => PREFIX_DB . Status::TABLE),
            array(
                'cnt'       => 'count(a.' . Status::PK . ')',
                'status'    => 'a.libelle_statut',
                'priority'  => 'a.priorite_statut'
            )
        )->join(
            array('b' => PREFIX_DB . Adherent::TABLE),
            'a.' . Status::PK . '=b.' . Status::PK,
            array()
        )
            ->order('a.priorite_statut')
            ->group('a.' . Status::PK);

        Analog::log(
            $select->__toString(),
            Analog::DEBUG
        );
        $res = $select->query()->fetchAll();

        $chart = array();
        $staff = array(_T("Staff members"), 0);
        foreach ( $res as $r ) {
            if ( $r->priority >= Members::NON_STAFF_MEMBERS ) {
                $chart[] = array(
                    _T($r->status),
                    (int)$r->cnt
                );
            } else {
                $staff[1] += $r->cnt;
            }
        }
        $chart[] = $staff;
        $this->_charts[self::MEMBERS_STATUS_PIE] = json_encode($chart);
    }

    /**
     * Loads data to produce a Pie chart based on members state of dues
     *
     * @return void
     */
    private function _getChartMembersStateDuePie()
    {
        global $zdb;

        $chart = array();
        $select = new \Zend_Db_Select($zdb->db);
        $select->from(
            array('a' => PREFIX_DB . Adherent::TABLE),
            array(
                'cnt' => 'count(a.' . Adherent::PK . ')'
            )
        )->where('bool_exempt_adh = ?', true);

        $res = $select->query()->fetchColumn();
        $chart[] = array(
            _T("Due free"),
            (int)$res
        );

        $select = new \Zend_Db_Select($zdb->db);
        $select->from(
            array('a' => PREFIX_DB . Adherent::TABLE),
            array(
                'cnt' => 'count(a.' . Adherent::PK . ')'
            )
        )->where('date_echeance ?', new \Zend_Db_Expr('IS NULL'));

        $res = $select->query()->fetchColumn();
        $chart[] = array(
            _T("Never contribute"),
            (int)$res
        );

        $soon_date = new \DateTime();
        $soon_date->modify('+30 day');

        $select = new \Zend_Db_Select($zdb->db);
        $select->from(
            array('a' => PREFIX_DB . Adherent::TABLE),
            array(
                'cnt' => 'count(a.' . Adherent::PK . ')'
            )
        )
            ->where('date_echeance < ?', $soon_date->format('Y-m-d'))
            ->where('date_echeance >= ?', new \Zend_Db_Expr('NOW()'));

        $res = $select->query()->fetchColumn();
        $chart[] = array(
            _T("Impending due dates"),
            (int)$res
        );

        $select = new \Zend_Db_Select($zdb->db);
        $select->from(
            array('a' => PREFIX_DB . Adherent::TABLE),
            array(
                'cnt'       => 'count(a.' . Adherent::PK . ')'
            )
        )->where('date_echeance > ?', new \Zend_Db_Expr('NOW()'));

        $res = $select->query()->fetchColumn();
        $chart[] = array(
            _T("Up to date"),
            (int)$res
        );

        $select = new \Zend_Db_Select($zdb->db);
        $select->from(
            array('a' => PREFIX_DB . Adherent::TABLE),
            array(
                'cnt'       => 'count(a.' . Adherent::PK . ')'
            )
        )->where('date_echeance < ?', new \Zend_Db_Expr('NOW()'));

        $res = $select->query()->fetchColumn();
        $chart[] = array(
            _T("Late"),
            (int)$res
        );

        $this->_charts[self::MEMBERS_STATEDUE_PIE] = json_encode($chart);
    }

    /**
     * Loads data to produce a pie chart based on company/not company members
     *
     * @return void
     */
    private function _getChartCompaniesOrNot()
    {
        global $zdb;

        //non companies
        $select1 = new \Zend_Db_Select($zdb->db);
        $select1->from(
            PREFIX_DB . Adherent::TABLE,
            array(
                'cnt' => 'count(' . Adherent::PK . ')'
            )
        )
            ->where('societe_adh ?', new \Zend_Db_Expr('IS NULL'))
            ->orWhere('societe_adh = ?', '');

        $select2 = new \Zend_Db_Select($zdb->db);
        $select2->from(
            PREFIX_DB . Adherent::TABLE,
            array(
                'cnt' => 'count(' . Adherent::PK . ')'
            )
        )
            ->where('societe_adh ?', new \Zend_Db_Expr('IS NOT NULL'))
            ->Where('societe_adh != ?', '');

        //companies
        $select = new \Zend_Db_Select($zdb->db);
        $select->union(array($select1, $select2), \Zend_Db_Select::SQL_UNION_ALL);

        Analog::log(
            $select->__toString(),
            Analog::DEBUG
        );

        $res = $select->query()->fetchAll();

        $chart = array(
            array(
                _T("Individuals"),
                (int)$res[0]->cnt
            ),
            array(
                _T("Companies"),
                (int)$res[1]->cnt
            )
        );
        $this->_charts[self::COMPANIES_OR_NOT] = json_encode($chart);
    }

    /**
     * Loads data to produce a Pie chart based on contributions types
     *
     * @return void
     */
    private function _getChartContribsTypesPie()
    {
        global $zdb;

        $select = new \Zend_Db_Select($zdb->db);
        $select->from(
            array('a' => PREFIX_DB . ContributionsTypes::TABLE),
            array(
                'cnt'       => 'count(a.' . ContributionsTypes::PK . ')',
                'label'     => 'a.libelle_type_cotis',
                'extends'   => 'a.cotis_extension'
            )
        )->join(
            array('b' => PREFIX_DB . Contribution::TABLE),
            'a.' . ContributionsTypes::PK . '=b.' . ContributionsTypes::PK,
            array()
        )
            ->order('a.cotis_extension')
            ->group('a.' . ContributionsTypes::PK);

        Analog::log(
            $select->__toString(),
            Analog::DEBUG
        );
        $res = $select->query()->fetchAll();

        $chart = array();
        foreach ( $res as $r ) {
            $chart[] = array(
                _T($r->label),
                (int)$r->cnt
            );
        }
        $this->_charts[self::CONTRIBS_TYPES_PIE] = json_encode($chart);
    }

    /**
     * Loads data to produce a Pie chart based on contributions types
     *
     * @return void
     */
    private function _getChartContribsAllTime()
    {
        global $zdb;

        $select = new \Zend_Db_Select($zdb->db);

        $cols = array(
            'date'      => null,
            'amount'    => new \Zend_Db_Expr('SUM(montant_cotis)')
        );
        $groupby = null;

        if ( TYPE_DB === 'pgsql' ) {
            $cols['date'] = new \Zend_Db_Expr('date_trunc(\'month\', date_enreg)');
            $groupby = new \Zend_Db_Expr('date_trunc(\'month\', date_enreg)');
        } else if ( TYPE_DB === 'mysql' ) {
            $cols['date'] = new \Zend_Db_Expr('date_format(date_enreg, \'%Y-%m\')');
            $groupby = new \Zend_Db_Expr('EXTRACT(YEAR_MONTH FROM date_enreg)');
        } else if ( TYPE_DB === 'sqlite') {
            $cols['date'] = new \Zend_Db_Expr('STRFTIME("%Y-%m-%d", date_enreg)');
            $groupby = 'date';
        }

        $select->from(
            PREFIX_DB . Contribution::TABLE,
            $cols
        )->group($groupby)->order('date ASC');

        $res = $select->query()->fetchAll();

        $chart = array();
        foreach ( $res as $r ) {
            $d = new \DateTime($r->date);
            $chart[] = array(
                $d->format('Y-m'),
                (float)$r->amount
            );
        }
        $this->_charts[self::CONTRIBS_ALLTIME] = json_encode($chart);
    }
}
