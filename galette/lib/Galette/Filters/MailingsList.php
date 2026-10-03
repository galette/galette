<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Filters;

use Galette\Core\MailingHistory;

/**
 * Mailings history lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string $start_date_filter
 * @property string  $raw_start_date_filter
 * @property ?string $end_date_filter
 * @property string  $raw_end_date_filter
 * @property int     $sender_filter
 * @property int     $sent_filter
 * @property ?string $subject_filter
 */

class MailingsList extends HistoryList
{
    public const int ORDERBY_DATE = 0;
    public const int ORDERBY_SENDER = 1;
    public const int ORDERBY_SUBJECT = 2;
    public const int ORDERBY_SENT = 3;

    //filters
    protected int $sender_filter = 0;
    protected int $sent_filter = MailingHistory::FILTER_DC_SENT;
    protected ?string $subject_filter = null;

    /** @var array<string>  */
    protected array $list_fields = [
        'start_date_filter',
        'raw_start_date_filter',
        'end_date_filter',
        'raw_end_date_filter',
        'sender_filter',
        'sent_filter',
        'subject_filter'
    ];

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->reinit();
    }

    /**
     * Returns the field we want to default set order to
     */
    protected function getDefaultOrder(): int|string
    {
        return self::ORDERBY_DATE;
    }

    /**
     * Reinit default parameters
     */
    public function reinit(): void
    {
        parent::reinit();
        $this->sender_filter = 0;
        $this->sent_filter = MailingHistory::FILTER_DC_SENT;
        $this->subject_filter = null;
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     */
    public function __set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'sent_filter':
                $this->$name = (int)$value;
                break;
            default:
                parent::__set($name, $value);
                break;
        }
    }
}
