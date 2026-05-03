<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Mailing tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GaletteMail extends GaletteTestCase
{
    /**
     * Data provider for testIsURL
     *
     * @return array<array{string, bool}>
     */
    public static function urlProvider(): array
    {
        return [
            ['http://galette.eu', true],
            ['http://galette', true],
            ['https://galette.eu', true],
            ['ftp://galette.eu', false],
            ['http://galette.eu/somepath?query=string', true],
            ['not a url', false],
            ['http:/galette.eu', false],
            ['http//galette.eu', false],
            ['galette.eu', false],
            ['', false],
            ['any://galette.eu', false],
            ['http://256.256.256.256', true],
            ['https://www.galette.eu/~johan/page.html', true],
            ['https://user:pass@galette.eu/page.html', true],
            ['https://@galette.eu/page.html', false],
            ['https://[2001:470:30:84:e276:63ff:fe72:3900]/page.html', true]
        ];
    }

    /**
     * Test isURL
     *
     * @param string $url      URL to test
     * @param bool   $expected Expected result
     */
    #[DataProvider('urlProvider')]
    public function testIsURL(string $url, bool $expected): void
    {
        $this->assertSame($expected, \Galette\Core\GaletteMail::isURL($url));
    }

    /**
     * Data provider for testIsValidEmail
     *
     * @return array<array{string, bool}>
     */
    public static function emailProvider(): array
    {
        return [
            ['contact@galette.eu', true],
            ['sthing+contact@galette.eu', true],
            ['sthing+contact@my.galette.eu', true],
            ['contact@1.2.3.4', false], //valie IP, not between brackets
            ['contact@[1.2.3.4]', true],
            ['"contact"@galette.eu', true],
            ['contact', false],
            ['@galette.eu', false],
            ['contact.galette.eu', false],
            ['contact@galette', false], //should be true
            ['contact@galette,eu', false],
            ['contact@galette..eu', false],
            ['contact@.eu', false],
            ['contact@galette.u', true],
            ['contact@256.256.256.256', false], //invalid IP anyway
            ['"contact"@galette.eu', true],
            ['"conta ct"@galette.eu', false],
            ['"conta\\ct"@galette.eu', true],
            ['"conta ct"@gale tte.eu', false],
            ['あいうえお@galette.eu', false]
        ];
    }

    /**
     * Test isValidEmail
     *
     * @param string $email    Email to test
     * @param bool   $expected Expected result
     */
    #[DataProvider('emailProvider')]
    public function testIsValidEmail(string $email, bool $expected): void
    {
        $this->assertSame($expected, \Galette\Core\GaletteMail::isValidEmail($email));
    }

    /**
     * Test setRecipients
     */
    public function testSetRecipients(): void
    {
        $mail = new \Galette\Core\GaletteMail($this->preferences);
        $mail->setRecipients([]);
        $this->expectNoLogEntry();

        $mail->setRecipients(['contact@galette.eu' => 'Contact']); //just a valid email
        $this->expectNoLogEntry();

        $mail->setRecipients(['contact.galette.eu' => 'Contact']); //just an invalid email
        $this->expectLogEntry(\Analog\Analog::INFO, '[Galette\Core\GaletteMail] One of recipients address is not valid.');
    }

    /**
     * Test sender information getters
     */
    public function testGetSender(): void
    {
        $mail = new \Galette\Core\GaletteMail($this->preferences);
        $this->assertSame($this->preferences->getDefaults()['pref_email_nom'], $mail->getSenderName());
        $this->assertSame($this->preferences->getDefaults()['pref_email'], $mail->getSenderAddress());
    }

    /**
     * Test subject setter and getter
     */
    public function testSubject(): void
    {
        $mail = new \Galette\Core\GaletteMail($this->preferences);
        $this->assertSame('', $mail->getSubject());
        $mail->setSubject('My subject');
        $this->assertSame('My subject', $mail->getSubject());
    }

    /**
     * Test mail body
     */
    public function testMessage(): void
    {
        $mail = new \Galette\Core\GaletteMail($this->preferences);
        $this->assertSame('', $mail->getMessage());
        $message = 'Galette is a membership management web application towards non profit organizations.';
        $mail->setMessage($message);
        $this->assertSame($message, $mail->getMessage());
        $this->assertSame(
            "Galette is a membership management web application towards non profit\r\norganizations.\r\n",
            $mail->getWrappedMessage()
        );

        $this->preferences->pref_bool_wrap_mails = false; //disable word wrapping
        $mail = new \Galette\Core\GaletteMail($this->preferences);
        $message = 'Galette is a membership management web application towards non profit organizations.';
        $mail->setMessage($message);
        $this->assertSame($message, $mail->getWrappedMessage());
    }

    /**
     * Test isHtml
     */
    public function testIsHtml(): void
    {
        $mail = new \Galette\Core\GaletteMail($this->preferences);
        $this->assertFalse($mail->isHtml());
        $this->assertTrue($mail->isHTML(true));
        $this->assertTrue($mail->isHTML());
        $this->assertFalse($mail->isHtml(false));
        $this->assertFalse($mail->isHTML());
    }

    /**
     * Test various getters and setters
     */
    public function testGettersSetters(): void
    {
        $mail = new \Galette\Core\GaletteMail($this->preferences);
        $this->assertInstanceOf($mail::class, $mail->setTimeout(20)); //nothing testable
        $this->assertSame([], $mail->getErrors());
    }
}
