<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConfigurationRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfigurationRepository::class)]
#[ORM\Table(name: 'configuration')]
class Configuration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: '`key`', length: 255, unique: true)]
    private ?string $key = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $value = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = 'string';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;
        $this->updatedAt = new DateTime();

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get the parsed value based on type.
     */
    public function getParsedValue(): mixed
    {
        if (null === $this->value) {
            return null;
        }

        return match ($this->type) {
            'boolean' => filter_var($this->value, \FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'array', 'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Set value with automatic type conversion.
     */
    public function setParsedValue(mixed $value): static
    {
        if (null === $value) {
            $this->value = null;
            $this->type = 'string';
        } elseif (\is_array($value) || \is_object($value)) {
            $jsonValue = json_encode($value);
            $this->value = false !== $jsonValue ? $jsonValue : '[]';
            $this->type = 'json';
        } elseif (\is_bool($value)) {
            $this->value = $value ? '1' : '0';
            $this->type = 'boolean';
        } elseif (\is_int($value)) {
            $this->value = (string) $value;
            $this->type = 'integer';
        } elseif (\is_float($value)) {
            $this->value = (string) $value;
            $this->type = 'float';
        } else {
            $this->value = (string) $value;
            $this->type = 'string';
        }

        $this->updatedAt = new DateTime();

        return $this;
    }
}
