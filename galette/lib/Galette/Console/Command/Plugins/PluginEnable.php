<?php

namespace Galette\Console\Command\Plugins;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'galette:plugins:enable',
    description: 'Enable Galette plugins'
)]
class PluginEnable extends AbstractPlugins
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $selected = $input->getArgument('plugins');
        if ($selected === [self::ALL]) {
            $selected = array_keys($this->getRelevantPlugins($io));
        }

        foreach ($selected as $module_id) {
            $this->plugins->activateModule($module_id);
            $io->success(sprintf('Plugin "%s" enabled', $module_id));
        }

        return Command::SUCCESS;
    }

    protected function getRelevantPlugins(SymfonyStyle $io): array
    {
        $disabled_plugins = $this->plugins->getDisabledModules();

        $relevant_plugins = [];
        foreach ($disabled_plugins as $module_id => $module) {
            if ($module['cause'] == \Galette\Core\Plugins::DISABLED_EXPLICIT) {
                $relevant_plugins[$module_id] = $module;
            } else {
                switch ($module['cause']) {
                    case \Galette\Core\Plugins::DISABLED_COMPAT:
                        $module['cause'] = 'Not compatible';
                        break;
                    case \Galette\Core\Plugins::DISABLED_MISS:
                        $module['cause'] = 'Miss a required file';
                        break;
                }
                $io->writeln(
                    sprintf('Plugin "%s" is not explicitly disabled (%s)', $module_id, $module['cause']),
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }
        }

        return $relevant_plugins;
    }
}