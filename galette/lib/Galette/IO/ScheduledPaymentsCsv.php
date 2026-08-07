<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

use ArrayObject;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Entity\ScheduledPayment;
use Galette\Filters\ScheduledPaymentsList;
use Galette\Repository\PaymentTypes;
use Galette\Repository\ScheduledPayments;
use Safe\DateTime;
use Safe\Exceptions\FilesystemException;

use function Safe\fclose;
use function Safe\fopen;

/**
 * Contributions CSV exports
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class ScheduledPaymentsCsv extends CsvOut
{
    private readonly string $filename;
    private readonly string $path;

    /**
     * Default constructor
     *
     * @param Db    $zdb   Db instance
     * @param Login $login Login instance
     */
    public function __construct(
        private readonly Db $zdb,
        private readonly Login $login
    ) {
        $this->filename = 'filtered_scheduledpaymentslist.csv';
        $this->path = self::DEFAULT_DIRECTORY . $this->filename;
        parent::__construct();
    }

    /**
     * Export members CSV
     *
     * @param ScheduledPaymentsList $filters Current filters
     */
    public function exportScheduledPayments(ScheduledPaymentsList $filters): void
    {
        $scheduled = new ScheduledPayment($this->zdb);
        $fields = $scheduled->getFields();
        $labels = [];

        foreach ($fields as $f) {
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

        try {
            $fp = fopen($this->path, 'w');
            $this->export(
                rs: $scheduled_list,
                separator: self::DEFAULT_SEPARATOR,
                quote: self::DEFAULT_QUOTE,
                titles: $labels,
                file: $fp
            );
            fclose($fp);
        } catch (FilesystemException) {
            //empty catch
        }
    }

    /**
     * Get file path on disk
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get file name
     */
    public function getFileName(): string
    {
        return $this->filename;
    }
}
