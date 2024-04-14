<?php

/**
 * Copyright © 2003-2024 The Galette Team
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

namespace Galette\IO;

use ArrayObject;
use DateTime;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Entity\ScheduledPayment;
use Galette\Filters\ScheduledPaymentsList;
use Galette\Repository\PaymentTypes;
use Galette\Repository\ScheduledPayments;

/**
 * Contributions CSV exports
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class ScheduledPaymentsCsv extends CsvOut
{
    private string $filename;
    private string $path;
    private Db $zdb;
    private Login $login;

    /**
     * Default constructor
     *
     * @param Db    $zdb   Db instance
     * @param Login $login Login instance
     */
    public function __construct(Db $zdb, Login $login)
    {
        $this->filename = 'filtered_shceduledpaymentslist.csv';
        $this->path = self::DEFAULT_DIRECTORY . $this->filename;
        $this->zdb = $zdb;
        $this->login = $login;
        parent::__construct();
    }

    /**
     * Export members CSV
     *
     * @param ScheduledPaymentsList $filters Current filters
     *
     * @return void
     */
    public function exportScheduledPayments(ScheduledPaymentsList $filters): void
    {
        $scheduled = new ScheduledPayment($this->zdb);
        $fields = $scheduled->getFields();
        $labels = array();

        foreach ($fields as $k => $f) {
            $label = $f['label'];
            $labels[] = $label;
        }

        $scheduleds = new ScheduledPayments($this->zdb, $this->login, $filters);
        $scheduled_list = $scheduleds->getArrayList($filters->selected);
        $ptypes = PaymentTypes::getAll(false);

        foreach ($scheduled_list as &$scheduled) {
            /** @var ArrayObject<string, int|string> $scheduled */
            if (isset($scheduled->id_paymenttype)) {
                //add textual payment type
                $scheduled->id_paymenttype = $ptypes[$scheduled->id_paymenttype];
            }

            //handle dates
            if (isset($scheduled->date)) {
                if (
                    $scheduled->date != ''
                    && $scheduled->date != '1901-01-01'
                ) {
                    $date = new DateTime($scheduled->date);
                    $scheduled->date = $date->format(__("Y-m-d"));
                } else {
                    $scheduled->date = '';
                }
            }

            if (isset($scheduled->scheduled_date)) {
                if (
                    $scheduled->scheduled_date != ''
                    && $scheduled->scheduled_date != '1901-01-01'
                ) {
                    $date = new DateTime($scheduled->scheduled_date);
                    $scheduled->scheduled_date = $date->format(__("Y-m-d"));
                } else {
                    $scheduled->scheduled_date = '';
                }
            }

            //member name
            if (isset($scheduled->{Adherent::PK})) {
                $scheduled->{Adherent::PK} = Adherent::getSName($this->zdb, $scheduled->{Adherent::PK});
            }
        }

        $fp = fopen($this->path, 'w');
        if ($fp) {
            $this->export(
                $scheduled_list,
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
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get file name
     *
     * @return string
     */
    public function getFileName(): string
    {
        return $this->filename;
    }
}
