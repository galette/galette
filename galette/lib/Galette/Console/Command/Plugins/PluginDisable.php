<?php

namespace Galette\Console\Command\Plugins;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'galette:plugins:disable',
    description: 'Disable Galette plugins'
)]
class PluginDisable extends AbstractPlugins
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $selected = $input->getArgument('plugins');
        if ($selected === [self::ALL]) {
            $selected = array_keys($this->getRelevantPlugins($io));
        }

        foreach ($selected as $module_id) {
            $this->plugins->deactivateModule($module_id);
            $io->success(sprintf('Plugin "%s" disabled', $module_id));
        }

        return Command::SUCCESS;
    }

    protected function getRelevantPlugins(SymfonyStyle $io): array
    {
        $enabled_plugins = $this->plugins->getModules();
        return $enabled_plugins;
    }
}