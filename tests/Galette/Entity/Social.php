<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Status tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Social extends GaletteTestCase
{
    protected int $seed = 25568744158;

    /**
     * Test social object
     */
    public function testObject(): void
    {
        $social = new \Galette\Entity\Social($this->zdb);

        //setters and getters
        $this->assertInstanceOf(\Galette\Entity\Social::class, $social->setType('mytype'));
        $this->assertSame('mytype', $social->type);

        $this->assertInstanceOf(\Galette\Entity\Social::class, $social->setUrl('myurl'));
        $this->assertSame('myurl', $social->url);

        //null as member id for Galette main preferences
        $this->assertInstanceOf(\Galette\Entity\Social::class, $social->setLinkedMember(id: null));
        $this->assertNull($social->id_adh);
        $this->assertNull($social->member);

        $this->getMemberTwo();
        $this->assertInstanceOf(\Galette\Entity\Social::class, $social->setLinkedMember($this->adh->id));
        $this->assertSame($this->adh->id, $social->id_adh);
        $this->assertInstanceOf(\Galette\Entity\Adherent::class, $social->member);
        $this->assertSame($this->adh->name, $social->member->name);
    }

    /**
     * Test socials "system" types
     */
    public function testGetSystemTypes(): void
    {
        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertCount(10, $social->getSystemTypes());
        $this->assertSame($social->getSystemTypes(translated: true), $social->getSystemTypes());
        $this->assertCount(10, $social->getSystemTypes(translated: false));

        $this->assertSame('Twitter', $social->getSystemType(\Galette\Entity\Social::TWITTER));
        $this->assertSame('twitter', $social->getSystemType(\Galette\Entity\Social::TWITTER, translated: false));
    }

    /**
     * Test getListForMember
     */
    public function testGetListForMember(): void
    {
        $this->assertEmpty(\Galette\Entity\Social::getListForMember(id_adh: null));

        $this->getMemberTwo();
        $this->assertEmpty(\Galette\Entity\Social::getListForMember($this->adh->id));

        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('mastodon URL')
                ->setLinkedMember($this->adh->id)
                ->store()
        );

        $socials = \Galette\Entity\Social::getListForMember($this->adh->id);
        $this->assertCount(1, $socials);
        $social = array_pop($socials);
        $this->assertSame(\Galette\Entity\Social::MASTODON, $social->type);
        $this->assertSame($this->adh->id, $social->id_adh);
        $this->assertSame('mastodon URL', $social->url);

        //Same, from Adherent object
        $adh = new \Galette\Entity\Adherent($this->zdb, $this->adh->id, ['socials' => true]);
        $socials = $adh->socials;
        $this->assertCount(1, $socials);
        $social = array_pop($socials);
        $this->assertSame(\Galette\Entity\Social::MASTODON, $social->type);
        $this->assertSame($this->adh->id, $social->id_adh);
        $this->assertSame('mastodon URL', $social->url);

        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('Galette mastodon URL')
                ->setLinkedMember(id: null)
                ->store()
        );

        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType(\Galette\Entity\Social::JABBER)
                ->setUrl('Galette jabber')
                ->setLinkedMember(id: null)
                ->store()
        );

        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('Another Galette mastodon URL')
                ->setLinkedMember(id: null)
                ->store()
        );

        $this->assertCount(3, \Galette\Entity\Social::getListForMember(id_adh: null));
        $this->assertCount(1, \Galette\Entity\Social::getListForMember(id_adh: null, type: \Galette\Entity\Social::JABBER));

        $this->assertTrue($social->remove());
        $this->assertCount(2, \Galette\Entity\Social::getListForMember(id_adh: null));
    }

    /**
     * URLs provider
     *
     * @return array<string, array{string, bool}>
     */
    public static function webUrlProvider(): array
    {
        return [
            'simple' => ['https://galette.eu', true],
            'subdomain and path' => ['http://www.galette.eu/', true],
            'mastodon' => ['https://mastodon.social/@galette', true],
            'port, query and fragment' => ['https://a.b.example.org:8443/x?y=1&z=2#f', true],
            'IDN TLD' => ['https://example.xn--p1ai', true],
            'apostrophe' => ["https://example.org/O'Reilly", true],
            'no TLD' => ['https://galette', false],
            'other scheme' => ['javascript:alert(1)', false],
            'space' => ['https://galette.eu/a b', false],
            'trailing newline' => ["https://galette.eu\n", false],
            'quote' => ['https://galette.eu" onmouseover="alert(1)', false],
            'tag' => ['https://galette.eu/<script>', false],
        ];
    }

    /**
     * Test web URL validation
     *
     * @param string $url      URL to check
     * @param bool   $expected Expected result
     */
    #[DataProvider('webUrlProvider')]
    public function testIsValidWebUrl(string $url, bool $expected): void
    {
        $this->assertSame($expected, isValidWebUrl($url));
    }

    /**
     * URL display provider
     *
     * @return array<string, array{string, string}>
     */
    public static function displayUrlProvider(): array
    {
        return [
            'web' => [
                'https://galette.eu/?a=1&b=2',
                '<a href="https://galette.eu/?a=1&amp;b=2">https://galette.eu/?a=1&amp;b=2</a>'
            ],
            'email' => [
                'contact@galette.eu',
                '<a href="mailto:contact@galette.eu">contact@galette.eu</a>'
            ],
            'other' => [
                '@galette',
                '@galette'
            ],
            'attribute injection' => [
                'https://a.co" onmouseover="alert(1)" x="',
                'https://a.co&quot; onmouseover=&quot;alert(1)&quot; x=&quot;'
            ],
            'apostrophe' => [
                "https://example.org/O'Reilly",
                '<a href="https://example.org/O&#039;Reilly">https://example.org/O&#039;Reilly</a>'
            ],
            'markup' => [
                '<img src=x onerror=alert(1)>',
                '&lt;img src=x onerror=alert(1)&gt;'
            ],
        ];
    }

    /**
     * Test URL display
     *
     * @param string $url      Stored URL
     * @param string $expected Expected HTML
     */
    #[DataProvider('displayUrlProvider')]
    public function testDisplayUrl(string $url, string $expected): void
    {
        $social = new \Galette\Entity\Social($this->zdb);
        $social->setUrl($url);
        $this->assertSame($expected, $social->displayUrl());
    }
}
