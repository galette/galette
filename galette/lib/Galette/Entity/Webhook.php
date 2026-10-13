<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Entity;

use ArrayObject;
use Galette\Core\Authentication;
use Galette\Entity\Attributes\Column;
use Galette\Webhooks\Signer;
use Galette\Webhooks\WebhookEvent;
use Safe\Exceptions\UrlException;

use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\parse_url;

/**
 * Outgoing webhook
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Webhook extends AbstractEntity
{
    public const string TABLE = 'webhooks';
    public const string PK = 'id_webhook';

    public const int NAME_MAX_LENGTH = 100;
    public const int URL_MAX_LENGTH = 2048;

    #[Column(self::PK)]
    protected int $id;
    #[Column('name')]
    private string $name = '';
    #[Column('url')]
    private string $url = '';
    #[Column('secret')]
    private string $secret = '';
    /** JSON list of subscribed events values */
    #[Column('events')]
    private string $events = '[]';
    /** Access level used to filter personal data sent */
    #[Column('access_level')]
    private int $access_level = Authentication::ACCESS_STAFF;
    #[Column('active')]
    private bool $active = true;
    #[Column('creation_date', updatable: false)]
    private string $creation_date;
    #[Column('modification_date')]
    private string $modification_date;

    /**
     * Main constructor
     *
     * @param int|ArrayObject<string, int|string>|null $args Identifier to load, or database row
     */
    public function __construct(int|ArrayObject|null $args = null)
    {
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Access levels a webhook can be given
     *
     * @return array<int, string>
     */
    public static function getAccessLevels(): array
    {
        return [
            Authentication::ACCESS_MANAGER => _T('Group manager'),
            Authentication::ACCESS_STAFF => _T('Staff member'),
            Authentication::ACCESS_ADMIN => _T('Administrator'),
        ];
    }

    /**
     * Is it a webhook not stored yet?
     */
    public function isNew(): bool
    {
        return !isset($this->id);
    }

    /**
     * Set dates, and generate a secret when none has been set
     */
    protected function preInsert(): bool
    {
        $this->creation_date = date('Y-m-d H:i:s');
        $this->modification_date = $this->creation_date;
        if ($this->secret === '') {
            $this->secret = Signer::generateSecret();
        }
        return true;
    }

    /**
     * Set modification date
     */
    protected function preUpdate(): bool
    {
        $this->modification_date = date('Y-m-d H:i:s');
        return true;
    }

    /**
     * Check values before storage
     *
     * @return list<string> Translated error messages, empty when everything is fine
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->name === '') {
            $errors[] = _T('Name is mandatory.');
        } elseif (mb_strlen($this->name) > self::NAME_MAX_LENGTH) {
            $errors[] = sprintf(
                //TRANS: %1$d is the maximum number of characters
                _T('Name must not exceed %1$d characters.'),
                self::NAME_MAX_LENGTH
            );
        }

        if (!self::isValidUrl($this->url)) {
            $errors[] = _T('URL must be a valid http or https address, without credentials.');
        }

        if ($this->getEvents() === []) {
            $errors[] = _T('Select at least one event.');
        }

        if (!array_key_exists($this->access_level, self::getAccessLevels())) {
            $errors[] = _T('Invalid access level.');
        }

        return $errors;
    }

    /**
     * Is URL acceptable as a webhook target?
     *
     * Only http and https are allowed, and credentials must not be part
     * of the URL: they would be stored and displayed in clear.
     *
     * @param string $url URL to check
     */
    public static function isValidUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > self::URL_MAX_LENGTH || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        try {
            $parts = parse_url($url);
        } catch (UrlException) {
            // no log: an invalid URL is a validation failure, reported to the user
            return false;
        }
        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], strict: true)
            && ($parts['host'] ?? '') !== ''
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    /**
     * Is URL using plain http?
     */
    public function isInsecure(): bool
    {
        return str_starts_with(strtolower($this->url), 'http://');
    }

    /**
     * Get name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set name
     *
     * @param string $name Name
     */
    public function setName(string $name): self
    {
        $this->name = trim(strip_tags($name));
        return $this;
    }

    /**
     * Get URL
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set URL
     *
     * @param string $url Target URL
     */
    public function setUrl(string $url): self
    {
        $this->url = trim($url);
        return $this;
    }

    /**
     * Get secret
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * Replace secret with a new random one
     */
    public function regenerateSecret(): self
    {
        $this->secret = Signer::generateSecret();
        return $this;
    }

    /**
     * Get subscribed events
     *
     * Unknown values, from an event that would have been removed, are ignored.
     *
     * @return list<WebhookEvent>
     */
    public function getEvents(): array
    {
        $values = json_decode($this->events, associative: true);
        if (!is_array($values)) {
            return [];
        }
        $events = [];
        foreach ($values as $value) {
            $event = is_string($value) ? WebhookEvent::tryFrom($value) : null;
            if ($event !== null && $event->getInternalName() !== null) {
                $events[] = $event;
            }
        }
        return $events;
    }

    /**
     * Set subscribed events
     *
     * @param array<WebhookEvent> $events Events
     */
    public function setEvents(array $events): self
    {
        $values = [];
        foreach ($events as $event) {
            if ($event->getInternalName() !== null) {
                $values[$event->value] = $event->value;
            }
        }
        $this->events = json_encode(array_values($values));
        return $this;
    }

    /**
     * Is webhook subscribed to given event?
     *
     * @param WebhookEvent $event Event
     */
    public function subscribesTo(WebhookEvent $event): bool
    {
        return in_array($event, $this->getEvents(), strict: true);
    }

    /**
     * Get access level
     */
    public function getAccessLevel(): int
    {
        return $this->access_level;
    }

    /**
     * Set access level
     *
     * @param int $access_level One of Authentication::ACCESS_* constants
     */
    public function setAccessLevel(int $access_level): self
    {
        $this->access_level = $access_level;
        return $this;
    }

    /**
     * Is webhook active?
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Set active
     *
     * @param bool $active Active or not
     */
    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    /**
     * Get creation date
     */
    public function getCreationDate(): ?string
    {
        return $this->creation_date ?? null;
    }

    /**
     * Get modification date
     */
    public function getModificationDate(): ?string
    {
        return $this->modification_date ?? null;
    }
}
