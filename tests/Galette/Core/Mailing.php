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

namespace GaletteTests\Core;

use Galette\GaletteTestCase;

/**
 * Mailing tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Mailing extends GaletteTestCase
{
    /**
     * Test setRecipients
     */
    public function testSetRecipients(): void
    {
        $mailing = new \Galette\Core\Mailing($this->preferences);
        $mailing->setRecipients([]);
        $this->expectNoLogEntry();

        $member = new \Galette\Entity\Adherent($this->zdb);
        $member->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
        $this->assertTrue($member->check(['email_adh' => 'contact@galette.eu'], [], []));
        $mailing->setRecipients([$member]); //just a valid email
        $this->assertSame([], $mailing->unreachables);

        $member = $this->getMockBuilder(\Galette\Entity\Adherent::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEmail'])
            ->getMock();
        $member->method('getEmail')->willReturn('contact.galette.eu');
        $mailing->setRecipients([$member]); //just a valid email
        $this->assertSame([$member], $mailing->unreachables);
    }

    /**
     * Test cleanHTML
     */
    public function testCleanHTML(): void
    {
        $mailing = new \Galette\Core\Mailing($this->preferences);
        $html = '<html><body><h1>Hello</h1><script>alert("XSS")</script><p>Welcome to <a href="https://galette.eu" onclick="stealCookies()">Galette</a>!</p></body></html>';
        $mailing->setMessage($html);
        $expected = "# Hello\n\nWelcome to [Galette](https://galette.eu)!";
        $this->assertSame($expected, $mailing->alt_message);
    }

    /**
     * Test getPhpMailer
     */
    public function testGetPhpMailer(): void
    {
        $mailing = new \Galette\Core\Mailing($this->preferences);
        $this->assertInstanceOf(\PHPMailer\PHPMailer\PHPMailer::class, $mailing->mail);
    }

    /**
     * Test __isset
     */
    public function testIsset(): void
    {
        $mailing = new \Galette\Core\Mailing($this->preferences);
        $this->assertFalse(isset($mailing->nonexistentProperty));
        $this->assertFalse(isset($mailing->ordered)); //explicitly forbidden
        $this->assertTrue(isset($mailing->alt_message)); //explicitly allowed
        $this->assertTrue(isset($mailing->mail)); //explicitly allowed
        $this->assertTrue(isset($mailing->current_step));
        $this->assertFalse(isset($mailing->history_id)); //private
    }

    /**
     * Test getters and setters
     */
    public function testGetterSetters(): void
    {
        $mailing = new \Galette\Core\Mailing($this->preferences, [], 42);
        $this->assertFalse($mailing->tmp_path);

        $mailing = new \Galette\Core\Mailing($this->preferences);
        $this->assertSame('', $mailing->subject);
        $mailing->subject = 'My subject';
        $this->assertSame('My subject', $mailing->subject);

        $this->assertSame('', $mailing->message);
        $message = 'Galette is a membership management web application towards non profit organizations.';
        $mailing->message = $message;
        $this->assertSame($message, $mailing->message);
        $this->assertSame(
            "Galette is a membership management web application towards non profit\r\norganizations.\r\n",
            $mailing->wrapped_message
        );

        $this->assertFalse($mailing->html);
        $mailing->html = 'true';
        $this->expectLogEntry(\Analog::WARNING, '[Galette\Core\Mailing] Value for field `html` should be boolean - (string)true given');
        $mailing->html = true;
        $this->assertTrue($mailing->html);

        $this->assertSame(\Galette\Core\Mailing::STEP_START, $mailing->current_step);
        $mailing->current_step = 'invalid_step';
        $this->expectLogEntry(\Analog::WARNING, '[Galette\Core\Mailing] Value for field `current_step` should be integer and know - (string)invalid_step given');
        $mailing->current_step = 42;
        $this->expectLogEntry(\Analog::WARNING, '[Galette\Core\Mailing] Value for field `current_step` should be integer and know - (integer)42 given');
        $mailing->current_step = \Galette\Core\Mailing::STEP_PREVIEW;
        $this->assertSame(\Galette\Core\Mailing::STEP_PREVIEW, $mailing->current_step);
        $this->assertSame(\Galette\Core\Mailing::STEP_PREVIEW, $mailing->step);

        $this->assertNotEmpty($mailing->id);
        $mailing->id = 42;
        $this->assertSame(42, $mailing->id);

        $this->assertFalse($mailing->ordered); //explicitly forbidden
        $this->expectLogEntry(\Analog::ERROR, '[Galette\Core\Mailing] Unable to get ordered');

        $this->assertSame([], $mailing->errors);
        $this->assertSame([], $mailing->recipients);
        $this->assertSame([], $mailing->attachments);

        $this->assertSame($this->preferences->getDefaults()['pref_email_nom'], $mailing->sender_name);
        $this->assertSame($this->preferences->getDefaults()['pref_email'], $mailing->sender_address);

        $this->assertNotEmpty($mailing->tmp_path);
        $this->assertFalse($mailing->existsInHistory());
    }
}
