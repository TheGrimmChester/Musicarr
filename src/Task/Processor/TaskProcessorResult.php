<?php

declare(strict_types=1);

namespace App\Task\Processor;

class TaskProcessorResult
{
    public function __construct(
        private bool $success,
        private ?string $message = null,
        private ?string $errorMessage = null,
        private ?array $metadata = null
    ) {
    }

    public static function success(?string $message = null, ?array $metadata = null): self
    {
        return new self(true, $message, null, $metadata);
    }

    public static function failure(string $errorMessage, ?array $metadata = null): self
    {
        return new self(false, null, $errorMessage, $metadata);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        if (null === $this->metadata) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $value;

        return $this;
    }
}
