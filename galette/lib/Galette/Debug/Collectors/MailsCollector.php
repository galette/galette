<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Debug\Collectors;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sent emails collector
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MailsCollector extends DataCollector implements Renderable, AssetProvider
{
    /** @var list<array<string, mixed>> */
    private array $mails = [];

    /**
     * Record a message handed to PHPMailer
     *
     * @param PHPMailer $mailer Mailer, after sending
     * @param bool      $sent   Whether sending succeeded
     */
    public function addMail(PHPMailer $mailer, bool $sent): void
    {
        $addresses = static fn(array $list): string => implode(
            ', ',
            array_map(static fn(array $address): string => (string)$address[0], $list)
        );

        $headers = [
            'Method: ' . $mailer->Mailer,
            'Status: ' . ($sent ? 'sent' : 'failed: ' . $mailer->ErrorInfo),
            'From: ' . $mailer->From,
        ];
        if (count($mailer->getCcAddresses()) > 0) {
            $headers[] = 'Cc: ' . $addresses($mailer->getCcAddresses());
        }
        if (count($mailer->getBccAddresses()) > 0) {
            $headers[] = 'Bcc: ' . $addresses($mailer->getBccAddresses());
        }

        $html = $mailer->ContentType === PHPMailer::CONTENT_TYPE_TEXT_HTML;
        $this->mails[] = [
            'to' => $addresses($mailer->getToAddresses()),
            'subject' => ($sent ? '' : '[FAILED] ') . $mailer->Subject,
            'headers' => implode("\n", $headers),
            'body' => $html ? $mailer->AltBody : $mailer->Body,
            'html' => $html ? $mailer->Body : null,
            'attachments' => array_map(
                static fn(array $attachment): string => (string)$attachment[2],
                $mailer->getAttachments()
            ),
        ];
    }

    /**
     * Collect data
     *
     * @return array{count: int, mails: list<array<string, mixed>>}
     */
    public function collect(): array
    {
        return [
            'count' => count($this->mails),
            'mails' => $this->mails,
        ];
    }

    /**
     * Collector name
     */
    public function getName(): string
    {
        return 'mails';
    }

    /**
     * Widgets
     *
     * @return array<string, array<string, mixed>>
     */
    public function getWidgets(): array
    {
        return [
            'mails' => [
                'icon' => 'mail',
                'widget' => 'PhpDebugBar.Widgets.MailsWidget',
                'map' => 'mails.mails',
                'default' => '[]',
            ],
            'mails:badge' => [
                'map' => 'mails.count',
                'default' => 0,
            ],
        ];
    }

    /**
     * Assets
     *
     * @return array<string, string>
     */
    public function getAssets(): array
    {
        return [
            'css' => 'widgets/mails/widget.css',
            'js' => 'widgets/mails/widget.js',
        ];
    }
}
