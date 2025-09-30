<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

namespace Galette\Core\test\units;

use Galette\GaletteTestCase;
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
     * @return array<string, bool>
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
     *
     * @return void
     */
    #[DataProvider('urlProvider')]
    public function testIsURL(string $url, bool $expected): void
    {
        $this->assertSame($expected, \Galette\Core\GaletteMail::isURL($url));
    }

    /**
     * Data provider for testIsValidEmail
     *
     * @return array<string, bool>
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
     *
     * @return void
     */
    #[DataProvider('emailProvider')]
    public function testIsValidEmail(string $email, bool $expected): void
    {
        $this->assertSame($expected, \Galette\Core\GaletteMail::isValidEmail($email));
    }

    /**
     * Test setRecipients
     *
     * @return void
     */
    public function testSetRecipients(): void
    {
        $mail = new \Galette\Core\GaletteMail($this->preferences);
        $mail->setRecipients([]);
        $this->expectNoLogEntry();

        $mail->setRecipients(['contact@galette.eu' => 'Contact']); //just a valid email
        $this->expectNoLogEntry();

        $mail->setRecipients(['contact.galette.eu' => 'Contact']); //just an invalid email
        $this->expectLogEntry(\Analog::INFO, '[Galette\Core\GaletteMail] One of recipients address is not valid.');
    }

    /**
     * Test sender information getters
     *
     * @return void
     */
    public function testGetSender(): void
    {
        $mail = new \Galette\Core\GaletteMail($this->preferences);
        $this->assertSame($this->preferences->getDefaults()['pref_email_nom'], $mail->getSenderName());
        $this->assertSame($this->preferences->getDefaults()['pref_email'], $mail->getSenderAddress());
    }

    /**
     * Test subject setter and getter
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
     */
    public function testGettersSetters(): void
    {
        $mail = new \Galette\Core\GaletteMail($this->preferences);
        $this->assertInstanceOf($mail::class, $mail->setTimeout(20)); //nothing testable
        $this->assertSame([], $mail->getErrors());
    }
}
