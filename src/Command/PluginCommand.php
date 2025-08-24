<?php

declare(strict_types=1);

namespace App\Command;

use App\Plugin\PluginManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:plugin',
    description: 'Manage Musicarr plugins'
)]
class PluginCommand extends Command
{
    public function __construct(
        private PluginManager $pluginManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List all plugins')
            ->addOption('info', 'i', InputOption::VALUE_REQUIRED, 'Show detailed information about a plugin')
            ->setHelp('This command allows you to manage Musicarr plugins.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Plugins are injected at compile time via tagged_iterator

        if ($input->getOption('list')) {
            return $this->listPlugins($io);
        }

        if ($pluginName = $input->getOption('info')) {
            return $this->showPluginInfo($io, $pluginName);
        }

        // Default: show help
        $io->error('Please specify an action. Use --help for more information.');

        return Command::FAILURE;
    }

    private function listPlugins(SymfonyStyle $io): int
    {
        $plugins = $this->pluginManager->getPlugins();

        if (empty($plugins)) {
            $io->info('No plugins found.');

            return Command::SUCCESS;
        }

        $io->title('Musicarr Plugins');

        $rows = [];
        foreach ($plugins as $pluginName => $pluginData) {
            $rows[] = [
                $pluginName,
                $pluginData['version'] ?? 'Unknown',
                $pluginData['description'] ?? 'No description',
                $pluginData['author'] ?? 'Unknown',
                'Available',
            ];
        }

        $io->table(
            ['Name', 'Version', 'Description', 'Author', 'Status'],
            $rows
        );

        return Command::SUCCESS;
    }

    private function showPluginInfo(SymfonyStyle $io, string $pluginName): int
    {
        $pluginData = $this->pluginManager->getPlugin($pluginName);

        if (!$pluginData) {
            $io->error("Plugin '{$pluginName}' not found.");

            return Command::FAILURE;
        }

        $io->title("Plugin: {$pluginName}");

        $info = [
            ['Name', $pluginData['name'] ?? $pluginName],
            ['Version', $pluginData['version'] ?? 'Unknown'],
            ['Description', $pluginData['description'] ?? 'No description'],
            ['Author', $pluginData['author'] ?? 'Unknown'],
            ['Status', 'Available'],
        ];

        $io->table(['Property', 'Value'], $info);

        return Command::SUCCESS;
    }
}
