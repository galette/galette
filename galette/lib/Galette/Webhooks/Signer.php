<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Webhooks;

use InvalidArgumentException;
use Safe\Exceptions\UrlException;

use function Safe\base64_decode;

/**
 * Webhooks signature, following the Standard Webhooks specification
 * (https://www.standardwebhooks.com/)
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final readonly class Signer
{
    public const string SECRET_PREFIX = 'whsec_';
    public const string SIGNATURE_VERSION = 'v1';
    /** Accepted clock difference when verifying a signature, in seconds */
    public const int TOLERANCE = 300;

    private string $key;

    /**
     * Constructor
     *
     * @param string $secret Webhook secret, whsec_ prefixed
     */
    public function __construct(string $secret)
    {
        if (!str_starts_with($secret, self::SECRET_PREFIX)) {
            throw new InvalidArgumentException('Webhook secret must start with ' . self::SECRET_PREFIX);
        }
        try {
            $key = base64_decode(substr($secret, strlen(self::SECRET_PREFIX)), strict: true);
        } catch (UrlException $e) {
            throw new InvalidArgumentException('Webhook secret is not valid base64.', $e->getCode(), previous: $e);
        }
        if ($key === '') {
            throw new InvalidArgumentException('Webhook secret is empty.');
        }
        $this->key = $key;
    }

    /**
     * Generate a new random secret
     */
    public static function generateSecret(): string
    {
        return self::SECRET_PREFIX . base64_encode(random_bytes(32));
    }

    /**
     * Compute signature header value
     *
     * @param string $message_id Message identifier, stable across delivery attempts
     * @param int    $timestamp  Attempt timestamp
     * @param string $payload    Raw request body
     */
    public function sign(string $message_id, int $timestamp, string $payload): string
    {
        $signature = hash_hmac(
            algo: 'sha256',
            data: sprintf('%1$s.%2$d.%3$s', $message_id, $timestamp, $payload),
            key: $this->key,
            binary: true
        );
        return self::SIGNATURE_VERSION . ',' . base64_encode($signature);
    }

    /**
     * Get all headers for a request
     *
     * @param string $message_id Message identifier, stable across delivery attempts
     * @param int    $timestamp  Attempt timestamp
     * @param string $payload    Raw request body
     *
     * @return array<string, string>
     */
    public function getHeaders(string $message_id, int $timestamp, string $payload): array
    {
        return [
            'webhook-id' => $message_id,
            'webhook-timestamp' => (string)$timestamp,
            'webhook-signature' => $this->sign($message_id, $timestamp, $payload),
        ];
    }

    /**
     * Verify a request, as a receiver would
     *
     * @param string $message_id Value of webhook-id header
     * @param string $timestamp  Value of webhook-timestamp header
     * @param string $signatures Value of webhook-signature header, space separated signatures
     * @param string $payload    Raw request body
     * @param ?int   $now        Current timestamp, defaults to time()
     */
    public function verify(
        string $message_id,
        string $timestamp,
        string $signatures,
        string $payload,
        ?int $now = null
    ): bool {
        if (!ctype_digit($timestamp) || abs(($now ?? time()) - (int)$timestamp) > self::TOLERANCE) {
            return false;
        }

        $expected = $this->sign($message_id, (int)$timestamp, $payload);
        foreach (explode(' ', $signatures) as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }
}
