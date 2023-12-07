<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contributions CSV exports
 *
 * PHP version 5
 *
 * Copyright © 2021-2023 The Galette Team
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
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.6-dev - 2021-11-07
 */

namespace Galette\IO;

use ArrayObject;
use DateTime;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Entity\ContributionsTypes;
use Galette\Repository\Contributions;
use Galette\Filters\ContributionsList;
use Galette\Repository\PaymentTypes;

/**
 * Contributions CSV exports
 *
 * @category  IO
 * @name      Csv
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2021-2023 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.6-dev - 2021-11-07
 */

class ContributionsCsv extends CsvOut
{
    private $filename;
    private $path;
    private $zdb;
    private $login;
    private $type;

    /**
     * Default constructor
     *
     * @param Db     $zdb   Db instance
     * @param Login  $login Login instance
     * @param string $type  One of 'contributions' or 'transactions'
     */
    public function __construct(Db $zdb, Login $login, string $type)
    {
        $this->filename = 'filtered_' . $type . 'list.csv';
        $this->path = self::DEFAULT_DIRECTORY . $this->filename;
        $this->zdb = $zdb;
        $this->login = $login;
        $this->type = $type;
        parent::__construct();
    }

    /**
     * Export members CSV
     *
     * @param ContributionsList $filters Current filters
     *
     * @return void
     */
    public function exportContributions(ContributionsList $filters)
    {
        $class = '\\Galette\\Entity\\' . ucwords(trim($this->type, 's'));
        $contrib = new $class($this->zdb, $this->login);

        $fields = $contrib->fields;
        //not a real data
        unset($fields['duree_mois_cotis']);
        $labels = array();

        foreach ($fields as $k => $f) {
            $label = $f['label'];
            if (isset($f['cotlabel'])) {
                $label = $f['cotlabel'] . ' / ' . $label;
            }
            $labels[] = $label;
        }

        $contributions = new Contributions($this->zdb, $this->login, $filters);
        $contributions_list = $contributions->getArrayList($filters->selected);

        $ptypes = PaymentTypes::getAll();
        $ctype = new ContributionsTypes($this->zdb);

        foreach ($contributions_list as &$contribution) {
            /** @var ArrayObject $contribution */
            if (isset($contribution->type_paiement_cotis)) {
                //add textual payment type
                $contribution->type_paiement_cotis = $ptypes[$contribution->type_paiement_cotis];
            }

            //add textual type
            $contribution->id_type_cotis = $ctype->getLabel($contribution->id_type_cotis);

            //handle dates
            if (isset($contribution->date)) {
                if (
                    $contribution->date != ''
                    && $contribution->date != '1901-01-01'
                ) {
                    $date = new DateTime($contribution->date);
                    $contribution->date = $date->format(__("Y-m-d"));
                } else {
                    $contribution->date = '';
                }
            }

            if (isset($contribution->date_debut_cotis)) {
                if (
                    $contribution->date_debut_cotis != ''
                    && $contribution->date_debut_cotis != '1901-01-01'
                ) {
                    $date = new DateTime($contribution->date_debut_cotis);
                    $contribution->date_debut_cotis = $date->format(__("Y-m-d"));
                } else {
                    $contribution->date_debut_cotis = '';
                }
            }

            if (isset($contribution->date_fin_cotis)) {
                if (
                    $contribution->date_fin_cotis != ''
                    && $contribution->date_fin_cotis != '1901-01-01'
                ) {
                    $date = new DateTime($contribution->date_fin_cotis);
                    $contribution->date_fin_cotis = $date->format(__("Y-m-d"));
                } else {
                    $contribution->date_fin_cotis = '';
                }
            }

            //member name
            if (isset($contribution->{Adherent::PK})) {
                $contribution->{Adherent::PK} = Adherent::getSName($this->zdb, $contribution->{Adherent::PK});
            }
        }

        $fp = fopen($this->path, 'w');
        if ($fp) {
            $this->export(
                $contributions_list,
                self::DEFAULT_SEPARATOR,
                self::DEFAULT_QUOTE,
                $labels,
                $fp
            );
            fclose($fp);
        }
    }

    /**
     * Get file path on disk
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get file name
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->filename;
    }
}
