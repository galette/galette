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
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\preg_match;
use function Safe\preg_match_all;
use function Safe\preg_split;
use function Safe\realpath;

/**
 * Point references of a POT file to Twig templates instead of their compiled versions
 *
 * Strings are extracted from templates compiled by galette:twig-cache; this
 * rewrites the "#:" references so they target the template file and line,
 * using the debug information Twig stores in each compiled template.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:twig-pot-references',
    description: 'Point POT file references to Twig templates instead of compiled ones'
)]
class TwigPotReferences extends AbstractCommand
{
    use TwigCacheDirectories;

    /** @var array<string, array<int, int>> */
    private array $debug_infos = [];

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this
            ->addArgument('pot', InputArgument::REQUIRED, 'POT file to fix')
            ->addArgument('plugin', InputArgument::OPTIONAL, 'Plugin directory the POT file has been extracted from')
        ;
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugin = $input->getArgument('plugin');
        $templates_dir = $this->getTemplatesDirectory($plugin);
        $cache_dir = $this->getCacheDirectory($plugin);

        $pot = $input->getArgument('pot');
        if (!is_file($pot) || !is_writable($pot)) {
            throw new InvalidOptionException(
                sprintf('Unable to write POT file "%s"', $input->getArgument('pot'))
            );
        }
        $pot = realpath($pot);
        $pot_dir = dirname($pot);

        $lines = [];
        $entry_references = [];
        foreach (explode("\n", file_get_contents($pot)) as $line) {
            if (!str_starts_with($line, '#')) {
                $entry_references = [];
            }
            if (!str_starts_with($line, '#: ')) {
                $lines[] = $line;
                continue;
            }

            //same template line may be referenced by several compiled lines
            $references = [];
            foreach (preg_split('/\s+/', trim(substr($line, 3))) as $reference) {
                $reference = $this->fixReference(
                    reference: $reference,
                    pot_dir: $pot_dir,
                    templates_dir: $templates_dir,
                    cache_dir: $cache_dir
                );
                if (!isset($entry_references[$reference])) {
                    $entry_references[$reference] = true;
                    $references[] = $reference;
                }
            }
            if (count($references)) {
                $lines[] = '#: ' . implode(' ', $references);
            }
        }

        file_put_contents($pot, implode("\n", $lines));

        return Command::SUCCESS;
    }

    /**
     * Fix a reference if it targets a compiled template
     *
     * @param string $reference     Reference, as "path:line"
     * @param string $pot_dir       Directory of the POT file, references are relative to it
     * @param string $templates_dir Templates directory
     * @param string $cache_dir     Compiled templates directory
     */
    private function fixReference(string $reference, string $pot_dir, string $templates_dir, string $cache_dir): string
    {
        if (!preg_match('/^(.+):(\d+)$/', $reference, $matches)) {
            return $reference;
        }

        $compiled = $pot_dir . '/' . $matches[1];
        if (!is_file($compiled)) {
            return $reference;
        }
        $compiled = realpath($compiled);
        if (!str_starts_with($compiled, $cache_dir . '/')) {
            return $reference;
        }

        $template = substr($compiled, strlen($cache_dir) + 1);
        $fixed = $this->getRelativePath($pot_dir, $templates_dir . '/' . $template);

        $template_line = $this->getTemplateLine($compiled, (int)$matches[2]);
        if ($template_line !== null) {
            $fixed .= ':' . $template_line;
        }

        return $fixed;
    }

    /**
     * Get template line matching a line of its compiled version,
     * the same way Twig does to report errors
     *
     * @param string $compiled Compiled template path
     * @param int    $line     Line in compiled template
     */
    private function getTemplateLine(string $compiled, int $line): ?int
    {
        if (!isset($this->debug_infos[$compiled])) {
            $debug_info = [];
            if (preg_match('/function getDebugInfo\(\): array\s*\{\s*return (?:array \(|\[)([^)\]]*)/', file_get_contents($compiled), $matches)) {
                preg_match_all(
                    pattern: '/(\d+)\s*=>\s*(\d+)/',
                    subject: $matches[1],
                    matches: $pairs,
                    flags: PREG_SET_ORDER
                );
                foreach ($pairs as $pair) {
                    $debug_info[(int)$pair[1]] = (int)$pair[2];
                }
            }
            krsort($debug_info);
            $this->debug_infos[$compiled] = $debug_info;
        }

        foreach ($this->debug_infos[$compiled] as $code_line => $template_line) {
            if ($code_line <= $line) {
                return $template_line;
            }
        }

        return null;
    }

    /**
     * Get path relative to a directory
     *
     * @param string $from Absolute directory path
     * @param string $to   Absolute path
     */
    private function getRelativePath(string $from, string $to): string
    {
        $from_parts = explode('/', trim($from, '/'));
        $to_parts = explode('/', trim($to, '/'));

        while (count($from_parts) && count($to_parts) && $from_parts[0] === $to_parts[0]) {
            array_shift($from_parts);
            array_shift($to_parts);
        }

        return str_repeat('../', count($from_parts)) . implode('/', $to_parts);
    }
}
