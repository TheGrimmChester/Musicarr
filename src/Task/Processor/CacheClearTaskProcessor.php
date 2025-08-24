<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpKernel\KernelInterface;

#[AutoconfigureTag('app.task_processor')]
class CacheClearTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private KernelInterface $kernel,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $this->logger->info('Starting Symfony cache clear task');

            // Get the application from the kernel
            $application = new Application($this->kernel);
            $application->setAutoExit(false);

            // Create input for cache:clear command
            $input = new ArrayInput([
                'command' => 'cache:clear',
                '--no-warmup' => true, // Don't warm up cache after clearing
            ]);

            // Capture output
            $output = new BufferedOutput();

            // Run the cache:clear command
            $exitCode = $application->run($input, $output);

            if (0 !== $exitCode) {
                $errorOutput = $output->fetch();
                $this->logger->error('Cache clear command failed', [
                    'exitCode' => $exitCode,
                    'output' => $errorOutput,
                ]);

                return TaskProcessorResult::failure(
                    'Cache clear command failed with exit code: ' . $exitCode,
                    ['exitCode' => $exitCode, 'output' => $errorOutput]
                );
            }

            $outputContent = $output->fetch();
            $this->logger->info('Successfully cleared Symfony cache', [
                'output' => $outputContent,
            ]);

            return TaskProcessorResult::success(
                'Successfully cleared Symfony cache',
                ['output' => $outputContent]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to clear Symfony cache', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return TaskProcessorResult::failure('Failed to clear Symfony cache: ' . $e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_CACHE_CLEAR];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_CACHE_CLEAR === $task->getType();
    }
}
