<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Galette's charts
 *
 * PHP version 5
 *
 * Copyright © 2013-2021 The Galette Team
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
 * @copyright 2013-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.4dev - 2013-02-02
 */

namespace Galette\IO;

use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\PredicateSet;
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
 * @copyright 2013-2021 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.4dev - 2013-02-02
 */
class Charts
{
    public const DEFAULT_CHART = 'MembersStatusPie';
    public const MEMBERS_STATUS_PIE = 'MembersStatusPie';
    public const MEMBERS_STATEDUE_PIE = 'MembersStateDuePie';
    public const CONTRIBS_TYPES_PIE = 'ContribsTypesPie';
    public const COMPANIES_OR_NOT = 'CompaniesOrNot';
    public const CONTRIBS_ALLTIME = 'ContribsAllTime';

    private $types;
    private $charts;

    /**
     * Default constructor
     *
     * @param array $types Charts types to cache
     */
    public function __construct($types = null)
    {
        if ($types !== null) {
            if (!is_array($types)) {
                $types = array($types);
            }
            $this->types = $types;
        } else {
            $this->types = array(self::DEFAULT_CHART);
        }

        $this->load();
    }

    /**
     * Loads charts data
     *
     * @return void
     */
    private function load()
    {
        foreach ($this->types as $t) {
            $classname = "getChart" . $t;
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
        return $this->charts;
    }

    /**
     * Loads data to produce a Pie chart based on members status
     *
     * @return void
     */
    private function getChartMembersStatusPie()
    {
        global $zdb;

        $select = $zdb->select(Status::TABLE, 'a');
        $select->columns(
            array(
                'cnt'       => new Expression('COUNT(a.' . Status::PK . ')'),
                'status'    => 'libelle_statut',
                'priority'  => 'priorite_statut'
            )
        )->join(
            array('b' => PREFIX_DB . Adherent::TABLE),
            'a.' . Status::PK . '=b.' . Status::PK,
            array()
        )
            ->where(array('b.activite_adh' => new Expression('true')))
            ->order('a.priorite_statut')
            ->group('a.' . Status::PK);

        $results = $zdb->execute($select);

        $chart_labels = array();
        $chart_data = array();
        $staff_label = _T("Staff members");
        $staff_data = 0;
        foreach ($results as $r) {
            if ($r->priority >= Members::NON_STAFF_MEMBERS) {
                $chart_labels[] = _T($r->status);
                $chart_data[] = (int)$r->cnt;
            } else {
                $staff_data += $r->cnt;
            }
        }
        $chart_labels[] = $staff_label;
        $chart_data[] = $staff_data;
        $this->charts[self::MEMBERS_STATUS_PIE . 'Labels'] = json_encode($chart_labels);
        $this->charts[self::MEMBERS_STATUS_PIE . 'Data'] = json_encode($chart_data);
    }

    /**
     * Loads data to produce a Pie chart based on members state of dues
     *
     * @return void
     */
    private function getChartMembersStateDuePie()
    {
        global $zdb;

        $chart_labels = array();
        $chart_data = array();
        $select = $zdb->select(Adherent::TABLE, 'a');
        $select->columns(
            array(
                'cnt' => new Expression('COUNT(a.' . Adherent::PK . ')')
            )
        )
            ->where(array('activite_adh' => new Expression('true')))
            ->where(array('bool_exempt_adh' => new Expression('true')));

        $results = $zdb->execute($select);
        $result = $results->current();

        $chart_labels[] = _T("Due free");
        $chart_data[] = (int)$result->cnt;

        $select = $zdb->select(Adherent::TABLE, 'a');
        $select->columns(
            array(
                'cnt' => new Expression('COUNT(a.' . Adherent::PK . ')')
            )
        )
            ->where(array('activite_adh' => new Expression('true')))
            ->where(array('bool_exempt_adh' => new Expression('false')))
            ->where('date_echeance IS NULL');

        $results = $zdb->execute($select);
        $result = $results->current();

        $chart_labels[] = _T("Never contribute");
        $chart_data[] = (int)$result->cnt;

        $soon_date = new \DateTime();
        $soon_date->modify('+30 day');

        $now = new \DateTime();

        $select = $zdb->select(Adherent::TABLE, 'a');
        $select->columns(
            array(
                'cnt' => new Expression('COUNT(a.' . Adherent::PK . ')')
            )
        )
            ->where(array('activite_adh' => new Expression('true')))
            ->where(array('bool_exempt_adh' => new Expression('false')))
            ->where->lessThanOrEqualTo('date_echeance', $soon_date->format('Y-m-d'))
            ->where->greaterThanOrEqualTo('date_echeance', $now->format('Y-m-d'));

        $results = $zdb->execute($select);
        $result = $results->current();

        $chart_labels[] = _T("Impending due dates");
        $chart_data[] = (int)$result->cnt;

        $select = $zdb->select(Adherent::TABLE, 'a');
        $select->columns(
            array(
                'cnt' => new Expression('COUNT(a.' . Adherent::PK . ')')
            )
        )
            ->where(array('activite_adh' => new Expression('true')))
            ->where(array('bool_exempt_adh' => new Expression('false')))
            ->where->greaterThan('date_echeance', $now->format('Y-m-d'));

        $results = $zdb->execute($select);
        $result = $results->current();

        $chart_labels[] = _T("Up to date");
        $chart_data[] = (int)$result->cnt;

        $select = $zdb->select(Adherent::TABLE, 'a');
        $select->columns(
            array(
                'cnt' => new Expression('COUNT(a.' . Adherent::PK . ')')
            )
        )
            ->where(array('activite_adh' => new Expression('true')))
            ->where(array('bool_exempt_adh' => new Expression('false')))
            ->where->lessThan('date_echeance', $now->format('Y-m-d'));

        $results = $zdb->execute($select);
        $result = $results->current();

        $chart_labels[] = _T("Late");
        $chart_data[] = (int)$result->cnt;

        $this->charts[self::MEMBERS_STATEDUE_PIE . 'Labels'] = json_encode($chart_labels);
        $this->charts[self::MEMBERS_STATEDUE_PIE . 'Data'] = json_encode($chart_data);
    }

    /**
     * Loads data to produce a pie chart based on company/not company members
     *
     * @return void
     */
    private function getChartCompaniesOrNot()
    {
        global $zdb;

        //non companies
        $select1 = $zdb->select(Adherent::TABLE);
        $select1->columns(
            array(
                'cnt' => new Expression('COUNT(' . Adherent::PK . ')')
            )
        )->where(
            array(
                'societe_adh IS NULL',
                'societe_adh = \'\''
            ),
            PredicateSet::OP_OR
        );

        $select2 = $zdb->select(Adherent::TABLE);
        $select2->columns(
            array(
                'cnt' => new Expression('COUNT(' . Adherent::PK . ')')
            )
        )
            ->where('societe_adh IS NOT NULL')
            ->Where('societe_adh != \'\'');

        //companies
        $select1->combine($select2);

        $results = $zdb->execute($select1);

        $result = $results->current();
        $results->next();
        $next = $results->current();

        $individuals = $result->cnt;
        $companies = 0;
        if ($next) {
            $companies = $next->cnt;
        }

        $chart_labels = [
            _T("Individuals"),
            _T("Companies")
        ];
        $chart_data = [
            (int)$individuals,
            (int)$companies
        ];
        $this->charts[self::COMPANIES_OR_NOT . 'Labels'] = json_encode($chart_labels);
        $this->charts[self::COMPANIES_OR_NOT . 'Data'] = json_encode($chart_data);
    }

    /**
     * Loads data to produce a Pie chart based on contributions types
     *
     * @return void
     */
    private function getChartContribsTypesPie()
    {
        global $zdb;

        $select = $zdb->select(ContributionsTypes::TABLE, 'a');
        $select->columns(
            array(
                'cnt'       => new Expression('COUNT(a.' . ContributionsTypes::PK . ')'),
                'label'     => 'libelle_type_cotis',
                'extends'   => 'cotis_extension'
            )
        )->join(
            array('b' => PREFIX_DB . Contribution::TABLE),
            'a.' . ContributionsTypes::PK . '=b.' . ContributionsTypes::PK,
            array()
        )
            ->order('cotis_extension')
            ->group('a.' . ContributionsTypes::PK);

        $results = $zdb->execute($select);

        $chart_labels = array();
        $chart_data = array();
        foreach ($results as $r) {
            $chart_labels[] = _T($r->label);
            $chart_data[] = (int)$r->cnt;
        }
        $this->charts[self::CONTRIBS_TYPES_PIE . 'Labels'] = json_encode($chart_labels);
        $this->charts[self::CONTRIBS_TYPES_PIE . 'Data'] = json_encode($chart_data);
    }

    /**
     * Loads data to produce a Pie chart based on contributions types
     *
     * @return void
     */
    private function getChartContribsAllTime()
    {
        global $zdb;

        $select = $zdb->select(Contribution::TABLE);

        $cols = array(
            'date'      => null,
            'amount'    => new Expression('SUM(montant_cotis)')
        );
        $groupby = null;

        if (TYPE_DB === 'pgsql') {
            $cols['date'] = new Expression('date_trunc(\'month\', date_enreg)');
            $groupby = new Expression('date_trunc(\'month\', date_enreg)');
        } elseif (TYPE_DB === 'mysql') {
            $cols['date'] = new Expression('date_format(date_enreg, \'%Y-%m\')');
            $groupby = new Expression('date_format(date_enreg, \'%Y-%m\')');
        }

        $select->columns($cols)->group($groupby)->order('date ASC');

        $results = $zdb->execute($select);

        $chart_labels = [];
        $chart_data = [];
        foreach ($results as $r) {
            $d = new \DateTime($r->date);
            $chart_labels[] = $d->format('Y-m');
            $chart_data[] = (float)$r->amount;
        }
        $this->charts[self::CONTRIBS_ALLTIME . 'Labels'] = json_encode($chart_labels);
        $this->charts[self::CONTRIBS_ALLTIME . 'Data'] = json_encode($chart_data);
    }
}
