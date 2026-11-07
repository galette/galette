<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Debug;

use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar as BaseDebugBar;
use DebugBar\Storage\FileStorage;
use Galette\Core\Db;
use Galette\Core\Galette;
use Galette\Debug\Collectors\DatabaseCollector;
use Galette\Debug\Collectors\GaletteCollector;
use Galette\Debug\Collectors\MailsCollector;
use Galette\Debug\Collectors\RequestCollector;
use Galette\Debug\Collectors\TwigCollector;
use PHPMailer\PHPMailer\PHPMailer;
use Galette\Core\Login;
use Psr\Container\ContainerInterface;
use RKA\Session;
use Slim\Interfaces\RouteInterface;
use Throwable;
use Twig\Extension\ProfilerExtension;
use Twig\Profiler\Profile;

/**
 * Galette debug bar
 *
 * Entry point for the rest of Galette: every method is a no-op unless the bar
 * has been booted, which requires debug mode and the php-debugbar development
 * dependency. Nothing here is loaded in production.
 *
 * Data is collected from the start of each request, but only rendered and
 * stored for a session where a super administrator turned the bar on.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class DebugBar
{
    private static ?BaseDebugBar $bar = null;
    private static ?Profile $twig_profile = null;
    private const string SESSION_KEY = 'debugbar';

    /**
     * Can the debug bar be used in current context?
     */
    public static function isAvailable(): bool
    {
        return Galette::isDebugEnabled()
            && !defined('GALETTE_TESTS')
            && !defined('GALETTE_INSTALLER')
            && PHP_SAPI !== 'cli'
            && class_exists(BaseDebugBar::class);
    }

    /**
     * Is the debug bar running?
     */
    public static function isEnabled(): bool
    {
        return self::$bar !== null;
    }

    /**
     * Is the bar turned on for current session and user?
     *
     * The flag is bound to the super administrator: logging in as someone
     * else without logging out keeps session values, and must not hand them
     * the bar nor its stored requests. Impersonating a member keeps it, only
     * the super administrator can do that.
     *
     * @param Session $session Current session
     * @param Login   $login   Current login
     */
    public static function isActive(Session $session, Login $login): bool
    {
        $identity = self::getIdentity($login);
        return self::$bar !== null
            && $identity !== null
            && $session->get(self::SESSION_KEY) === $identity;
    }

    /**
     * Turn the bar on or off for current session and user
     *
     * @param Session $session Current session
     * @param Login   $login   Current login
     * @param bool    $active  Whether the bar is shown
     */
    public static function setActive(Session $session, Login $login, bool $active): void
    {
        $identity = self::getIdentity($login);
        if ($active && $identity !== null) {
            $session->set(self::SESSION_KEY, $identity);
            return;
        }

        $session->delete(self::SESSION_KEY);
        self::$bar?->getStorage()?->clear();
    }

    /**
     * Identity the flag is bound to; null for whoever cannot use the bar
     *
     * There is a single super administrator account, and impersonation is
     * reserved to it.
     *
     * @param Login $login Current login
     */
    private static function getIdentity(Login $login): ?string
    {
        if (!$login->isLogged()) {
            return null;
        }
        return $login->isSuperAdmin() || $login->isImpersonated() ? 'superadmin' : null;
    }

    /**
     * Can this user turn the bar on or off?
     *
     * @param Login $login Current login
     */
    public static function canToggle(Login $login): bool
    {
        return $login->isSuperAdmin();
    }

    /**
     * Start the debug bar, as early as possible
     */
    public static function boot(): void
    {
        if (self::$bar !== null || !self::isAvailable()) {
            return;
        }

        $bar = new BaseDebugBar();
        $bar->setHttpDriver(new Psr7HttpDriver());

        $storage = new FileStorage(GALETTE_CACHE_DIR . 'debugbar');
        $storage->setAutoPrune(24);
        $bar->setStorage(new SessionStorage($storage, new Session()));

        $time = new TimeDataCollector($_SERVER['REQUEST_TIME_FLOAT'] ?? null);

        $profiler = new QueryProfiler();
        Db::setProfiler($profiler);
        self::$twig_profile = new Profile();

        $bar->addCollector(new GaletteCollector());
        $bar->addCollector(new MessagesCollector('messages'));
        $bar->addCollector(new RequestCollector());
        $bar->addCollector((new DatabaseCollector($profiler))->withTimeDataCollector($time));
        $bar->addCollector((new TwigCollector(self::$twig_profile))->withTimeDataCollector($time));
        $bar->addCollector(new MailsCollector());
        $exceptions = new ExceptionsCollector();
        $exceptions->setChainExceptions(chainExceptions: true);
        $bar->addCollector($exceptions);
        $bar->addCollector($time);
        $bar->addCollector(new MemoryCollector());
        $bar->addCollector(new PhpInfoCollector());

        self::$bar = $bar;
    }

    /**
     * Get the debug bar
     */
    public static function get(): ?BaseDebugBar
    {
        return self::$bar;
    }

    /**
     * Get a collector, when the bar is running
     *
     * @param string $name Collector name
     */
    private static function getCollector(string $name): ?DataCollectorInterface
    {
        if (self::$bar === null || !self::$bar->hasCollector($name)) {
            return null;
        }
        return self::$bar->getCollector($name);
    }

    /**
     * Wrap Analog's handler so that log entries reach the bar too
     *
     * @param object|callable $handler Regular handler
     */
    public static function wrapLogHandler(object|callable $handler): object|callable
    {
        $messages = self::getCollector('messages');
        if (!$messages instanceof MessagesCollector) {
            return $handler;
        }
        return new AnalogHandler($handler, $messages);
    }

    /**
     * Give the bar access to Galette services
     *
     * @param ContainerInterface $container Container instance
     */
    public static function setContainer(ContainerInterface $container): void
    {
        $galette = self::getCollector('galette');
        if ($galette instanceof GaletteCollector) {
            $galette->setContainer($container);
        }
    }

    /**
     * Get Twig extension profiling templates rendering
     */
    public static function getTwigExtension(): ?ProfilerExtension
    {
        return self::$twig_profile !== null ? new ProfilerExtension(self::$twig_profile) : null;
    }

    /**
     * Record an exception
     *
     * @param Throwable $exception Exception
     */
    public static function addException(Throwable $exception): void
    {
        $collector = self::getCollector('exceptions');
        if ($collector instanceof ExceptionsCollector) {
            $collector->addThrowable($exception);
        }
    }

    /**
     * Record an email
     *
     * @param PHPMailer $mailer Mailer, after sending
     * @param bool      $sent   Whether sending succeeded
     */
    public static function addMail(PHPMailer $mailer, bool $sent): void
    {
        $collector = self::getCollector('mails');
        if ($collector instanceof MailsCollector) {
            $collector->addMail($mailer, $sent);
        }
    }

    /**
     * Start a time measure
     *
     * @param string $name  Measure name
     * @param string $label Measure label
     */
    public static function startMeasure(string $name, ?string $label = null): void
    {
        $collector = self::getCollector('time');
        if ($collector instanceof TimeDataCollector) {
            $collector->startMeasure($name, $label);
        }
    }

    /**
     * Add a time measure that already ended
     *
     * @param string $label Measure label
     * @param float  $start Start time
     * @param ?float $end   End time, defaults to now
     */
    public static function addMeasure(string $label, float $start, ?float $end = null): void
    {
        $collector = self::getCollector('time');
        if ($collector instanceof TimeDataCollector) {
            $collector->addMeasure($label, $start, $end ?? microtime(as_float: true));
        }
    }

    /**
     * Set the route that serves current request
     *
     * @param ?RouteInterface $route Matched route
     */
    public static function setRoute(?RouteInterface $route): void
    {
        $galette = self::getCollector('galette');
        if ($galette instanceof GaletteCollector) {
            $galette->setRoute($route);
        }
    }

    /**
     * Stop a time measure
     *
     * @param string $name Measure name
     */
    public static function stopMeasure(string $name): void
    {
        $collector = self::getCollector('time');
        if ($collector instanceof TimeDataCollector && $collector->hasStartedMeasure($name)) {
            $collector->stopMeasure($name);
        }
    }

    /**
     * Add a collector, from a plugin for example
     *
     * @param DataCollectorInterface $collector Collector
     */
    public static function addCollector(DataCollectorInterface $collector): void
    {
        self::$bar?->addCollector($collector);
    }
}
