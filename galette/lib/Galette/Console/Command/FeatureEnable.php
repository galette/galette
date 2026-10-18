<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Galette\Core\FeatureFlagManager;
use Galette\Core\Preferences;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Turn on an experimental feature
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:feature:enable',
    description: 'Turn on an experimental feature'
)]
class FeatureEnable extends AbstractFeature
{
    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->addArgument(
            name: 'flag',
            mode: InputArgument::REQUIRED,
            description: 'Feature flag to turn on; the features it requires are turned on as well'
        );
        $this->addForceOption();
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $container;

        $flags = new FeatureFlagManager($container->get(Preferences::class));

        $this->io->title('Turn on an experimental feature');

        try {
            $change = $flags->computeTurnOn((string)$input->getArgument('flag'));
        } catch (\DomainException $e) {
            // no log: a refused change, reported to the user
            $this->io->error($e->getMessage());
            if ($e->getCode() === FeatureFlagManager::ERR_UNKNOWN) {
                $registered = array_keys($flags->getRegistryFlags());
                $this->io->text(
                    $registered === []
                        ? 'There is no experimental feature.'
                        : sprintf('Experimental features: <info>%s</info>', implode(', ', $registered))
                );
            }
            return Command::FAILURE;
        }

        $added = $change['added'];
        if ($added === []) {
            $this->io->warning('This feature is already turned on.');
            return Command::SUCCESS;
        }

        $this->io->text(sprintf('Features to turn on: <info>%s</info>', implode(', ', $added)));
        if (!$this->confirm($input, 'Turn them on?')) {
            return Command::FAILURE;
        }

        return $this->store($change['flags'], $added, turn_on: true);
    }
}
