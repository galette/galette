<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Filters;

use Galette\Enums\SQLOrder;
use Galette\Helpers\DatesHelper;
use Analog\Analog;
use Galette\Core\Pagination;

/**
 * History lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string $start_date_filter
 * @property ?string $raw_start_date_filter
 * @property ?string $end_date_filter
 * @property ?string $raw_end_date_filter
 * @property ?string $user_filter
 * @property ?string $action_filter
 */

class HistoryList extends Pagination
{
    use DatesHelper;

    public const int ORDERBY_DATE = 0;
    public const int ORDERBY_IP = 1;
    public const int ORDERBY_USER = 2;
    public const int ORDERBY_ACTION = 3;

    //filters
    protected ?string $start_date_filter = null;
    protected ?string $end_date_filter = null;
    private ?string $user_filter = null;
    private ?string $action_filter = null;

    /** @var array<string>  */
    protected array $list_fields = [
        'start_date_filter',
        'raw_start_date_filter',
        'end_date_filter',
        'raw_end_date_filter',
        'user_filter',
        'action_filter'
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
     * Return the default direction for ordering
     */
    protected function getDefaultDirection(): SQLOrder
    {
        return SQLOrder::DESC;
    }

    /**
     * Reinit default parameters
     */
    public function reinit(): void
    {
        parent::reinit();
        $this->start_date_filter = null;
        $this->end_date_filter = null;
        $this->user_filter = null;
        $this->action_filter = null;
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } elseif (in_array($name, $this->list_fields)) {
            return match ($name) {
                'raw_start_date_filter' => $this->getDate('start_date_filter', true, false),
                'raw_end_date_filter' => $this->getDate('end_date_filter', true, false),
                'start_date_filter', 'end_date_filter' => $this->getDate($name),
                default => $this->$name,
            };
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                static::class,
                $name
            )
        );
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrieve
     */
    public function __isset(string $name): bool
    {
        return in_array($name, $this->pagination_fields) || in_array($name, $this->list_fields);
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     */
    public function __set(string $name, mixed $value): void
    {
        if (in_array($name, $this->pagination_fields)) {
            parent::__set($name, $value);
        } else {
            Analog::log(
                '[' . static::class . '] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'start_date_filter':
                case 'end_date_filter':
                    $this->setFilterDate($name, $value, $name === 'start_date_filter');
                    break;
                default:
                    $this->$name = $value;
                    break;
            }
        }
    }
}
