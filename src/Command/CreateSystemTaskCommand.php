<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Task;
use App\Task\TaskFactory;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-system-task',
    description: 'Create a system task (cache clear, npm build, etc.)',
)]
class CreateSystemTaskCommand extends Command
{
    public function __construct(
        private TaskFactory $taskFactory
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Task type (cache_clear, npm_build)', 'cache_clear')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'Task priority (0-20)', Task::PRIORITY_NORMAL)
            ->addOption('metadata', 'm', InputOption::VALUE_OPTIONAL, 'Additional metadata as JSON string', '{}');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = $input->getOption('type');
        $priority = (int) $input->getOption('priority');
        $metadataJson = $input->getOption('metadata');

        // Validate task type
        $validTypes = [Task::TYPE_CACHE_CLEAR, Task::TYPE_NPM_BUILD];
        if (!\in_array($type, $validTypes, true)) {
            $io->error(\sprintf('Invalid task type. Must be one of: %s', implode(', ', $validTypes)));

            return Command::FAILURE;
        }

        // Parse metadata
        $metadata = [];
        if ('{}' !== $metadataJson) {
            $metadata = json_decode($metadataJson, true);
            if (\JSON_ERROR_NONE !== json_last_error()) {
                $io->error('Invalid JSON metadata: ' . json_last_error_msg());

                return Command::FAILURE;
            }
        }

        try {
            // Create the task based on type
            switch ($type) {
                case Task::TYPE_CACHE_CLEAR:
                    $task = $this->taskFactory->createCacheClearTask($metadata, $priority);
                    break;
                case Task::TYPE_NPM_BUILD:
                    $task = $this->taskFactory->createNpmBuildTask($metadata, $priority);
                    break;
                default:
                    throw new InvalidArgumentException('Unsupported task type');
            }

            $io->success(\sprintf(
                'Created %s task with ID %d and priority %d',
                $type,
                $task->getId(),
                $priority
            ));

            $io->table(
                ['Property', 'Value'],
                [
                    ['Task ID', $task->getId()],
                    ['Type', $task->getType()],
                    ['Status', $task->getStatus()],
                    ['Priority', $task->getPriority()],
                    ['Created At', $task->getCreatedAt()->format('Y-m-d H:i:s')],
                    ['Unique Key', $task->getUniqueKey()],
                ]
            );

            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Failed to create task: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
