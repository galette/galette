<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Console\Command\Plugins;

use Galette\Console\Command\AbstractCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Abstract command for plugins
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class AbstractPlugins extends AbstractCommand
{
    public const string ALL = '*';

    protected \Galette\Core\Plugins $plugins;

    /**
     * Default constructor
     *
     * @param string $basepath Base path to Galette installation
     */
    public function __construct(string $basepath)
    {
        global $container;

        parent::__construct($basepath);
        $this->plugins = $container->get(\Galette\Core\Plugins::class);
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this
            ->addArgument('plugins', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Plugins names')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Enable plugin(s)')
        ;
    }

    /**
     * Interacts to request missing arguments or options
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Galette plugins management');

        $plugins = $input->getArgument('plugins');
        $all = $input->getOption('all');

        if ($all && $plugins) {
            throw new InvalidArgumentException('You can\'t use --all and specify plugins at the same time');
        }

        if ($all) {
            $input->setArgument('plugins', [self::ALL]);
        } elseif (empty($plugins)) {
            // Ask for plugin list if argument is empty
            $choices = $this->getRelevantChoices($io);

            if ($choices !== []) {
                $choices = array_merge(
                    [self::ALL => 'All plugins'],
                    $choices
                );

                /** @var \Symfony\Component\Console\Helper\QuestionHelper $question_helper */
                $question_helper = $this->getHelper('question');
                $question = new ChoiceQuestion(
                    'Which plugins do you want to select?',
                    $choices
                );
                $question->setAutocompleterValues(array_keys($choices));
                $question->setMultiselect(true);
                $answer = $question_helper->ask(
                    $input,
                    $output,
                    $question
                );
                $input->setArgument('plugins', $answer);
            }
        }

        $unknown_plugins = array_diff($plugins, $this->getPlugins());
        if (count($unknown_plugins)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unknown plugin(s): %s',
                    implode(
                        ', ',
                        $unknown_plugins
                    )
                )
            );
        }

        $irrelevant_plugins = array_diff($plugins, array_keys($this->getRelevantPlugins($io)));
        if (count($irrelevant_plugins)) {
            $verbose = count($irrelevant_plugins) < count($input->getArgument('plugins')) ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL;
            $io->writeln(
                sprintf(
                    '<error>Irrelevant plugin(s): %s</error>',
                    implode(
                        ', ',
                        $irrelevant_plugins
                    )
                ),
                $verbose
            );
        }

        if (!count($input->getArgument('plugins'))) {
            $io->error('No relevant plugin found.');
            exit(1);
        }
    }

    /**
     * Get active plugins ids list
     *
     * @return array<string>
     */
    protected function getActivePlugins(): array
    {
        return array_keys($this->plugins->getModules());
    }

    /**
     * Get inactive plugins ids list
     *
     * @return array<string>
     */
    protected function getInactivePlugins(): array
    {
        return array_keys($this->plugins->getDisabledModules());
    }

    /**
     * Get all plugins ids list
     *
     * @return array<string>
     */
    protected function getPlugins(): array
    {
        return array_merge($this->getActivePlugins(), $this->getInactivePlugins());
    }

    /**
     * Get relevant choices (getRelevantPlugins formatted) for the command
     *
     * @return array<string, string>
     */
    protected function getRelevantChoices(SymfonyStyle $io): array
    {
        $relevant = $this->getRelevantPlugins($io);
        $choices = [];

        foreach ($relevant as $module_id => $module) {
            $choices[$module_id] = $module['name'] ?? $module_id;
        }

        return $choices;
    }

    /**
     * Get relevant plugins for current command
     *
     * @return array<string, array<string, string>>
     */
    abstract protected function getRelevantPlugins(SymfonyStyle $io): array;
}
