<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Updates;

use Galette\Entity\PaymentType;
use Galette\Updater\AbstractUpdater;
use Galette\Updater\PaymentTypesIdsFix;
use Galette\Updater\ScheduledPaymentsFix;

/**
 * Galette 1.2.1 upgrade script
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UpgradeTo121 extends AbstractUpdater
{
    use PaymentTypesIdsFix;
    use ScheduledPaymentsFix;

    protected ?string $db_version = '1.21';

    /**
     * Pre stuff, if any.
     * Will be executed first.
     */
    protected function preUpdate(): bool
    {
        return $this->fixScheduledPaymentsTable();
    }

    /**
     * Update instructions
     */
    protected function update(): bool
    {
        return $this->freeSystemPaymentTypesIds(
            [
                PaymentType::STRIPE => 'stripe', //payment type 8 is "Stripe"
                PaymentType::HELLOASSO => 'helloasso' //payment type 9 is "HelloAsso"
            ]
        );
    }
}
