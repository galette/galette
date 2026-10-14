<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core;

use Slim\Flash\Messages;

/**
 * Flash messages, consumed when they are read
 *
 * Slim drops messages from storage as soon as it is instantiated, that is on
 * each request. A request that never displays them (an ajax call, a picture)
 * then loses the messages a previous one has left for the next page.
 *
 * Messages from previous request are kept in storage until they are read.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class FlashMessages extends Messages
{
    private bool $consumed = false;

    /**
     * Create new Flash messages service provider
     *
     * @param null|array<mixed>|\ArrayAccess<mixed, mixed> $storage    Storage
     * @param ?string                                      $storageKey Storage key
     */
    public function __construct(array|\ArrayAccess|null &$storage = null, ?string $storageKey = null)
    {
        parent::__construct($storage, $storageKey);
        //keep previous messages until they are read
        $this->storage[$this->storageKey] = $this->fromPrevious;
    }

    /**
     * Get flash messages; reading them consumes the ones from previous request
     *
     * @return array<string, array<int, mixed>>
     */
    public function getMessages(): array
    {
        if (!$this->consumed) {
            $this->consumed = true;
            //previous messages come first in storage, messages added since then are kept
            foreach ($this->fromPrevious as $key => $messages) {
                $remaining = array_slice($this->storage[$this->storageKey][$key] ?? [], count($messages));
                if (count($remaining) > 0) {
                    $this->storage[$this->storageKey][$key] = $remaining;
                } else {
                    unset($this->storage[$this->storageKey][$key]);
                }
            }
        }
        return parent::getMessages();
    }

    /**
     * Clear all messages
     */
    public function clearMessages(): void
    {
        parent::clearMessages();
        $this->consumed = true;
    }
}
