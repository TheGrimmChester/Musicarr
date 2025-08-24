<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\RemotePluginInstallerService;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:remote-plugin',
    description: 'Install, update, or remove plugins from remote repositories'
)]
class RemotePluginCommand extends Command
{
    public function __construct(
        private RemotePluginInstallerService $remotePluginInstaller
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:remote-plugin')
            ->setDescription('Manage remote plugins')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform: install, update, remove, or change-reference')
            ->addOption('repository', 'r', InputOption::VALUE_REQUIRED, 'Repository URL')
            ->addOption('plugin-name', 'p', InputOption::VALUE_REQUIRED, 'Plugin name')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Branch/tag/commit to checkout', 'main')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force operation')
            ->addOption('reference', null, InputOption::VALUE_REQUIRED, 'Reference to checkout (for change-reference action)')
            ->addOption('reference-type', null, InputOption::VALUE_REQUIRED, 'Type of reference: branch, tag, or commit (for change-reference action)', 'branch')
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> command manages remote plugins:

  <info>php %command.full_name% install --repository=https://github.com/user/repo --plugin-name=my-plugin</info>
  <info>php %command.full_name% update --plugin-name=my-plugin</info>
  <info>php %command.full_name% remove --plugin-name=my-plugin</info>
  <info>php %command.full_name% change-reference --plugin-name=my-plugin --reference=develop --reference-type=branch</info>

Available actions:
  install          - Install a new plugin from a remote repository
  update           - Update an existing plugin to the latest version
  remove           - Remove an installed plugin
  change-reference - Change the branch/tag/commit of an installed plugin

Examples:
  <info>php %command.full_name% install --repository=https://github.com/user/repo --plugin-name=my-plugin --branch=develop</info>
  <info>php %command.full_name% change-reference --plugin-name=my-plugin --reference=v1.2.0 --reference-type=tag</info>
  <info>php %command.full_name% change-reference --plugin-name=my-plugin --reference=abc1234 --reference-type=commit</info>
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');
        $pluginName = $input->getOption('plugin-name');
        $repositoryUrl = $input->getOption('repository');
        $branch = $input->getOption('branch');
        $force = $input->getOption('force');
        $reference = $input->getOption('reference');
        $referenceType = $input->getOption('reference-type');

        $io = new SymfonyStyle($input, $output);

        try {
            switch ($action) {
                case 'install':
                    if (!$repositoryUrl || !$pluginName) {
                        $io->error('Repository URL and plugin name are required for install action');

                        return Command::FAILURE;
                    }

                    $io->info("Installing plugin '{$pluginName}' from repository...");
                    $result = $this->remotePluginInstaller->installPlugin($repositoryUrl, $pluginName, $branch, $output);

                    break;

                case 'update':
                    if (!$pluginName) {
                        $io->error('Plugin name is required for update action');

                        return Command::FAILURE;
                    }

                    $io->info("Updating plugin '{$pluginName}'...");
                    $result = $this->remotePluginInstaller->updatePlugin($pluginName, $branch, $output);

                    break;

                case 'remove':
                    if (!$pluginName) {
                        $io->error('Plugin name is required for remove action');

                        return Command::FAILURE;
                    }

                    if (!$force) {
                        if (!$io->confirm("Are you sure you want to remove plugin '{$pluginName}'? This action cannot be undone.", false)) {
                            $io->info('Operation cancelled');

                            return Command::SUCCESS;
                        }
                    }

                    $io->info("Removing plugin '{$pluginName}'...");
                    $result = $this->remotePluginInstaller->removePlugin($pluginName, $output);

                    break;

                case 'change-reference':
                    if (!$pluginName || !$reference) {
                        $io->error('Plugin name and reference are required for change-reference action');

                        return Command::FAILURE;
                    }

                    $io->info("Changing reference for plugin '{$pluginName}' to '{$reference}' ({$referenceType})...");
                    $result = $this->remotePluginInstaller->changePluginReference($pluginName, $reference, $referenceType, $output);

                    break;

                default:
                    $io->error("Unknown action '{$action}'. Available actions: install, update, remove, change-reference");

                    return Command::FAILURE;
            }

            if ($result['success']) {
                $io->success($result['message']);

                return Command::SUCCESS;
            }
            $io->error($result['error']);

            return Command::FAILURE;
        } catch (Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
