<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Galette\Core\CacheManager;
use Galette\Util\Filesize;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clear Galette cache
 *
 * Files are removed as the user running the command: run it as the web server
 * user, or the web server may not be able to write its cache again.
 *
 * OPcache is not handled here: the command line has its own, the web server
 * one can only be reset from the advanced configuration page.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:cache:clear',
    description: 'Clear Galette cache, or some of its parts only'
)]
class CacheClear extends AbstractCommand
{
    private readonly CacheManager $cache;

    /**
     * Default constructor
     *
     * @param string        $basepath Base path to Galette installation
     * @param ?CacheManager $cache    Cache manager, the default one when omitted
     */
    public function __construct(string $basepath, ?CacheManager $cache = null)
    {
        $this->cache = $cache ?? new CacheManager();
        parent::__construct($basepath);
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'caches',
                mode: InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                description: 'Caches to clear (see --list); those cleared by default when omitted'
            )
            ->addOption(
                name: 'list',
                shortcut: 'l',
                mode: InputOption::VALUE_NONE,
                description: 'List caches and what they hold, without clearing anything'
            );
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cache = $this->cache;

        if ($input->getOption('list')) {
            $rows = [];
            foreach ($cache->getEntries() as $entry) {
                $rows[] = [
                    $entry['key'],
                    $entry['key'] === CacheManager::OLD_VERSIONS || $entry['sensitive'] ? 'no' : 'yes',
                    $entry['files'],
                    Filesize::fromBytes($entry['size']),
                    $entry['mtime'] !== null ? date('Y-m-d H:i:s', $entry['mtime']) : '',
                ];
            }
            $this->io->table(['Cache', 'Cleared by default', 'Files', 'Size', 'Last change'], $rows);
            return Command::SUCCESS;
        }

        /** @var array<int, string> $keys */
        $keys = $input->getArgument('caches');
        $unknown = array_diff($keys, $cache->getKeys());
        if ($unknown !== []) {
            $this->io->error(sprintf(
                'Unknown cache(s): %s. Known ones are: %s.',
                implode(', ', $unknown),
                implode(', ', $cache->getKeys())
            ));
            return Command::INVALID;
        }

        $failed = false;
        foreach ($keys === [] ? [CacheManager::ALL] : $keys as $key) {
            if (!$cache->clear($key)) {
                $failed = true;
                foreach ($cache->getErrors() as $error) {
                    $this->io->error($error);
                }
            }
        }

        if ($failed) {
            $this->io->note('Run this command as the web server user, who owns the cache files.');
            return Command::FAILURE;
        }

        if ($keys === []) {
            $this->io->success('Cache has been cleared.');
            $this->io->note(sprintf(
                'Some caches are only cleared when named, see --list: %s.',
                implode(', ', array_filter(
                    $cache->getKeys(),
                    fn(string $key): bool => $key === CacheManager::OLD_VERSIONS || $cache->isSensitive($key)
                ))
            ));
        } else {
            $this->io->success('Cache cleared: ' . implode(', ', $keys) . '.');
        }
        return Command::SUCCESS;
    }
}
