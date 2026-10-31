<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\IO;

use Galette\IO\ExternalScript;
use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function Safe\chmod;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\json_decode;
use function Safe\unlink;

/**
 * External script tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ExternalScriptTest extends GaletteTestCase
{
    /** @var array<int, string> Files created by the test */
    private array $files = [];

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    /**
     * Get script instance for an URI
     *
     * @param string $uri Script URI
     */
    private function getScript(string $uri): ExternalScript
    {
        $this->preferences->pref_new_contrib_script = $uri;
        return new ExternalScript($this->preferences);
    }

    /**
     * Read a private property
     *
     * @param ExternalScript $script Script instance
     * @param string         $name   Property name
     */
    private function getProperty(ExternalScript $script, string $name): string
    {
        return (new \ReflectionProperty(ExternalScript::class, $name))->getValue($script);
    }

    /**
     * Write an executable shell script
     *
     * @param string $body Script body
     */
    private function writeScript(string $body): string
    {
        $path = GALETTE_CACHE_DIR . 'external_script_' . count($this->files) . '.sh';
        file_put_contents($path, "#!/bin/sh\n" . $body . "\n");
        chmod($path, 0o700);
        $this->files[] = $path;
        return $path;
    }

    /**
     * Remote protocols provider
     *
     * @return array<string, array{string, string, string, string}>
     */
    public static function remoteProvider(): array
    {
        return [
            'get' => ['get://example.com/script.php', 'get', 'http', 'http://example.com/script.php'],
            'gets' => ['gets://example.com/script.php', 'get', 'https', 'https://example.com/script.php'],
            'post' => ['post://example.com/script.php', 'post', 'http', 'http://example.com/script.php'],
            'posts' => ['posts://example.com/script.php', 'post', 'https', 'https://example.com/script.php'],
        ];
    }

    /**
     * Remote scripts URI are made HTTP ones
     *
     * @param string $uri      Configured URI
     * @param string $method   Expected method
     * @param string $protocol Expected protocol
     * @param string $expected Expected URI
     */
    #[DataProvider('remoteProvider')]
    public function testRemoteProtocols(string $uri, string $method, string $protocol, string $expected): void
    {
        $script = $this->getScript($uri);
        $this->assertSame($method, $this->getProperty($script, 'method'));
        $this->assertSame($protocol, $this->getProperty($script, 'protocol'));
        $this->assertSame($expected, $this->getProperty($script, 'uri'));
    }

    /**
     * Galette scripts are relative to the instance URL
     */
    public function testGaletteProtocol(): void
    {
        $script = $this->getScript('galette://plugins/script');
        $this->assertSame('galette', $this->getProperty($script, 'method'));
        $this->assertSame(
            $this->preferences->getURL() . '/plugins/script',
            $this->getProperty($script, 'uri')
        );
    }

    /**
     * Unknown or missing protocols are refused
     */
    public function testUnknownProtocol(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown protocol.');
        $this->getScript('ftp://example.com/script');
    }

    /**
     * An URI without protocol is refused
     */
    public function testMissingProtocol(): void
    {
        try {
            $this->getScript('/path/to/script');
            $this->fail('An URI without protocol must be refused');
        } catch (\RuntimeException $e) {
            $this->assertSame('Unknown protocol.', $e->getMessage());
        }
        $this->expectLogEntry(\Analog\Analog::ERROR, 'An URI must be specified');
    }

    /**
     * Local script must exist
     */
    public function testMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exists!');
        $this->getScript('file://' . GALETTE_CACHE_DIR . 'does_not_exist.sh');
    }

    /**
     * Parameters are mandatory
     */
    public function testSendWithoutParameters(): void
    {
        $script = $this->getScript('file://' . $this->writeScript('exit 0'));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('parameters are mandatory.');
        $script->send([]);
    }

    /**
     * A local script gets parameters as JSON, and succeeds when it says nothing
     */
    public function testFileSuccess(): void
    {
        $received = GALETTE_CACHE_DIR . 'external_script_received.json';
        $this->files[] = $received;
        $path = $this->writeScript('cat > ' . $received);
        $script = $this->getScript('file://' . $path);
        $this->assertSame($path, $this->getProperty($script, 'uri'));

        $params = ['id' => 42, 'amount' => 92.5, 'member' => ['name' => 'Some One']];
        $this->assertTrue($script->send($params));
        $this->assertSame('', $script->getOutput());
        $this->assertSame($params, json_decode(file_get_contents($received), associative: true));
    }

    /**
     * Anything a local script says is a failure
     */
    public function testFileFailure(): void
    {
        $script = $this->getScript('file://' . $this->writeScript("echo 'Some output'\necho 'Some error' >&2"));

        $this->assertFalse($script->send(['id' => 42]));
        $output = $script->getOutput();
        $this->assertStringContainsString("StdOut:\nSome output", $output);
        $this->assertStringContainsString("StdErr:\nSome error", $output);
    }

    /**
     * Unreachable remote scripts
     *
     * @return array<string, array{string}>
     */
    public static function unreachableProvider(): array
    {
        return [
            'get' => ['get://127.0.0.1:1/script.php'],
            'post' => ['post://127.0.0.1:1/script.php'],
        ];
    }

    /**
     * A remote script that cannot be reached is a failure, not a crash
     *
     * @param string $uri Script URI
     */
    #[DataProvider('unreachableProvider')]
    public function testUnreachableRemote(string $uri): void
    {
        $script = $this->getScript($uri);
        $this->assertFalse($script->send(['id' => 42]));
        $this->assertNotSame('', $script->getOutput());
    }
}
