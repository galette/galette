<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\MakeTwigCache;
use Galette\Console\Command\TwigPotReferences as TwigPotReferencesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\glob;
use function Safe\mkdir;
use function Safe\realpath;
use function Safe\rmdir;
use function Safe\unlink;

/**
 * TwigPotReferences command tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TwigPotReferences extends TestCase
{
    private string $lang_dir;

    /**
     * Compile templates, and create a directory for the POT file
     * at the same level as the core lang directory
     */
    public function setUp(): void
    {
        parent::setUp();
        $tester = new CommandTester(new MakeTwigCache(GALETTE_BASE_PATH));
        $tester->execute([]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $this->lang_dir = realpath(GALETTE_BASE_PATH) . '/test-lang-' . uniqid();
        mkdir($this->lang_dir);
    }

    /**
     * Remove POT file directory
     */
    public function tearDown(): void
    {
        foreach (glob($this->lang_dir . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->lang_dir);
        parent::tearDown();
    }

    /**
     * Test references to compiled templates are rewritten, others are kept
     */
    public function testFixReferences(): void
    {
        $pot = $this->lang_dir . '/galette.pot';
        file_put_contents(
            $pot,
            <<<'POT'
                #: ../lib/Galette/IO/CsvIn.php:164 ../../tempcache/pages/saved_searches_list.html.twig:92
                msgid "ID"
                msgstr ""

                #: ../../tempcache/pages/saved_searches_list.html.twig:101
                #: ../../tempcache/pages/saved_searches_list.html.twig:102
                msgid "Creation date"
                msgstr ""

                POT
        );

        $tester = new CommandTester(new TwigPotReferencesCommand(GALETTE_BASE_PATH));
        $tester->execute(['pot' => $pot]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $this->assertSame(
            <<<'POT'
                #: ../lib/Galette/IO/CsvIn.php:164 ../templates/default/pages/saved_searches_list.html.twig:37
                msgid "ID"
                msgstr ""

                #: ../templates/default/pages/saved_searches_list.html.twig:40
                msgid "Creation date"
                msgstr ""

                POT,
            file_get_contents($pot)
        );
    }
}
