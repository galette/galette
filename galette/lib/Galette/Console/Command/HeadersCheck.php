<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Exception;
use Override;
use RecursiveDirectoryIterator;
use RecursiveFilterIterator;
use RecursiveIterator;
use RecursiveIteratorIterator;
use Safe\Exceptions\FilesystemException;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Safe\file;
use function Safe\file_put_contents;
use function Safe\preg_grep;
use function Safe\preg_match;
use function Safe\realpath;

/**
 * Headers check and fix console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:headers:check',
    description: 'Check Galette files headers'
)]

final class HeadersCheck extends AbstractCommand
{
    /**
     * Header lines.
     *
     * @var ?string[]
     */
    private ?array $header_lines = null;

    /**
     * Configures the current command.
     */
    #[Override]
    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'directory',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Directory to parse (optional)',
        );

        $this->addOption(
            'header-file',
            null,
            InputOption::VALUE_OPTIONAL,
            'Header file to use (optional)',
        );

        $this->addOption(
            'fix',
            'f',
            InputOption::VALUE_NONE,
            'Fix missing and outdated headers'
        );

        $this->addOption(
            name: 'owner',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Copyright owner',
            default: 'The Galette Team',
        );

        $this->addOption(
            name: 'project-name',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Project name',
            default: 'Galette',
        );

        $this->addOption(
            name: 'project-url',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Project URL',
            default: 'https://galette.eu',
        );

        $this->addOption(
            name: 'start-year',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Start year',
            default: '2003',
        );

        $this->addOption(
            name: 'end-year',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'End year',
            default: date('Y'),
        );
    }

    /**
     * Command execution
     *
     * @param InputInterface  $input  Input interface
     * @param OutputInterface $output Output interface
     *
     * @return int Command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project_dir = dirname(__DIR__, 5);

        /** @var string|null $header_file_path */
        $header_file_path = $this->input->getOption('header-file');
        if (!$header_file_path) {
            $path = implode(DIRECTORY_SEPARATOR, [$project_dir, '.licence-header']);
            $legacy_path = implode(DIRECTORY_SEPARATOR, [$project_dir, '.docheader']);
            if (file_exists($path)) {
                $header_file_path = realpath($path);
            } elseif (file_exists($legacy_path)) {
                $header_file_path = realpath($legacy_path);
            }
        }

        if (!$header_file_path) {
            throw new \RuntimeException('No header path defined.');
        }

        if ($this->io->isVerbose()) {
            $this->io->info(sprintf('HEADER path: %s', $header_file_path));
        }

        $target_dir = $input->getOption('directory') ?? $project_dir;
        $files = $this->getFilesToParse($target_dir);

        if ($this->io->isVerbose()) {
            $this->io->info(sprintf('%s files to process in %s.', count($files), $target_dir));
        }

        $missing_found   = 0;
        $missing_errors  = 0;
        $outdated_found  = 0;
        $outdated_errors = 0;

        /** @var string $filename */
        foreach ($files as $filename) {
            if ($this->io->isVerbose()) {
                $this->io->text('<comment>' . sprintf('Processing "%s".', $filename) . '</comment>');
            }

            try {
                $file_lines = file($filename);
            } catch (FilesystemException $e) {
                throw new Exception(sprintf('Unable to read file "%s".', $filename), $e->getCode(), $e);
            }

            $header_start_pattern   = null;
            $header_end_pattern     = null;
            $header_content_pattern = null;

            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if ($extension === '') {
                // No extension, file is probably a binary.
                // Try to compute extension from shebang.
                $first_line = $file_lines[0];
                if (preg_match('/^#!/', $first_line)) {
                    $shebang_matches = [];
                    if (
                        // `#!/usr/bin/env php [options]` format
                        preg_match('/^#!\/usr\/bin\/env\s+(?<binary>[^\s]+)(\s+.*)?$/', $first_line, $shebang_matches)
                        // `#!/bin/bash [options]` format
                        || preg_match('/^#!(.{0}|\/([^\/]+\/)*(?<binary>[^\/\s]+))(\s+.*)?$/', $first_line, $shebang_matches)
                    ) {
                        $binary = $shebang_matches['binary'];
                        $extension = match ($shebang_matches['binary']) {
                            'bash' => 'sh',
                            'perl' => 'pl',
                            default => $binary,
                        };
                    }
                }
            }
            switch ($extension) {
                case 'pl':
                case 'sh':
                case 'yaml':
                case 'yml':
                    $header_line_prefix     = '# ';
                    $header_prepend_line    = "#\n";
                    $header_append_line     = "#\n";
                    $header_start_pattern   = '/^#[^!]/'; // Any commented line except shebang (#!)
                    $header_content_pattern = '/^#/';
                    break;
                case 'sql':
                    $header_line_prefix     = '-- ';
                    $header_prepend_line    = "--\n";
                    $header_append_line     = "--\n";
                    $header_content_pattern = '/^(--|#)/'; // older headers were prefixed by "#"
                    break;
                case 'css':
                case 'scss':
                    $header_line_prefix     = ' * ';
                    $header_prepend_line    = "/*!\n";
                    $header_append_line     = " */\n";
                    $header_start_pattern   = '/^\/\*([!*])?$/'; // older headers were starting by "/**" or "/*!"
                    $header_end_pattern     = '/\*\//';
                    break;
                case 'twig':
                    $header_line_prefix     = ' # ';
                    $header_prepend_line    = "{#\n";
                    $header_append_line     = " #}\n";
                    $header_start_pattern   = '/^\{#$/';
                    $header_end_pattern     = '/#}/';
                    break;
                default:
                    $header_line_prefix     = ' * ';
                    $header_prepend_line    = "/**\n";
                    $header_append_line     = " */\n";
                    $header_start_pattern   = '/^\/\*([!*])?$/'; // accept "/*", "/**" and "/*!"
                    $header_end_pattern     = '/\*\//';
                    break;
            }

            if ($header_start_pattern === null) {
                // If there is no specific "start pattern", then first regular comment line is consider are header start.
                $header_start_pattern = $header_content_pattern;
            }

            $header_found         = false;
            $header_missing       = false;
            $is_header_line       = false;
            $is_last_header_line  = false;
            $pre_header_lines     = [];
            $current_header_lines = [];
            $post_header_lines    = [];

            foreach ($file_lines as $line) {
                if (!$header_found && !$header_missing) {
                    if (preg_match($header_start_pattern, $line)) {
                        // Line matches header opening line
                        $header_found = true;
                        $is_header_line = true;
                    } elseif (!$this->shouldLineBeLocatedBeforeHeader($line)) {
                        // Line does not match allowed lines before header,
                        // consider that header is missing.
                        $header_missing = true;
                    }
                } elseif ($is_last_header_line) {
                    // Previous line was "last header line", so current line is the first line after license header
                    $is_last_header_line = false;
                    $is_header_line = false;
                } elseif ($is_header_line && $header_end_pattern !== null && preg_match($header_end_pattern, $line)) {
                    // Line matches header end pattern
                    $is_last_header_line = true;
                } elseif ($is_header_line && $header_content_pattern !== null && !preg_match($header_content_pattern, $line)) {
                    // Line does not match header, so it is the first line after license header
                    $is_header_line = false;
                }

                if ($header_missing || ($header_found && !$is_header_line)) {
                    $post_header_lines[] = $line;
                } elseif ($is_header_line) {
                    $current_header_lines[] = $line;
                } else {
                    $pre_header_lines[] = $line;
                }
            }

            $updated_header_lines = $this->getLicenceHeaderLines(
                $header_file_path,
                $header_line_prefix,
                $header_prepend_line,
                $header_append_line,
                //$preserved_tagged_data
            );

            $sliced_header_lines = array_slice($updated_header_lines, 1, -1);
            $header_outdated = $sliced_header_lines !== array_slice($current_header_lines, 1, -1);

            if (!$header_missing && !$header_outdated) {
                continue;
            }

            if ($header_missing) {
                $this->io->writeln(sprintf('<fg=red>Missing licence header in file "%s".</>', $filename));
                $missing_found++;
            } else {
                $this->io->writeln(sprintf('<fg=yellow>Licence header outdated in file "%s".</>', $filename));
                $outdated_found++;
            }

            if ($this->input->getOption('fix')) {
                $pre_header_lines  = $this->stripEmptyLines($pre_header_lines, false, true);
                $post_header_lines = $this->stripEmptyLines($post_header_lines, true, false);

                $file_contents = '';
                if ($pre_header_lines !== []) {
                    $file_contents .= implode('', $pre_header_lines) . "\n";
                }
                $file_contents .= implode('', $updated_header_lines);
                if ($post_header_lines !== []) {
                    $file_contents .= "\n" . implode('', $post_header_lines);
                }

                if (strlen($file_contents) !== file_put_contents($filename, $file_contents)) {
                    $this->io->error(sprintf('Unable to update licence header in file "%s".', $filename));
                    if ($header_missing) {
                        $missing_errors++;
                    } else {
                        $outdated_errors++;
                    }
                }
            }
        }

        if ($missing_found === 0 && $outdated_found === 0) {
            $this->io->success('Files headers are valid.');
            return 0; // Success
        }

        $build_msg = function (int $missing_count, int $outdated_count): string {
            $messages = [];
            if ($missing_count > 0) {
                $messages[] = sprintf(
                    '%d file%s without header',
                    $missing_count,
                    $missing_count > 1 ? 's' : ''
                );
            }
            if ($outdated_count > 0) {
                $messages[] = sprintf(
                    '%d file%s with outdated header',
                    $outdated_count,
                    $outdated_count > 1 ? 's' : ''
                );
            }
            return implode(' and ', $messages);
        };

        if (!$this->input->getOption('fix')) {
            $msg = sprintf(
                'Found %s. Use --fix option to fix these files.',
                $build_msg($missing_found, $outdated_found)
            );
            $this->io->error($msg);
            return self::FAILURE;
        }

        $msg = sprintf(
            'Fixed %s.',
            $build_msg(
                $missing_found - $missing_errors,
                $outdated_found - $outdated_errors
            )
        );
        $this->io->success($msg);

        if ($missing_errors > 0 || $outdated_errors > 0) {
            $this->io->error(sprintf('%s file(s) cannot be updated.', $missing_errors + $outdated_errors));
            return self::FAILURE;
        }

        return 0; // Success
    }

    /**
     * Get license header lines.
     *
     * @param array<string, array<int, mixed>> $extra_tagged_data
     *
     * @return array<int, string>
     */
    private function getLicenceHeaderLines(
        string $header_file_path,
        string $line_prefix,
        string $prepend_line,
        string $append_line,
        array $extra_tagged_data = []
    ): array {
        $this->buildHeaderLines($header_file_path);

        $lines = [];
        $lines[] = $prepend_line;
        foreach ($this->header_lines as $line) {
            $lines[] = (preg_match('/^\s+$/', $line) ? rtrim($line_prefix) : $line_prefix) . $line;
        }
        $lines[] = $append_line;

        $lines = $this->appendTaggedData($lines, $extra_tagged_data, $line_prefix);

        return $this->stripEmptyLines($lines, true, true);
    }

    /**
     * Return files to parse.
     *
     * @return string[]
     */
    private function getFilesToParse(string $directory): array
    {
        $original_directory = $directory;

        try {
            $directory = realpath($directory);
        } catch (FilesystemException $e) {
            throw new InvalidOptionException(
                message: sprintf('Unable to read directory "%s"', $original_directory),
                code: $e->getCode(),
                previous: $e
            );
        }

        if (!is_dir($directory) || !is_readable($directory)) {
            throw new InvalidOptionException(
                sprintf('Unable to read directory "%s"', $directory)
            );
        }

        $dir_iterator = new RecursiveDirectoryIterator($directory);
        $exclusion_pattern = $this->getExclusionPattern($directory);

        $filter_iterator = new class ($dir_iterator, $exclusion_pattern) extends RecursiveFilterIterator {
            /**
             * Default constructor
             *
             * @param RecursiveIterator<string, SplFileInfo> $iterator
             */
            public function __construct(RecursiveIterator $iterator, private readonly ?string $exclusion_pattern)
            {
                parent::__construct($iterator);
            }

            /**
             * Accept
             */
            public function accept(): bool
            {
                $file = $this->current();
                if ($this->exclusion_pattern !== null && preg_match($this->exclusion_pattern, $file->getRealPath())) {
                    return false;
                }
                if ($file->isDir()) {
                    return true; // parse subdirectories
                }
                if (preg_match('/^(css|js|ts|php|pl|scss|sh|sql|twig|ya?ml)$/', $file->getExtension())) {
                    return true; // handled extensions
                }
                if (basename((string)$file->getPath()) === 'bin') {
                    return true; // executable
                }
                return false;
            }

            /**
             * Get children
             */
            public function getChildren(): RecursiveFilterIterator
            {
                /** @var RecursiveIterator<string, SplFileInfo> $inner */
                $inner = $this->getInnerIterator();
                return new self($inner->getChildren(), $this->exclusion_pattern);
            }
        };

        $recursive_iterator = new RecursiveIteratorIterator(
            $filter_iterator,
            RecursiveIteratorIterator::SELF_FIRST
        );

        $files = [];

        /** @var SplFileInfo $file */
        foreach ($recursive_iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * Indicates if a line can/should be located before license header.
     */
    private function shouldLineBeLocatedBeforeHeader(string $line): bool
    {
        // PHP opening tag
        if (rtrim($line) === '<?php') {
            return true;
        }

        // Shebang
        if (preg_match('/^#!/', $line)) {
            return true;
        }

        // File generated by bootstrap
        if (
            str_contains($line, '// webpackBootstrap')
            || rtrim($line) === 'var __webpack_exports__ = {};'
        ) {
            return true;
        }
        // Empty line
        return trim($line) === '';
    }

    /**
     * Strip empty top/bottom lines from an array.
     *
     * @param array<int, string> $lines
     *
     * @return string[]
     */
    private function stripEmptyLines(array $lines, bool $strip_top_lines, bool $strip_bottom_lines): array
    {
        // Remove empty lines from top of an array
        $strip_top_fct = function (array $values): array {
            $filtered_values = [];
            $found_not_empty = false;

            foreach ($values as $value) {
                if (!$found_not_empty && empty(trim($value))) {
                    continue;
                }
                $found_not_empty = true;
                $filtered_values[] = $value;
            }

            return $filtered_values;
        };

        if ($strip_top_lines) {
            $lines = $strip_top_fct($lines);
        }

        if ($strip_bottom_lines) {
            $lines = array_reverse($lines);
            $lines = $strip_top_fct($lines);
            $lines = array_reverse($lines);
        }

        return $lines;
    }

    /**
     * Extract tagged data from header lines.
     *
     * @param array<int, string> $lines
     *
     * @return array<string, array<int, string>>
     */
    private function extractTaggedData(array $lines, ?string $line_prefix = null): array
    {

        $tagged_data = [];

        $tag_pattern = $this->getTagPattern($line_prefix);

        foreach ($lines as $line) {
            $tag = null;
            if (preg_match($tag_pattern, $line, $tag)) {
                $tag_name = $tag['name'];
                $tag_value = $tag['value'];

                if (!array_key_exists($tag_name, $tagged_data)) {
                    $tagged_data[$tag_name] = [];
                }
                $tagged_data[$tag_name][] = $tag_value;
            }
        }

        return $tagged_data;
    }

    /**
     * Append tagged data to header lines.
     *
     * @param array<int, string>               $lines
     * @param array<string, array<int, mixed>> $data_to_append
     *
     * @return array<int, string>
     */
    private function appendTaggedData(array $lines, array $data_to_append, ?string $line_prefix = null): array
    {

        $existing_data = $this->extractTaggedData($lines, $line_prefix);

        if (count($existing_data) === 0) {
            $existing_tag_lines_nums = [];
            $append_line_num = count($lines); // There is no tag in given lines, append new tags to the end.
        } else {
            $data_to_append = array_merge_recursive($existing_data, $data_to_append);
            $data_to_append = array_map(array_unique(...), $data_to_append);
            ksort($data_to_append);

            $existing_tag_lines_nums = array_keys(preg_grep($this->getTagPattern($line_prefix), $lines));
            $append_line_num = $existing_tag_lines_nums[0];
        }

        // Deduplicate tagged data
        foreach ($data_to_append as $tag_name => $tag_values) {
            if (preg_match('/^copy(right|left)$/', $tag_name) !== 1) {
                continue;
            }
            $data_to_append[$tag_name] = $this->unduplicateCopyTag($tag_values);
        }

        // Drop existing tag lines and re-append merged tagged data entirely
        $result_lines = [];
        foreach ($lines as $num => $line) {
            if (!in_array($num, $existing_tag_lines_nums)) {
                $result_lines[] = $line; // Line is not a tag line, keep it.
            }
            if ($num === $append_line_num) {
                // Append entire tag data
                $pad = max(array_map(strlen(...), array_keys($data_to_append)));
                foreach ($data_to_append as $tag_name => $tag_values) {
                    foreach ($tag_values as $tag_value) {
                        $result_lines[] = $line_prefix . sprintf('@%s %s', str_pad((string)$tag_name, $pad), $tag_value) . "\n";
                    }
                }
            }
        }

        return $result_lines;
    }

    /**
     * Get regex pattern used to detect/extract tagged data.
     */
    private function getTagPattern(?string $line_prefix = null): string
    {
        return '/^'
           . ($line_prefix !== null ? '(?:' . preg_quote($line_prefix, '/') . ')?' : '') // may be prefixed by line prefix
           . '\s*' // may be prefixed by whitespace
           . '@(?<name>[a-z]+)' // @tagname
           . '\s+' // space between tag and value
           . '(?<value>.+)' // value
           . '$/i';
    }

    /**
     * Unduplicate copyright/copyleft tags values.
     *
     * @param array<int, mixed> $values
     *
     * @return array<int, mixed>
     */
    private function unduplicateCopyTag(array $values): array
    {
        $copy_dates_pattern = '/^'
           . '(?<before>.+\s+)?' // capture everything before dates
           . '(?<starting_date>\d{4})' // mandatory date (unique year or starting year)
           . '(-(?<ending_date>\d{4}))?' // optionnal ending date with `-` separator
           . '(?<after>\s+.+)?' // capture everything after dates
           . '$/';

        $preserved_values = [];

        foreach ($values as $value) {
            $dates_matches = [];
            if (preg_match($copy_dates_pattern, $value, $dates_matches) !== 1) {
                continue;
            }

            $before = trim($dates_matches['before'] ?? '');
            $before_pattern = $before !== ''
               ? '\s*' . preg_quote($before, '/') . '\s+'
               : '';
            $after = trim($dates_matches['after'] ?? '');
            $after_pattern = $after !== ''
               ? '\s+' . preg_quote($after, '/') . '\s*'
               : '';

            $similar_pattern = '/^'
               . $before_pattern
               . '(?<starting_date>\d{4})(-(?<ending_date>\d{4}))?'
               . $after_pattern
               . '$/';

            if (count(preg_grep($similar_pattern, $preserved_values)) > 0) {
                // similar value already computed
                continue;
            }

            $similar_values = preg_grep($similar_pattern, $values);

            if (count($similar_values) === 1) {
                // found only current value, no need to deduplicate
                $preserved_values[] = $value;
                continue;
            }

            // Compute min starting and max ending dates
            $starting_date = $dates_matches['starting_date'];
            $ending_date   = !empty($dates_matches['ending_date']) ? $dates_matches['ending_date'] : $starting_date;
            foreach ($similar_values as $similar_value) {
                $similar_dates_matches = [];
                preg_match($copy_dates_pattern, $similar_value, $similar_dates_matches);
                if ($similar_dates_matches['starting_date'] < $starting_date) {
                    $starting_date = $similar_dates_matches['starting_date'];
                } elseif ($similar_dates_matches['starting_date'] > $ending_date) {
                    $ending_date = $similar_dates_matches['starting_date'];
                }
                if (!empty($similar_dates_matches['ending_date']) && $similar_dates_matches['ending_date'] > $ending_date) {
                    $ending_date = $similar_dates_matches['ending_date'];
                }
            }
            $preserved_values[] = ($dates_matches['before'] ?? '')
               . $starting_date
               . ($ending_date !== $starting_date ? '-' . $ending_date : '')
               . ($dates_matches['after'] ?? '');
        }

        return $preserved_values;
    }

    /**
     * Get files exclusion pattern. All files matching this pattern will be excluded from checks.
     */
    protected function getExclusionPattern(string $directory): string
    {
        $excluded_elements = [
            '(\.|.*\/\.).+', // Any hidden file/directory
            'local_.+', // local configuration files

            'node_modules(?:\/.*)?', // npm imported libs
            'galette\/vendor(?:\/.*)?', // composer imported libs
            'vendor(?:\/.*)?', // composer imported libs
            'galette\/plugins(?:\/.*)?', // plugins
            'galette\/data(?:\/.*)?', // data files
            'galette\/tempcache(?:\/.*)?',
            'galette\/config\/config\.inc\.php',
            'playwright-report(?:\/.*)?',

            'semantic(?:\/.*)?', // Semantic UI assets

            'galette\/webroot\/assets(?:\/.*)?',
            'galette\/webroot\/themes(?:\/.*)?',

            'tests\/config(?:\/.*)?',

            //outside licenced files
            'galette\/docs\/source\/doxygen-awesome(-sidebar-only)?\.css',
        ];

        return '/^'
           . preg_quote($directory . DIRECTORY_SEPARATOR, '/')
           . '(' . implode('|', $excluded_elements) . ')'
           . '$/';
    }

    /**
     * Build header lines and proceed variables replacements.
     */
    private function buildHeaderLines(string $header_file_path): void
    {
        if ($this->header_lines === null) {
            try {
                $lines = file($header_file_path);
            } catch (FilesystemException $e) {
                throw new Exception('Unable to read header file.', $e->getCode(), $e);
            }
            foreach ($lines as $line) {
                $this->header_lines[] = str_replace(
                    [
                        '%START_YEAR%',
                        '%END_YEAR%',
                        '%OWNER%',
                        '%PROJECT%',
                        '%URL%',
                    ],
                    [
                        $this->input->getOption('start-year'),
                        $this->input->getOption('end-year'),
                        $this->input->getOption('owner'),
                        $this->input->getOption('project-name'),
                        $this->input->getOption('project-url'),
                    ],
                    $line
                );
            }
        }
    }
}
