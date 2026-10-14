<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\FlashMessages;
use PHPUnit\Framework\TestCase;

/**
 * Flash messages tests
 *
 * Each instance stands for a request sharing the same session storage.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class FlashMessagesTest extends TestCase
{
    /**
     * A request that does not read messages keeps them for the next one
     */
    public function testUnreadMessagesAreKept(): void
    {
        $storage = [];

        //form submission
        $flash = new FlashMessages($storage);
        $flash->addMessage('success_detected', 'Member has been stored');

        //ajax call, before the page the form redirects to
        $flash = new FlashMessages($storage);
        $flash->addMessage('info', 'From ajax');

        //page displaying messages
        $flash = new FlashMessages($storage);
        $this->assertSame(
            [
                'success_detected' => ['Member has been stored'],
                'info' => ['From ajax'],
            ],
            $flash->getMessages()
        );

        //next page: messages have been consumed
        $flash = new FlashMessages($storage);
        $this->assertSame([], $flash->getMessages());
    }

    /**
     * Reading messages keeps the ones added for the next request
     */
    public function testReadKeepsAddedMessages(): void
    {
        $storage = [];

        $flash = new FlashMessages($storage);
        $flash->addMessage('error_detected', 'Previous');

        $flash = new FlashMessages($storage);
        $flash->addMessage('success_detected', 'Before reading');
        $this->assertSame(['Previous'], $flash->getMessage('error_detected'));
        $this->assertNull($flash->getMessage('success_detected'));
        $flash->addMessage('success_detected', 'After reading');

        $flash = new FlashMessages($storage);
        $this->assertSame(
            ['success_detected' => ['Before reading', 'After reading']],
            $flash->getMessages()
        );
    }

    /**
     * Clearing messages drops them all
     */
    public function testClearMessages(): void
    {
        $storage = [];

        $flash = new FlashMessages($storage);
        $flash->addMessage('error_detected', 'Previous');

        $flash = new FlashMessages($storage);
        $flash->addMessage('success_detected', 'Current');
        $flash->clearMessages();

        $flash = new FlashMessages($storage);
        $this->assertSame([], $flash->getMessages());
    }
}
