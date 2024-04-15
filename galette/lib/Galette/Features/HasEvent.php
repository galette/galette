<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
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
     *
     * @return string
     */
    abstract protected function getEventsPrefix(): string;

    /**
     * Activate events
     *
     * @return self
     */
    public function activateEvents(): self
    {
        $this->events_active = true;
        return $this;
    }

    /**
     * Disable events
     *
     * @return self
     */
    public function disableEvents(): self
    {
        $this->events_active = false;
        return $this;
    }

    /**
     * Are events enabled
     *
     * @return bool
     */
    public function areEventsEnabled(): bool
    {
        return $this->events_active;
    }

    /**
     * Activate add event
     *
     * @return self
     */
    public function withAddEvent(): self
    {
        $this->has_add_event = true;
        return $this;
    }

    /**
     * Disable add event
     *
     * @return self
     */
    public function withoutAddEvent(): self
    {
        $this->has_add_event = false;
        return $this;
    }

    /**
     * Get add event name
     *
     * @return ?string
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
     *
     * @return bool
     */
    public function hasAddEvent(): bool
    {
        return $this->areEventsEnabled() && $this->has_add_event;
    }

    /**
     * Activate edit event
     *
     * @return self
     */
    public function withEditEvent(): self
    {
        $this->has_edit_event = true;
        return $this;
    }

    /**
     * Disable edit event
     *
     * @return self
     */
    public function withoutEditEvent(): self
    {
        $this->has_edit_event = false;
        return $this;
    }

    /**
     * Get edit event name
     *
     * @return ?string
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
     *
     * @return bool
     */
    public function hasEditEvent(): bool
    {
        return $this->areEventsEnabled() && $this->has_edit_event;
    }

    /**
     * Activate add event
     *
     * @return self
     */
    public function withDeleteEvent(): self
    {
        $this->has_delete_event = true;
        return $this;
    }

    /**
     * Disable delete event
     *
     * @return self
     */
    public function withoutDeleteEvent(): self
    {
        $this->has_delete_event = false;
        return $this;
    }

    /**
     * Get edit event name
     *
     * @return ?string
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
     *
     * @return bool
     */
    public function hasDeleteEvent(): bool
    {
        return $this->areEventsEnabled() && $this->has_delete_event;
    }
}
