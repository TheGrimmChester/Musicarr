<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data migration: Insert default configuration values for all configuration domains
 */
final class Version20250823062134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert current saved configuration values for album import, association, and metadata domains';
    }

    public function up(Schema $schema): void
    {
        // Insert current saved configuration values for the domains
        $this->insertDefaultConfigurations();
    }

    public function down(Schema $schema): void
    {
        // Remove the inserted configuration values
        $this->removeDefaultConfigurations();
    }

    private function insertDefaultConfigurations(): void
    {
        $now = date('Y-m-d H:i:s');

        // Album Import Configuration Domain
        $this->addSql(<<<'SQL'
            INSERT INTO configuration (`key`, value, type, description, created_at, updated_at) VALUES
            ('album_import.primary_types', '["Album","EP","Single"]', 'json', 'Primary album types to import', :created_at, :updated_at),
            ('album_import.secondary_types', '["Studio"]', 'json', 'Secondary album types to import', :created_at, :updated_at),
            ('album_import.release_statuses', '["official"]', 'json', 'Release statuses to import', :created_at, :updated_at)
        SQL, ['created_at' => $now, 'updated_at' => $now]);

        // Association Configuration Domain
        $this->addSql(<<<'SQL'
            INSERT INTO configuration (`key`, value, type, description, created_at, updated_at) VALUES
            ('association.auto_association', '1', 'boolean', 'Enable automatic track association', :created_at, :updated_at),
            ('association.min_score', '100', 'integer', 'Minimum score for track association', :created_at, :updated_at),
            ('association.exact_artist_match', '1', 'boolean', 'Require exact artist name match', :created_at, :updated_at),
            ('association.exact_album_match', '1', 'boolean', 'Require exact album title match', :created_at, :updated_at),
            ('association.exact_duration_match', '1', 'boolean', 'Require exact duration match', :created_at, :updated_at),
            ('association.exact_year_match', '1', 'boolean', 'Require exact year match', :created_at, :updated_at),
            ('association.exact_title_match', '1', 'boolean', 'Require exact track title match', :created_at, :updated_at)
        SQL, ['created_at' => $now, 'updated_at' => $now]);

        // Metadata Configuration Domain
        $this->addSql(<<<'SQL'
            INSERT INTO configuration (`key`, value, type, description, created_at, updated_at) VALUES
            ('metadata.base_dir', '/app/public/metadata', 'string', 'Base directory for metadata storage', :created_at, :updated_at),
            ('metadata.save_in_library', '1', 'boolean', 'Save metadata in library directory', :created_at, :updated_at),
            ('metadata.image_path', 'images', 'string', 'Path for metadata images', :created_at, :updated_at),
            ('metadata.library_image_path', 'library', 'string', 'Path for library images', :created_at, :updated_at)
        SQL, ['created_at' => $now, 'updated_at' => $now]);


    }

    private function removeDefaultConfigurations(): void
    {
        // Remove all configuration values for the domains
        $this->addSql("DELETE FROM configuration WHERE `key` LIKE 'album_import.%'");
        $this->addSql("DELETE FROM configuration WHERE `key` LIKE 'association.%'");
        $this->addSql("DELETE FROM configuration WHERE `key` LIKE 'metadata.%'");
    }
}
