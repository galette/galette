<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Filters;

use Galette\Helpers\DatesHelper;
use Galette\Core\Pagination;

/**
 * Documents list filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string $start_date_filter
 * @property ?string $end_date_filter
 * @property string  $raw_start_date_filter
 * @property string  $raw_end_date_filter
 * @property ?string $filename_filter
 * @property ?string $type_filter
 * @property ?string $visibility_filter
 */

class DocumentsList extends Pagination
{
    use DatesHelper;

    public const int ORDERBY_DATE = 0;
    public const int ORDERBY_TYPE = 1;
    public const int ORDERBY_NAME = 2;
    public const int ORDERBY_ID = 3;

    //filters
    private ?string $start_date_filter = null;
    private ?string $end_date_filter = null;
    private ?string $filename_filter = null;
    private ?string $type_filter = null;
    private ?string $visibility_filter = null;

    /** @var array<string>  */
    protected array $list_fields = [
        'start_date_filter',
        'end_date_filter',
        'filename_filter',
        'type_filter',
        'visibility_filter',
    ];

    /** @var array<string>  */
    protected array $virtuals_list_fields = [
        'raw_start_date_filter',
        'raw_end_date_filter'
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
        $this->start_date_filter = null;
        $this->end_date_filter = null;
        $this->filename_filter = null;
        $this->type_filter = null;
        $this->visibility_filter = null;
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
                case 'raw_start_date_filter':
                case 'raw_end_date_filter':
                    //same as above, but raw format
                    $rname = substr($name, 4);
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
