<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

declare(strict_types=1);

namespace Galette\Filters;

use Throwable;
use Analog\Analog;
use Galette\Core\Pagination;
use Galette\Core\MailingHistory;

/**
 * Mailings history lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string $start_date_filter
 * @property string $raw_start_date_filter
 * @property ?string $end_date_filter
 * @property string $raw_end_date_filter
 * @property int $sender_filter
 * @property int $sent_filter
 * @property ?string $subject_filter
 */

class MailingsList extends HistoryList
{
    public const ORDERBY_DATE = 0;
    public const ORDERBY_SENDER = 1;
    public const ORDERBY_SUBJECT = 2;
    public const ORDERBY_SENT = 3;

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
     *
     * @return int|string
     */
    protected function getDefaultOrder(): int|string
    {
        return self::ORDERBY_DATE;
    }

    /**
     * Reinit default parameters
     *
     * @return void
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
     *
     * @return void
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
