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
 * Transactions lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string   $start_date_filter
 * @property ?string   $end_date_filter
 * @property ?int      $filtre_cotis_adh
 * @property int|false $filtre_cotis_children
 * @property string    $rstart_date_filter
 * @property string    $rend_date_filter
 * @property ?int      $max_amount
 */
class TransactionsList extends Pagination
{
    use DatesHelper;

    public const int ORDERBY_DATE = 0;
    public const int ORDERBY_MEMBER = 3;
    public const int ORDERBY_AMOUNT = 5;
    public const int ORDERBY_PAYMENT_TYPE = 7;
    public const int ORDERBY_ID = 8;

    //filters
    private ?string $start_date_filter = null;
    private ?string $end_date_filter = null;
    private ?int $filtre_cotis_adh = null;
    private int|false $filtre_cotis_children = false;
    private ?int $max_amount = null;

    /** @var array<string> */
    protected array $list_fields = [
        'start_date_filter',
        'end_date_filter',
        'filtre_cotis_adh',
        'filtre_cotis_children'
    ];

    /** @var array<string> */
    protected array $virtuals_list_fields = [
        'rstart_date_filter',
        'rend_date_filter'
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
        $this->filtre_cotis_adh = null;
        $this->filtre_cotis_children = false;
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
        } elseif (in_array($name, $this->list_fields) || in_array($name, $this->virtuals_list_fields)) {
            switch ($name) {
                case 'start_date_filter':
                case 'end_date_filter':
                    return $this->getDate($name);
                case 'rstart_date_filter':
                case 'rend_date_filter':
                    //same as above, but raw format
                    $rname = substr($name, 1);
                    return $this->getDate($rname, true, false);
                default:
                    return $this->$name;
            }
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
        return in_array($name, $this->pagination_fields) || in_array($name, $this->list_fields) || in_array($name, $this->virtuals_list_fields);
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
                '[TransactionsList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'start_date_filter':
                case 'end_date_filter':
                    $this->setFilterDate($name, $value, $name === 'start_date_filter');
                    break;
                case 'filtre_cotis_adh':
                    $this->$name = (int)$value;
                    break;
                default:
                    $this->$name = $value;
                    break;
            }
        }
    }
}
