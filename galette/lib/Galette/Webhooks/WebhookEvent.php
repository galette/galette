<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks;

/**
 * Public catalog of webhooks events
 *
 * Values are part of the public contract with receivers. They are decoupled
 * from internal events names, which can then change without breaking anyone.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
enum WebhookEvent: string
{
    case MemberCreated = 'member.created';
    case MemberUpdated = 'member.updated';
    case MemberDeleted = 'member.deleted';
    case ContributionCreated = 'contribution.created';
    case ContributionUpdated = 'contribution.updated';
    case ContributionDeleted = 'contribution.deleted';
    case TransactionCreated = 'transaction.created';
    case TransactionUpdated = 'transaction.updated';
    case TransactionDeleted = 'transaction.deleted';
    case GroupCreated = 'group.created';
    case GroupUpdated = 'group.updated';
    case GroupDeleted = 'group.deleted';
    /** Test event, sent on demand from the webhook page */
    case Ping = 'webhook.ping';

    /**
     * Internal event this one is sent for, if any
     */
    public function getInternalName(): ?string
    {
        return match ($this) {
            self::MemberCreated => 'member.add',
            self::MemberUpdated => 'member.edit',
            self::MemberDeleted => 'member.remove',
            self::ContributionCreated => 'contribution.add',
            self::ContributionUpdated => 'contribution.edit',
            self::ContributionDeleted => 'contribution.remove',
            self::TransactionCreated => 'transaction.add',
            self::TransactionUpdated => 'transaction.edit',
            self::TransactionDeleted => 'transaction.remove',
            self::GroupCreated => 'group.add',
            self::GroupUpdated => 'group.edit',
            self::GroupDeleted => 'group.remove',
            self::Ping => null,
        };
    }

    /**
     * Get public event from an internal event name
     *
     * @param string $name Internal event name
     */
    public static function fromInternal(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getInternalName() === $name) {
                return $case;
            }
        }
        return null;
    }

    /**
     * Events a webhook can subscribe to
     *
     * @return list<self>
     */
    public static function subscribable(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn(self $event): bool => $event->getInternalName() !== null
        ));
    }

    /**
     * Subscribable events, grouped by subject
     *
     * @return array<string, list<self>> Subject label as key
     */
    public static function grouped(): array
    {
        $grouped = [];
        foreach (self::subscribable() as $event) {
            $grouped[$event->getSubjectLabel()][] = $event;
        }
        return $grouped;
    }

    /**
     * Get subject, the part before the dot
     */
    public function getSubject(): string
    {
        return explode('.', $this->value)[0];
    }

    /**
     * Get translated subject label
     */
    public function getSubjectLabel(): string
    {
        return match ($this->getSubject()) {
            'member' => _T('Members'),
            'contribution' => _T('Contributions'),
            'transaction' => _T('Transactions'),
            'group' => _T('Groups'),
            default => _T('Webhooks'),
        };
    }

    /**
     * Get translated label
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::MemberCreated => _T('Member created'),
            self::MemberUpdated => _T('Member updated'),
            self::MemberDeleted => _T('Member deleted'),
            self::ContributionCreated => _T('Contribution created'),
            self::ContributionUpdated => _T('Contribution updated'),
            self::ContributionDeleted => _T('Contribution deleted'),
            self::TransactionCreated => _T('Transaction created'),
            self::TransactionUpdated => _T('Transaction updated'),
            self::TransactionDeleted => _T('Transaction deleted'),
            self::GroupCreated => _T('Group created'),
            self::GroupUpdated => _T('Group updated'),
            self::GroupDeleted => _T('Group deleted'),
            self::Ping => _T('Test event'),
        };
    }
}
