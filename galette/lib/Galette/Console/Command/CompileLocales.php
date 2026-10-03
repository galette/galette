<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Safe\fclose;
use function Safe\filemtime;
use function Safe\glob;
use function Safe\mkdir;
use function Safe\preg_match;
use function Safe\proc_close;
use function Safe\proc_open;
use function Safe\realpath;
use function Safe\rename;
use function Safe\stream_get_contents;
use function Safe\unlink;

/**
 * Compile gettext sources (PO) into the MO files Galette loads
 *
 * MO files are not versioned; this builds them for the core and plugins,
 * at lang/<locale>/LC_MESSAGES/<domain>.mo, from lang/<domain>_<locale>.po.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:compile-locales',
    description: 'Compile translation files (PO) into MO files, for the core and plugins'
)]
class CompileLocales extends AbstractCommand
{
    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this
            ->addArgument(
                'plugin',
                InputArgument::OPTIONAL,
                'Only compile specified plugin directory (core and all plugins by default)'
            )
            ->addOption(
                name: 'force',
                shortcut: 'f',
                mode: InputOption::VALUE_NONE,
                description: 'Compile even if MO file is up to date'
            )
        ;
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $msgfmt = self::findMsgfmt();
        if ($msgfmt === null) {
            $this->io->error('msgfmt not found; please install gettext tools.');
            return Command::FAILURE;
        }

        $plugin = $input->getArgument('plugin');
        $force = (bool)$input->getOption('force');

        $galette_root = realpath($this->basepath);
        $lang_dirs = [];
        if ($plugin) {
            $lang_dir = $galette_root . '/plugins/' . $plugin . '/lang';
            if (!is_dir($lang_dir)) {
                throw new InvalidArgumentException(
                    sprintf('Unable to find lang directory for plugin "%s"', $plugin)
                );
            }
            $lang_dirs[] = $lang_dir;
        } else {
            $lang_dirs[] = $galette_root . '/lang';
            foreach (glob($galette_root . '/plugins/*/lang', GLOB_ONLYDIR) as $lang_dir) {
                $lang_dirs[] = $lang_dir;
            }
        }

        $compiled = 0;
        $errors = 0;
        foreach ($lang_dirs as $lang_dir) {
            foreach (glob($lang_dir . '/*.pot') as $pot) {
                $domain = basename($pot, '.pot');
                foreach (glob($lang_dir . '/' . $domain . '_*.po') as $po) {
                    if (!preg_match('/^' . preg_quote($domain, '/') . '_(.+)\.po$/', basename($po), $matches)) {
                        continue;
                    }
                    $mo_dir = $lang_dir . '/' . $matches[1] . '/LC_MESSAGES';
                    $mo = $mo_dir . '/' . $domain . '.mo';

                    if (!$force && file_exists($mo) && filemtime($mo) >= filemtime($po)) {
                        continue;
                    }

                    if (!is_dir($mo_dir)) {
                        mkdir($mo_dir, 0o755, recursive: true);
                    }

                    $error = $this->compile($msgfmt, $po, $mo . '.new');
                    if ($error !== null) {
                        ++$errors;
                        $this->io->error($error);
                        continue;
                    }
                    rename($mo . '.new', $mo);
                    ++$compiled;
                    if ($output->isVerbose()) {
                        $this->io->writeln(sprintf('Compiled %s', $mo));
                    }
                }
            }
        }

        $this->io->writeln(sprintf('%d MO file(s) compiled, %d error(s).', $compiled, $errors));
        return $errors === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Compile one PO file
     *
     * @param string $msgfmt Path to msgfmt
     * @param string $po     PO file to compile
     * @param string $mo     MO file to write
     *
     * @return ?string Error message, null on success
     */
    private function compile(string $msgfmt, string $po, string $mo): ?string
    {
        $process = proc_open(
            implode(' ', array_map(escapeshellarg(...), [$msgfmt, '--check', '-o', $mo, $po])),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0) {
            if (file_exists($mo)) {
                unlink($mo);
            }
            return trim($stderr) ?: sprintf('unable to compile %s', $po);
        }
        return null;
    }

    /**
     * Find msgfmt executable in PATH
     */
    public static function findMsgfmt(): ?string
    {
        $path = getenv('PATH');
        if ($path === false) {
            return null;
        }
        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            foreach (['msgfmt', 'msgfmt.exe'] as $name) {
                $candidate = $dir . DIRECTORY_SEPARATOR . $name;
                if (is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
            }
        }
        return null;
    }
}
