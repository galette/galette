<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks;

use Analog\Analog;
use Closure;
use Galette\Core\Preferences;
use Throwable;

use function Safe\fastcgi_finish_request;

/**
 * Sends deliveries once the response has been sent, for instances without cron
 *
 * With PHP-FPM, the response is flushed first and every due delivery is
 * processed, retries included. Elsewhere the visitor would wait for the
 * receivers: only the deliveries of the current request are sent then, and
 * retries are left to the next requests or to the cron script.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ShutdownDispatcher
{
    private bool $scheduled = false;

    /**
     * Constructor
     *
     * @param Preferences          $preferences Preferences instance
     * @param Closure():Dispatcher $dispatcher  Dispatcher provider, only built when needed
     */
    public function __construct(
        private readonly Preferences $preferences,
        private readonly Closure $dispatcher
    ) {
    }

    /**
     * Should deliveries be sent at the end of the request?
     *
     * Never from command line: cron scripts and console commands have
     * their own dispatching, and tests must not call anyone.
     */
    public function isEnabled(): bool
    {
        return PHP_SAPI !== 'cli' && !$this->preferences->pref_webhooks_cron;
    }

    /**
     * Schedule dispatching, once per request
     *
     * @param Closure():array<int> $deliveries Provides deliveries queued during the request
     */
    public function schedule(Closure $deliveries): void
    {
        if ($this->scheduled || !$this->isEnabled()) {
            return;
        }
        $this->scheduled = true;
        register_shutdown_function(fn() => $this->dispatch($deliveries()));
    }

    /**
     * Send deliveries
     *
     * @param array<int> $deliveries Deliveries queued during the request
     */
    public function dispatch(array $deliveries): void
    {
        try {
            /** @var Dispatcher $dispatcher */
            $dispatcher = ($this->dispatcher)();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
                $dispatcher->process();
            } else {
                $dispatcher->processIds($deliveries);
            }
        } catch (Throwable $e) {
            Analog::log('Unable to send webhooks deliveries | ' . $e->getMessage(), Analog::ERROR);
        }
    }
}
