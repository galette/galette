<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Webhooks;

use Galette\Webhooks\Signer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function Safe\base64_decode;

/**
 * Webhooks signature tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SignerTest extends TestCase
{
    //values from the Standard Webhooks reference test suite
    private const string SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
    private const string MESSAGE_ID = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
    private const int TIMESTAMP = 1614265330;
    private const string PAYLOAD = '{"test": 2432232314}';
    private const string SIGNATURE = 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=';

    /**
     * Test signature matches the specification reference
     */
    public function testSign(): void
    {
        $signer = new Signer(self::SECRET);
        $this->assertSame(self::SIGNATURE, $signer->sign(self::MESSAGE_ID, self::TIMESTAMP, self::PAYLOAD));
        $this->assertSame(
            [
                'webhook-id' => self::MESSAGE_ID,
                'webhook-timestamp' => (string)self::TIMESTAMP,
                'webhook-signature' => self::SIGNATURE,
            ],
            $signer->getHeaders(self::MESSAGE_ID, self::TIMESTAMP, self::PAYLOAD)
        );
    }

    /**
     * Test verification
     */
    public function testVerify(): void
    {
        $signer = new Signer(self::SECRET);
        $ts = (string)self::TIMESTAMP;

        $this->assertTrue($signer->verify(
            message_id: self::MESSAGE_ID,
            timestamp: $ts,
            signatures: self::SIGNATURE,
            payload: self::PAYLOAD,
            now: self::TIMESTAMP
        ));
        //one of several signatures is enough, as when a secret is rotated
        $this->assertTrue(
            $signer->verify(
                message_id: self::MESSAGE_ID,
                timestamp: $ts,
                signatures: 'v1,Zm9v ' . self::SIGNATURE,
                payload: self::PAYLOAD,
                now: self::TIMESTAMP
            )
        );
        //tampered payload, identifier or timestamp
        $this->assertFalse($signer->verify(
            message_id: self::MESSAGE_ID,
            timestamp: $ts,
            signatures: self::SIGNATURE,
            payload: '{"test": 1}',
            now: self::TIMESTAMP
        ));
        $this->assertFalse($signer->verify(
            message_id: 'msg_other',
            timestamp: $ts,
            signatures: self::SIGNATURE,
            payload: self::PAYLOAD,
            now: self::TIMESTAMP
        ));
        $this->assertFalse(
            $signer->verify(
                message_id: self::MESSAGE_ID,
                timestamp: (string)(self::TIMESTAMP + 1),
                signatures: self::SIGNATURE,
                payload: self::PAYLOAD,
                now: self::TIMESTAMP
            )
        );
        //too old, or in the future
        $this->assertFalse(
            $signer->verify(
                message_id: self::MESSAGE_ID,
                timestamp: $ts,
                signatures: self::SIGNATURE,
                payload: self::PAYLOAD,
                now: self::TIMESTAMP + Signer::TOLERANCE + 1
            )
        );
        $this->assertFalse(
            $signer->verify(
                message_id: self::MESSAGE_ID,
                timestamp: $ts,
                signatures: self::SIGNATURE,
                payload: self::PAYLOAD,
                now: self::TIMESTAMP - Signer::TOLERANCE - 1
            )
        );
        //not a timestamp
        $this->assertFalse($signer->verify(
            message_id: self::MESSAGE_ID,
            timestamp: 'abc',
            signatures: self::SIGNATURE,
            payload: self::PAYLOAD,
            now: self::TIMESTAMP
        ));
    }

    /**
     * Test generated secrets
     */
    public function testGenerateSecret(): void
    {
        $secret = Signer::generateSecret();
        $this->assertStringStartsWith(Signer::SECRET_PREFIX, $secret);
        $this->assertSame(32, strlen(base64_decode(substr($secret, strlen(Signer::SECRET_PREFIX)))));
        $this->assertNotSame($secret, Signer::generateSecret());

        //a generated secret can sign and verify
        $signer = new Signer($secret);
        $signature = $signer->sign('msg_1', 1000, '{}');
        $this->assertTrue($signer->verify(
            message_id: 'msg_1',
            timestamp: '1000',
            signatures: $signature,
            payload: '{}',
            now: 1000
        ));
    }

    /**
     * Test invalid secrets are refused
     */
    public function testInvalidSecret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Signer('MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw');
    }

    /**
     * Test secrets that are not base64 are refused
     */
    public function testInvalidBase64Secret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Signer('whsec_not base64!');
    }
}
