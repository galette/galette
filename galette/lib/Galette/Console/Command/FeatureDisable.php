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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Turn off preview features
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:feature:disable',
    description: 'Turn off preview features'
)]
class FeatureDisable extends AbstractFeature
{
    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'flag',
                mode: InputArgument::OPTIONAL,
                description: 'Feature flag to turn off; the ones requiring it are turned off as well'
            )
            ->addOption(
                name: 'all',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Turn off every preview feature'
            );
        $this->addForceOption();
    }

    /**
     * Command execution
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $container;

        $preferences = $container->get(Preferences::class);
        $flags = new FeatureFlagManager($preferences);

        $this->io->title('Turn off preview features');

        $flag = $input->getArgument('flag');
        $all = (bool)$input->getOption('all');
        if (($flag === null) === !$all) {
            $this->io->error('Give either a feature flag or --all.');
            return Command::FAILURE;
        }

        if ($all) {
            //whatever is stored goes, including flags that are no longer
            //preview features and are ignored anyway
            $removed = $preferences->getFeatureFlags();
            $remaining = [];
        } else {
            try {
                $change = $flags->computeTurnOff((string)$flag);
            } catch (\DomainException $e) {
                // no log: a refused change, reported to the user
                $this->io->error($e->getMessage());
                return Command::FAILURE;
            }
            $removed = $change['removed'];
            $remaining = $change['flags'];
        }

        if ($removed === []) {
            $this->io->warning('No such preview feature is turned on.');
            return Command::SUCCESS;
        }

        $this->io->text(sprintf('Features to turn off: <info>%s</info>', implode(', ', $removed)));
        if (!$this->confirm($input, 'Turn them off?')) {
            return Command::FAILURE;
        }

        return $this->store($remaining, $removed, turn_on: false);
    }
}
