<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Debug\Collectors;

use DebugBar\DataCollector\RequestDataCollector;

/**
 * Request collector that never shows secrets
 *
 * Masked values are replaced entirely (the parent class keeps their first and
 * last characters), and session objects are only named: a member stored in
 * session carries its password hash.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RequestCollector extends RequestDataCollector
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addMaskedKeys([
            'mdp_adh',
            'mdp_adh2',
            'mdp_*',
            //second factor code
            'code',
        ]);
    }

    /**
     * Collect data
     *
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $session = $_SESSION ?? null;
        if (is_array($session)) {
            $_SESSION = $this->nameObjects($session);
        }

        try {
            return parent::collect();
        } finally {
            if (is_array($session)) {
                $_SESSION = $session;
            }
        }
    }

    /**
     * Replace objects by their class name, recursively
     *
     * @param array<mixed> $data Data
     *
     * @return array<mixed>
     */
    private function nameObjects(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $data[$key] = '(' . $value::class . ')';
            } elseif (is_array($value)) {
                $data[$key] = $this->nameObjects($value);
            }
        }
        return $data;
    }

    /**
     * Mask a value
     *
     * @param mixed $value Value to mask
     */
    public function maskValue(mixed $value): string
    {
        return '***';
    }
}
