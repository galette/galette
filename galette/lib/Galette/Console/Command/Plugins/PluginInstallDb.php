<?php

namespace Galette\Console\Command\Plugins;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'galette:plugins:install-db',
    description: 'Install Galette plugins database'
)]
class PluginInstallDb extends AbstractPlugins
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }

    protected function getRelevantPlugins(SymfonyStyle $io): array
    {
        $enabled_plugins = $this->plugins->getModules();

        $relevant_plugins = [];
        foreach ($enabled_plugins as $module_id => $module) {
            if ($this->plugins->needsDatabase($module_id)) {
                $relevant_plugins[$module_id] = $module;
            } else {
                $io->writeln(
                    sprintf('Plugin "%s" doe snot use a database', $module_id),
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }
        }

        return $relevant_plugins;
    }
}