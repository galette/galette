<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\CompileLocales as CompileLocalesCommand;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Tester\CommandTester;

use function Safe\file_put_contents;
use function Safe\filemtime;
use function Safe\mkdir;
use function Safe\rmdir;
use function Safe\touch;
use function Safe\unlink;

/**
 * CompileLocales command tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CompileLocales extends TestCase
{
    private string $root;

    /**
     * Create a fake Galette root, with a core and a plugin lang directory
     */
    public function setUp(): void
    {
        parent::setUp();
        if (CompileLocalesCommand::findMsgfmt() === null) {
            $this->markTestSkipped('msgfmt is not available');
        }

        $this->root = sys_get_temp_dir() . '/galette-compile-locales-' . uniqid();
        mkdir($this->root . '/lang', 0o755, recursive: true);
        mkdir($this->root . '/plugins/plugin-test/lang', 0o755, recursive: true);

        file_put_contents($this->root . '/lang/galette.pot', '');
        file_put_contents($this->root . '/lang/galette_fr_FR.utf8.po', $this->getPo('Le fichier %1$s ne peut être ouvert !'));
        file_put_contents($this->root . '/lang/galette_en_US.po', $this->getPo('File %1$s cannot be open!'));
        file_put_contents($this->root . '/plugins/plugin-test/lang/test.pot', '');
        file_put_contents($this->root . '/plugins/plugin-test/lang/test_fr_FR.utf8.po', $this->getPo('Le fichier %1$s ne peut être ouvert !'));
    }

    /**
     * Remove fake Galette root
     */
    public function tearDown(): void
    {
        if (isset($this->root) && is_dir($this->root)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    /**
     * Get a PO file content
     *
     * @param string $msgstr Translation of the only message
     */
    private function getPo(string $msgstr): string
    {
        return <<<PO
            msgid ""
            msgstr ""
            "Content-Type: text/plain; charset=UTF-8\\n"

            #, php-format
            msgid "File %1\$s cannot be open!"
            msgstr "{$msgstr}"

            PO;
    }

    /**
     * Test core and plugins MO files are compiled where Galette loads them
     */
    public function testCompile(): void
    {
        $tester = new CommandTester(new CompileLocalesCommand($this->root));
        $tester->execute([]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('3 MO file(s) compiled, 0 error(s).', $tester->getDisplay());

        $core_mo = $this->root . '/lang/fr_FR.utf8/LC_MESSAGES/galette.mo';
        $this->assertFileExists($core_mo);
        $this->assertFileExists($this->root . '/lang/en_US/LC_MESSAGES/galette.mo');
        $this->assertFileExists($this->root . '/plugins/plugin-test/lang/fr_FR.utf8/LC_MESSAGES/test.mo');
        $this->assertFileDoesNotExist($core_mo . '.new');

        $translator = new \Laminas\I18n\Translator\Translator();
        $translator->addTranslationFile(
            type: 'gettext',
            filename: $core_mo,
            textDomain: 'galette',
            locale: 'fr_FR.utf8'
        );
        $this->assertSame(
            'Le fichier %1$s ne peut être ouvert !',
            $translator->translate('File %1$s cannot be open!', 'galette', 'fr_FR.utf8')
        );

        //up to date files are not compiled again
        $tester->execute([]);
        $this->assertStringContainsString('0 MO file(s) compiled, 0 error(s).', $tester->getDisplay());

        //unless forced
        $tester->execute(['--force' => true]);
        $this->assertStringContainsString('3 MO file(s) compiled, 0 error(s).', $tester->getDisplay());

        //an updated PO is compiled again
        touch($this->root . '/lang/galette_fr_FR.utf8.po', filemtime($core_mo) + 10);
        $tester->execute([]);
        $this->assertStringContainsString('1 MO file(s) compiled, 0 error(s).', $tester->getDisplay());
    }

    /**
     * Test only one plugin can be compiled
     */
    public function testCompilePlugin(): void
    {
        $tester = new CommandTester(new CompileLocalesCommand($this->root));
        $tester->execute(['plugin' => 'plugin-test']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('1 MO file(s) compiled, 0 error(s).', $tester->getDisplay());
        $this->assertFileExists($this->root . '/plugins/plugin-test/lang/fr_FR.utf8/LC_MESSAGES/test.mo');
        $this->assertFileDoesNotExist($this->root . '/lang/fr_FR.utf8/LC_MESSAGES/galette.mo');

        $this->expectException(InvalidArgumentException::class);
        $tester->execute(['plugin' => 'plugin-unknown']);
    }

    /**
     * Test a translation that lost a parameter fails, and other files are still compiled
     */
    public function testBrokenTranslation(): void
    {
        file_put_contents($this->root . '/lang/galette_fr_FR.utf8.po', $this->getPo('Le fichier ne peut être ouvert !'));

        $tester = new CommandTester(new CompileLocalesCommand($this->root));
        $tester->execute([]);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('2 MO file(s) compiled, 1 error(s).', $tester->getDisplay());
        $this->assertFileDoesNotExist($this->root . '/lang/fr_FR.utf8/LC_MESSAGES/galette.mo');
        $this->assertFileDoesNotExist($this->root . '/lang/fr_FR.utf8/LC_MESSAGES/galette.mo.new');
    }
}
