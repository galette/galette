<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Console;

use PHPUnit\Framework\TestCase;

use function Safe\copy;
use function Safe\fclose;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\proc_close;
use function Safe\realpath;
use function Safe\rmdir;
use function Safe\stream_get_contents;
use function Safe\unlink;

/**
 * bin/console application directory lookup tests
 *
 * The script is copied in a temporary installation directory, next to stub
 * application directories: their galette.inc.php prints the resolved paths and
 * stops, so neither the configuration nor the database are involved.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Console extends TestCase
{
    //an uncommon name, with a space and a non ASCII character
    private const string RENAMED = 'Gestion des adhérents';

    private string $root;

    /**
     * Create the temporary installation directory, with bin/console only
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/galette-console-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/bin', recursive: true);
        copy(GALETTE_ROOT . '../bin/console', $this->root . '/bin/console');
    }

    /**
     * Remove the temporary installation directory
     */
    public function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getPathname());
                continue;
            }
            unlink($fileinfo->getPathname());
        }
        rmdir($this->root);
        parent::tearDown();
    }

    /**
     * Test the "galette" directory is used when present
     */
    public function testDefaultDirectory(): void
    {
        $this->makeApplication('galette');
        $this->makeApplication(self::RENAMED);

        $this->assertResolvedTo('galette', $this->runConsole());
    }

    /**
     * Test a renamed application directory is found
     */
    public function testRenamedDirectory(): void
    {
        $this->makeApplication(self::RENAMED);
        //not a Galette directory, must be ignored
        mkdir($this->root . '/other');

        $this->assertResolvedTo(self::RENAMED, $this->runConsole());
    }

    /**
     * Test the console refuses to guess among several application directories
     */
    public function testSeveralDirectories(): void
    {
        $this->makeApplication(self::RENAMED);
        $this->makeApplication('galette.old');

        [$code, $output] = $this->runConsole();
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Several Galette directories found next to', $output);
        $this->assertStringContainsString(self::RENAMED, $output);
        $this->assertStringContainsString('galette.old', $output);
        $this->assertStringContainsString('Set the GALETTE_DIR environment variable', $output);
    }

    /**
     * Test the console fails when no application directory is found
     */
    public function testNoDirectory(): void
    {
        [$code, $output] = $this->runConsole();
        $this->assertSame(1, $code);
        $this->assertStringContainsString('No Galette directory found next to', $output);
        $this->assertStringContainsString('Set the GALETTE_DIR environment variable', $output);
    }

    /**
     * Test GALETTE_DIR wins over the "galette" directory, and lifts ambiguity
     */
    public function testEnvironmentVariable(): void
    {
        $this->makeApplication('galette');
        $this->makeApplication(self::RENAMED);
        $this->makeApplication('galette.old');

        $this->assertResolvedTo(
            self::RENAMED,
            $this->runConsole(['GALETTE_DIR' => $this->root . '/' . self::RENAMED])
        );
    }

    /**
     * Test a relative GALETTE_DIR is made absolute, from the current directory
     */
    public function testRelativeEnvironmentVariable(): void
    {
        $this->makeApplication(self::RENAMED);
        $this->makeApplication('galette.old');

        $this->assertResolvedTo(
            'galette.old',
            $this->runConsole(['GALETTE_DIR' => 'galette.old'], $this->root)
        );
    }

    /**
     * Test the console fails when GALETTE_DIR is not a Galette directory
     */
    public function testInvalidEnvironmentVariable(): void
    {
        $this->makeApplication('galette');
        mkdir($this->root . '/other');

        [$code, $output] = $this->runConsole(['GALETTE_DIR' => $this->root . '/other']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString(
            sprintf('GALETTE_DIR is set to "%s/other", which is not a Galette directory.', $this->root),
            $output
        );
    }

    /**
     * Create a stub application directory
     *
     * @param string $name Directory name
     */
    private function makeApplication(string $name): void
    {
        $directory = $this->root . '/' . $name;
        mkdir($directory . '/vendor', recursive: true);
        mkdir($directory . '/includes/sys_config', recursive: true);

        //the console parses its arguments with Symfony before the bootstrap
        file_put_contents(
            $directory . '/vendor/autoload.php',
            sprintf("<?php\nrequire_once %s;\n", var_export(GALETTE_ROOT . 'vendor/autoload.php', return: true))
        );
        file_put_contents($directory . '/includes/sys_config/versions.inc.php', "<?php\n");
        //$basepath is in scope: the console requires this file from its top level
        file_put_contents(
            $directory . '/includes/galette.inc.php',
            "<?php\necho GALETTE_ROOT, PHP_EOL, \$basepath, PHP_EOL;\nexit(0);\n"
        );
    }

    /**
     * Run the console copy
     *
     * @param array<string, string> $env Additional environment variables
     * @param ?string               $cwd Working directory, defaults to the system root
     *
     * @return array{0: int, 1: string} Exit code and output
     */
    private function runConsole(array $env = [], ?string $cwd = null): array
    {
        //the parent environment may carry GALETTE_TESTS or GALETTE_DIR
        $env += ['PATH' => (string)getenv('PATH')];
        //an argument list, not a shell command line: no quoting of the directory names.
        //Safe\proc_open only accepts a command line.
        $process = \proc_open( //@phpstan-ignore theCodingMachineSafe.function
            command: [PHP_BINARY, $this->root . '/bin/console', '--version'],
            descriptor_spec: [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            pipes: $pipes,
            cwd: $cwd ?? '/',
            env_vars: $env
        );
        $this->assertNotFalse($process);
        //a few lines at most: reading both pipes in turn cannot block
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }

    /**
     * Assert the console resolved the expected application directory
     *
     * @param string                   $name   Expected directory name
     * @param array{0: int, 1: string} $result Console exit code and output
     */
    private function assertResolvedTo(string $name, array $result): void
    {
        [$code, $output] = $result;
        $expected = realpath($this->root . '/' . $name) . '/';

        $this->assertSame(0, $code, $output);
        //GALETTE_ROOT, then $basepath
        $paths = explode(PHP_EOL, trim($output));
        $this->assertCount(2, $paths, $output);
        foreach ($paths as $path) {
            //an absolute path, so the current directory does not matter afterwards
            $this->assertStringStartsWith('/', $path);
            $this->assertSame($expected, realpath($path) . '/');
        }
    }
}
