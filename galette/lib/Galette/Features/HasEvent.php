<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Features;

/**
 * Translatable objects trait
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

trait HasEvent
{
    private bool $has_add_event = false;
    private bool $has_edit_event = false;
    private bool $has_delete_event = false;
    protected bool $events_active = true;

    /**
     * Get prefix for events
     */
    abstract protected function getEventsPrefix(): string;

    /**
     * Activate events
     */
    public function activateEvents(): self
    {
        $this->events_active = true;
        return $this;
    }

    /**
     * Disable events
     */
    public function disableEvents(): self
    {
        $this->events_active = false;
        return $this;
    }

    /**
     * Are events enabled
     */
    public function areEventsEnabled(): bool
    {
        return $this->events_active;
    }

    /**
     * Activate add event
     */
    public function withAddEvent(): self
    {
        $this->has_add_event = true;
        return $this;
    }

    /**
     * Disable add event
     */
    public function withoutAddEvent(): self
    {
        $this->has_add_event = false;
        return $this;
    }

    /**
     * Get add event name
     */
    public function getAddEventName(): ?string
    {
        if (!$this->hasAddEvent()) {
            return null;
        }
        return sprintf(
            '%1$s.add',
            $this->getEventsPrefix()
        );
    }

    /**
     * Has add event
     */
    public function hasAddEvent(): bool
    {
        return $this->areEventsEnabled() && $this->has_add_event;
    }

    /**
     * Activate edit event
     */
    public function withEditEvent(): self
    {
        $this->has_edit_event = true;
        return $this;
    }

    /**
     * Disable edit event
     */
    public function withoutEditEvent(): self
    {
        $this->has_edit_event = false;
        return $this;
    }

    /**
     * Get edit event name
     */
    public function getEditEventName(): ?string
    {
        if (!$this->hasEditEvent()) {
            return null;
        }
        return sprintf(
            '%1$s.edit',
            $this->getEventsPrefix()
        );
    }

    /**
     * Has edit event
     */
    public function hasEditEvent(): bool
    {
        return $this->areEventsEnabled() && $this->has_edit_event;
    }

    /**
     * Activate add event
     */
    public function withDeleteEvent(): self
    {
        $this->has_delete_event = true;
        return $this;
    }

    /**
     * Disable delete event
     */
    public function withoutDeleteEvent(): self
    {
        $this->has_delete_event = false;
        return $this;
    }

    /**
     * Get edit event name
     */
    public function getDeleteEventName(): ?string
    {
        if (!$this->hasDeleteEvent()) {
            return null;
        }
        return sprintf(
            '%1$s.delete',
            $this->getEventsPrefix()
        );
    }

    /**
     * Has delete event
     */
    public function hasDeleteEvent(): bool
    {
        return $this->areEventsEnabled() && $this->has_delete_event;
    }
}
