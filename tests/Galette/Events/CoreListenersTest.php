<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Events\test\units;

use ArrayObject;
use DI\ContainerBuilder;
use Galette\Events\ContribListener;
use Galette\Events\GaletteEvent;
use Galette\Events\MemberListener;
use Galette\Tests\Fixtures\RecordingSubscriber;
use League\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;

/**
 * Core listeners are subscribed to the container event dispatcher
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CoreListenersTest extends TestCase
{
    /**
     * Member and contribution listeners receive dispatched events
     */
    public function testCoreListenersSubscribed(): void
    {
        /** @var ArrayObject<int, string> $received */
        $received = new ArrayObject();
        $builder = new ContainerBuilder();
        $builder->addDefinitions(require GALETTE_ROOT . 'includes/dependencies.php');
        $builder->addDefinitions([
            MemberListener::class => new RecordingSubscriber(['member.add', 'member.edit'], $received),
            ContribListener::class => new RecordingSubscriber(['contribution.add'], $received),
        ]);
        $container = $builder->build();

        $dispatcher = $container->get(EventDispatcher::class);
        foreach (['member.add', 'member.edit', 'contribution.add'] as $name) {
            $dispatcher->dispatch(new GaletteEvent($name, new \stdClass()));
        }

        $this->assertSame(['member.add', 'member.edit', 'contribution.add'], $received->getArrayCopy());
    }
}
