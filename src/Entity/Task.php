<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TaskRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
class Task
{
    public const TYPE_ADD_ARTIST = 'add_artist';
    public const TYPE_ADD_ALBUM = 'add_album';
    public const TYPE_UPDATE_ARTIST = 'update_artist';
    public const TYPE_UPDATE_ALBUM = 'update_album';
    public const TYPE_SYNC_ARTIST = 'sync_artist';
    public const TYPE_SYNC_ALBUM = 'sync_album';
    public const TYPE_SYNC_ARTIST_ALBUMS = 'sync_artist_albums';
    public const TYPE_SYNC_ALL_ARTISTS = 'sync_all_artists';
    public const TYPE_SYNC_SINGLE_ALBUM = 'sync_single_album';
    public const TYPE_DOWNLOAD_ALBUM = 'download_album';
    public const TYPE_DOWNLOAD_SONG = 'download_song';
    public const TYPE_ASSOCIATE_ARTIST = 'associate_artist';
    public const TYPE_ASSOCIATE_ALBUM = 'associate_album';
    public const TYPE_AUTO_ASSOCIATE_TRACK = 'auto_associate_track';
    public const TYPE_AUTO_ASSOCIATE_TRACKS = 'auto_associate_tracks';
    public const TYPE_SCAN_LIBRARY = 'scan_library';
    public const TYPE_PROCESS_LIBRARY_FILE = 'process_library_file';
    public const TYPE_ANALYZE_AUDIO_QUALITY = 'analyze_audio_quality';
    public const TYPE_ANALYZE_EXISTING_TRACKS = 'analyze_existing_tracks';
    public const TYPE_RENAME_FILES = 'rename_files';
    public const TYPE_FIX_TRACK_STATUSES = 'fix_track_statuses';
    public const TYPE_FIX_MATCHED_TRACKS_WITHOUT_FILES = 'fix_matched_tracks_without_files';
    public const TYPE_SYNC_TRACK_STATUSES = 'sync_track_statuses';
    public const TYPE_UPDATE_ALBUM_STATUSES = 'update_album_statuses';
    public const TYPE_PLUGIN_INSTALL = 'plugin_install';
    public const TYPE_PLUGIN_UNINSTALL = 'plugin_uninstall';
    public const TYPE_PLUGIN_ENABLE = 'plugin_enable';
    public const TYPE_PLUGIN_DISABLE = 'plugin_disable';
    public const TYPE_PLUGIN_UPGRADE = 'plugin_upgrade';
    public const TYPE_REMOTE_PLUGIN_INSTALL = 'remote_plugin_install';
    public const TYPE_PLUGIN_REFERENCE_CHANGE = 'plugin_reference_change';
    public const TYPE_CACHE_CLEAR = 'cache_clear';
    public const TYPE_NPM_BUILD = 'npm_build';

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 1;
    public const PRIORITY_NORMAL = 5;
    public const PRIORITY_HIGH = 10;
    public const PRIORITY_URGENT = 20;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 50)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityMbid = null;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityName = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    /** @phpstan-ignore-next-line */
    private ?array $metadata = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $completedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $priority = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uniqueKey = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->status = self::STATUS_PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        if (self::STATUS_RUNNING === $status && !$this->startedAt) {
            $this->startedAt = new DateTime();
        }

        if (\in_array($status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true) && !$this->completedAt) {
            $this->completedAt = new DateTime();
        }

        return $this;
    }

    public function getEntityMbid(): ?string
    {
        return $this->entityMbid;
    }

    public function setEntityMbid(?string $entityMbid): static
    {
        $this->entityMbid = $entityMbid;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    public function setEntityName(?string $entityName): static
    {
        $this->entityName = $entityName;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

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

    public function getStartedAt(): ?DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(?DateTimeInterface $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getUniqueKey(): ?string
    {
        return $this->uniqueKey;
    }

    public function setUniqueKey(?string $uniqueKey): static
    {
        $this->uniqueKey = $uniqueKey;

        return $this;
    }

    /**
     * Generate a unique key based on task type and entity identifiers.
     */
    public function generateUniqueKey(): string
    {
        $parts = [$this->type];

        if ($this->entityMbid) {
            $parts[] = $this->entityMbid;
        } elseif ($this->entityId) {
            $parts[] = 'id:' . $this->entityId;
        } elseif ($this->entityName) {
            $parts[] = 'name:' . $this->entityName;
        }

        $this->uniqueKey = implode(':', $parts);

        return $this->uniqueKey;
    }

    /**
     * Check if the task is in a final state.
     */
    public function isFinalized(): bool
    {
        return \in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true);
    }

    /**
     * Check if the task is currently active.
     */
    public function isActive(): bool
    {
        return \in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true);
    }

    /**
     * Get the duration of the task execution.
     */
    public function getDuration(): ?int
    {
        if (!$this->startedAt) {
            return null;
        }

        $endTime = $this->completedAt ?? new DateTime();

        return $endTime->getTimestamp() - $this->startedAt->getTimestamp();
    }

    /**
     * Get all possible task types.
     *
     * @return array<int, string>
     */
    public static function getTaskTypes(): array
    {
        return [
            self::TYPE_ADD_ARTIST,
            self::TYPE_ADD_ALBUM,
            self::TYPE_UPDATE_ARTIST,
            self::TYPE_UPDATE_ALBUM,
            self::TYPE_SYNC_ARTIST,
            self::TYPE_SYNC_ALBUM,
            self::TYPE_SYNC_ARTIST_ALBUMS,
            self::TYPE_SYNC_ALL_ARTISTS,
            self::TYPE_SYNC_SINGLE_ALBUM,
            self::TYPE_DOWNLOAD_ALBUM,
            self::TYPE_DOWNLOAD_SONG,
            self::TYPE_ASSOCIATE_ARTIST,
            self::TYPE_ASSOCIATE_ALBUM,
            self::TYPE_AUTO_ASSOCIATE_TRACK,
            self::TYPE_AUTO_ASSOCIATE_TRACKS,
            self::TYPE_SCAN_LIBRARY,
            self::TYPE_PROCESS_LIBRARY_FILE,
            self::TYPE_ANALYZE_AUDIO_QUALITY,
            self::TYPE_ANALYZE_EXISTING_TRACKS,
            self::TYPE_RENAME_FILES,
            self::TYPE_FIX_TRACK_STATUSES,
            self::TYPE_FIX_MATCHED_TRACKS_WITHOUT_FILES,
            self::TYPE_SYNC_TRACK_STATUSES,
            self::TYPE_UPDATE_ALBUM_STATUSES,
            self::TYPE_PLUGIN_INSTALL,
            self::TYPE_PLUGIN_UNINSTALL,
            self::TYPE_PLUGIN_ENABLE,
            self::TYPE_PLUGIN_DISABLE,
            self::TYPE_PLUGIN_UPGRADE,
            self::TYPE_REMOTE_PLUGIN_INSTALL,
            self::TYPE_PLUGIN_REFERENCE_CHANGE,
            self::TYPE_CACHE_CLEAR,
            self::TYPE_NPM_BUILD,
        ];
    }

    /**
     * Get all possible task statuses.
     *
     * @return array<int, string>
     */
    public static function getTaskStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RUNNING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
    }
}
