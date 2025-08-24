<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Process\Process;

#[AutoconfigureTag('app.task_processor')]
class NpmBuildTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $this->logger->info('Starting npm build task');

            // Get the project root directory
            $projectRoot = \dirname(__DIR__, 3); // Go up from src/Task/Processor to project root

            // Check if package.json exists
            $packageJsonPath = $projectRoot . '/package.json';
            if (!file_exists($packageJsonPath)) {
                return TaskProcessorResult::failure('package.json not found in project root');
            }

            // Check if node_modules exists
            $nodeModulesPath = $projectRoot . '/node_modules';
            if (!is_dir($nodeModulesPath)) {
                return TaskProcessorResult::failure('node_modules directory not found. Please run npm install first.');
            }

            // Create the npm run build process
            $process = new Process(['npm', 'run', 'build'], $projectRoot);
            $process->setTimeout(300); // 5 minutes timeout
            $process->setIdleTimeout(60); // 1 minute idle timeout

            $this->logger->info('Running npm run build command', [
                'workingDirectory' => $projectRoot,
                'command' => $process->getCommand(),
            ]);

            // Run the process
            $process->run();

            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                $this->logger->error('npm build command failed', [
                    'exitCode' => $process->getExitCode(),
                    'errorOutput' => $errorOutput,
                    'output' => $process->getOutput(),
                ]);

                return TaskProcessorResult::failure(
                    'npm build command failed: ' . $errorOutput,
                    [
                        'exitCode' => $process->getExitCode(),
                        'errorOutput' => $errorOutput,
                        'output' => $process->getOutput(),
                    ]
                );
            }

            $output = $process->getOutput();
            $this->logger->info('Successfully completed npm build', [
                'output' => $output,
            ]);

            return TaskProcessorResult::success(
                'Successfully completed npm build',
                ['output' => $output]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to run npm build', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return TaskProcessorResult::failure('Failed to run npm build: ' . $e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_NPM_BUILD];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_NPM_BUILD === $task->getType();
    }
}
