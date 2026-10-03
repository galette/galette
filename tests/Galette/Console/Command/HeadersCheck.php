<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console\Command;

use Galette\Console\Command\HeadersCheck as HeadersCheckCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\rmdir;
use function Safe\unlink;

/**
 * HeadersCheck command tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class HeadersCheck extends TestCase
{
    /** @var string[] */
    private array $temp_dirs = [];

    /**
     * Clean temporary directories after each test.
     */
    public function tearDown(): void
    {
        foreach ($this->temp_dirs as $directory) {
            $this->cleanupTempDir($directory);
        }

        $this->temp_dirs = [];
        parent::tearDown();
    }

    /**
     * Test command reports a missing header without fixing it.
     */
    public function testDetectsMissingHeaderWithoutFix(): void
    {
        $directory = $this->makeTempDir();
        $filename = $directory . '/src/MissingHeader.php';
        $this->writeFile(
            $filename,
            <<<'PHP'
                <?php

                declare(strict_types=1);

                final class MissingHeader
                {
                }
                PHP
        );

        $tester = $this->runCommand($directory);
        $display = $tester->getDisplay();

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString(
            sprintf('Missing licence header in file "%s".', $filename),
            $display
        );
        $this->assertStringContainsString(
            'Found 1 file without header. Use --fix option to fix these files.',
            $display
        );
        $this->assertStringNotContainsString('Fixed 1 file without header.', $display);
    }

    /**
     * Test command fixes a missing PHP header and becomes idempotent.
     */
    public function testFixesMissingPhpHeaderAndThenReportsValidHeaders(): void
    {
        $directory = $this->makeTempDir();
        $filename = $directory . '/src/FixableHeader.php';
        $this->writeFile(
            $filename,
            <<<'PHP'
                <?php

                declare(strict_types=1);

                final class FixableHeader
                {
                }
                PHP
        );

        $fixTester = $this->runCommand($directory, ['--fix' => true]);
        $fixedDisplay = $fixTester->getDisplay();
        $fixedContents = $this->readFile($filename);

        $this->assertSame(Command::SUCCESS, $fixTester->getStatusCode());
        $this->assertStringContainsString(
            sprintf('Missing licence header in file "%s".', $filename),
            $fixedDisplay
        );
        $this->assertStringContainsString('Fixed 1 file without header.', $fixedDisplay);
        $this->assertStringContainsString("<?php\n\n/**\n", $fixedContents);
        $this->assertStringContainsString(' * This file is part of Test Project (https://example.org).', $fixedContents);
        $this->assertStringContainsString(' * SPDX-FileCopyrightText: Copyright © 2020-2026 Test Owner', $fixedContents);
        $this->assertStringContainsString(" */\n\ndeclare(strict_types=1);", $fixedContents);

        $validTester = $this->runCommand($directory);

        $this->assertSame(Command::SUCCESS, $validTester->getStatusCode());
        $this->assertStringContainsString('Files headers are valid.', $validTester->getDisplay());
        $this->assertSame($fixedContents, $this->readFile($filename));
    }

    /**
     * Test command detects and fixes an outdated header.
     */
    public function testDetectsAndFixesOutdatedHeader(): void
    {
        $directory = $this->makeTempDir();
        $filename = $directory . '/src/OutdatedHeader.php';
        $this->writeFile(
            $filename,
            <<<'PHP'
                <?php

                /**
                 * This file is part of Legacy Project (https://legacy.example.org).
                 * SPDX-FileCopyrightText: Copyright © 2010-2015 Someone Else
                 * SPDX-License-Identifier: GPL-3.0-or-later
                 */

                declare(strict_types=1);

                final class OutdatedHeader
                {
                }
                PHP
        );

        $checkTester = $this->runCommand($directory);
        $checkDisplay = $checkTester->getDisplay();

        $this->assertSame(Command::FAILURE, $checkTester->getStatusCode());
        $this->assertStringContainsString(
            sprintf('Licence header outdated in file "%s".', $filename),
            $checkDisplay
        );
        $this->assertStringContainsString(
            'Found 1 file with outdated header. Use --fix option to fix these files.',
            $checkDisplay
        );

        $fixTester = $this->runCommand($directory, ['--fix' => true]);
        $fixedContents = $this->readFile($filename);

        $this->assertSame(Command::SUCCESS, $fixTester->getStatusCode());
        $this->assertStringContainsString('Fixed 1 file with outdated header.', $fixTester->getDisplay());
        $this->assertStringContainsString(' * This file is part of Test Project (https://example.org).', $fixedContents);
        $this->assertStringContainsString(' * SPDX-FileCopyrightText: Copyright © 2020-2026 Test Owner', $fixedContents);
        $this->assertStringNotContainsString('Legacy Project', $fixedContents);
        $this->assertStringNotContainsString('Someone Else', $fixedContents);
        $this->assertStringContainsString("final class OutdatedHeader\n{\n}", $fixedContents);
    }

    /**
     * Test command fixes shebang scripts and ignores excluded paths.
     */
    public function testFixesShebangScriptAndIgnoresExcludedPaths(): void
    {
        $directory = $this->makeTempDir();
        $script = $directory . '/bin/headers-check';
        $hiddenFile = $directory . '/.hidden.php';
        $vendorFile = $directory . '/galette/vendor/Ignored.php';

        $this->writeFile(
            $script,
            <<<'SH'
                #!/usr/bin/env bash

                echo "hello"
                SH
        );
        $this->writeFile(
            $hiddenFile,
            <<<'PHP'
                <?php

                echo 'hidden';
                PHP
        );
        $this->writeFile(
            $vendorFile,
            <<<'PHP'
                <?php

                echo 'vendor';
                PHP
        );

        $tester = $this->runCommand($directory, ['--fix' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString(
            sprintf('Missing licence header in file "%s".', $script),
            $display
        );
        $this->assertStringContainsString('Fixed 1 file without header.', $display);
        $this->assertStringNotContainsString($hiddenFile, $display);
        $this->assertStringNotContainsString($vendorFile, $display);

        $scriptContents = $this->readFile($script);
        $this->assertStringContainsString("#!/usr/bin/env bash\n\n#\n# This file is part of Test Project (https://example.org).", $scriptContents);
        $this->assertStringContainsString("# SPDX-FileCopyrightText: Copyright © 2020-2026 Test Owner", $scriptContents);
        $this->assertStringContainsString("#\n\necho \"hello\"", $scriptContents);
        $this->assertSame("<?php\n\necho 'hidden';", $this->readFile($hiddenFile));
        $this->assertSame("<?php\n\necho 'vendor';", $this->readFile($vendorFile));
    }

    /**
     * Run the headers check command on a temporary directory.
     *
     * @param string                     $directory Directory to inspect
     * @param array<string, bool|string> $options   Additional command options
     */
    private function runCommand(string $directory, array $options = []): CommandTester
    {
        $command = new HeadersCheckCommand('');
        $tester = new CommandTester($command);
        $tester->execute(
            array_merge(
                [
                    '--directory' => $directory,
                    '--header-file' => $this->writeHeaderTemplate($directory),
                    '--owner' => 'Test Owner',
                    '--project-name' => 'Test Project',
                    '--project-url' => 'https://example.org',
                    '--start-year' => '2020',
                    '--end-year' => '2026',
                ],
                $options
            )
        );
        return $tester;
    }

    /**
     * Write the header template used by command tests.
     *
     * @param string $directory Base temporary directory
     */
    private function writeHeaderTemplate(string $directory): string
    {
        $headerFile = $directory . '/.licence-header';
        $this->writeFile(
            $headerFile,
            <<<'HEADER'
                This file is part of %PROJECT% (%URL%).
                SPDX-FileCopyrightText: Copyright © %START_YEAR%-%END_YEAR% %OWNER%
                SPDX-License-Identifier: GPL-3.0-or-later

                HEADER
        );
        return $headerFile;
    }

    /**
     * Create and register a temporary directory for a test.
     */
    private function makeTempDir(): string
    {
        $directory = sys_get_temp_dir() . '/galette-headers-check-' . bin2hex(random_bytes(8));
        mkdir($directory, recursive: true);
        $this->temp_dirs[] = $directory;
        return $directory;
    }

    /**
     * Write a file contents, creating parent directories if needed.
     *
     * @param string $filename File path to write
     * @param string $contents File contents
     */
    private function writeFile(string $filename, string $contents): void
    {
        $parent = dirname($filename);
        if (!is_dir($parent)) {
            mkdir($parent, recursive: true);
        }

        file_put_contents($filename, $contents);
    }

    /**
     * Read file contents and assert loading succeeded.
     *
     * @param string $filename File path to read
     */
    private function readFile(string $filename): string
    {
        $contents = file_get_contents($filename);
        $this->assertNotEmpty($contents);
        return $contents;
    }

    /**
     * Remove a temporary directory recursively.
     *
     * @param string $directory Directory to remove
     */
    private function cleanupTempDir(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getPathname());
                continue;
            }

            unlink($fileinfo->getPathname());
        }

        rmdir($directory);
    }
}
