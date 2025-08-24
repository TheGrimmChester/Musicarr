<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;

interface TaskProcessorInterface
{
    /**
     * Process a task and return the result.
     */
    public function process(Task $task): TaskProcessorResult;

    /**
     * Get the task types this processor supports.
     */
    public function getSupportedTaskTypes(): array;

    /**
     * Check if this processor can handle the given task.
     */
    public function supports(Task $task): bool;
}
