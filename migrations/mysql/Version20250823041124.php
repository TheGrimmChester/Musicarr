<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250823041124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial project';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE album (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(255) NOT NULL,
              release_mbid VARCHAR(255) NOT NULL,
              release_group_mbid VARCHAR(255) DEFAULT NULL,
              disambiguation VARCHAR(255) DEFAULT NULL,
              overview LONGTEXT DEFAULT NULL,
              release_date DATE DEFAULT NULL,
              status VARCHAR(50) DEFAULT NULL,
              path VARCHAR(500) DEFAULT NULL,
              image_url VARCHAR(500) DEFAULT NULL,
              monitored TINYINT(1) NOT NULL,
              any_release_ok TINYINT(1) NOT NULL,
              last_info_sync DATETIME DEFAULT NULL,
              last_search DATETIME DEFAULT NULL,
              album_type VARCHAR(50) DEFAULT NULL,
              secondary_types JSON DEFAULT NULL,
              downloaded TINYINT(1) NOT NULL,
              has_file TINYINT(1) NOT NULL,
              artist_id INT NOT NULL,
              INDEX IDX_39986E43B7970CF8 (artist_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE album_statistics (
              id INT AUTO_INCREMENT NOT NULL,
              total_tracks INT NOT NULL,
              downloaded_tracks INT NOT NULL,
              monitored_tracks INT NOT NULL,
              tracks_with_files INT NOT NULL,
              total_duration INT DEFAULT NULL,
              average_track_duration INT DEFAULT NULL,
              completion_percentage NUMERIC(5, 2) DEFAULT NULL,
              updated_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              album_id INT NOT NULL,
              UNIQUE INDEX UNIQ_EAD398071137ABCF (album_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE artist (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              mbid VARCHAR(255) DEFAULT NULL,
              spotify_id VARCHAR(255) DEFAULT NULL,
              disambiguation VARCHAR(255) DEFAULT NULL,
              overview LONGTEXT DEFAULT NULL,
              country VARCHAR(255) DEFAULT NULL,
              type VARCHAR(255) DEFAULT NULL,
              status VARCHAR(255) DEFAULT NULL,
              ended DATETIME DEFAULT NULL,
              started DATETIME DEFAULT NULL,
              artist_folder_path VARCHAR(500) DEFAULT NULL,
              image_url VARCHAR(500) DEFAULT NULL,
              monitored TINYINT(1) NOT NULL,
              monitor_new_items TINYINT(1) NOT NULL,
              last_info_sync DATETIME NOT NULL,
              last_search DATETIME DEFAULT NULL,
              UNIQUE INDEX UNIQ_15996875DBB9A23 (mbid),
              UNIQUE INDEX UNIQ_1599687A905FC5C (spotify_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE artist_statistics (
              id INT AUTO_INCREMENT NOT NULL,
              total_albums INT NOT NULL,
              total_singles INT NOT NULL,
              total_tracks INT NOT NULL,
              downloaded_albums INT NOT NULL,
              downloaded_singles INT NOT NULL,
              downloaded_tracks INT NOT NULL,
              monitored_albums INT NOT NULL,
              monitored_singles INT NOT NULL,
              updated_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              artist_id INT NOT NULL,
              UNIQUE INDEX UNIQ_1AC545BB7970CF8 (artist_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE configuration (
              id INT AUTO_INCREMENT NOT NULL,
              `key` VARCHAR(255) NOT NULL,
              value LONGTEXT DEFAULT NULL,
              type VARCHAR(255) DEFAULT NULL,
              description LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              UNIQUE INDEX UNIQ_A5E2A5D74E645A7E (`key`),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE library (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              path VARCHAR(255) NOT NULL,
              enabled TINYINT(1) NOT NULL,
              scan_automatically TINYINT(1) NOT NULL,
              scan_interval INT NOT NULL,
              last_scan DATETIME NOT NULL,
              quality_profile VARCHAR(255) DEFAULT NULL,
              metadata_profile VARCHAR(255) DEFAULT NULL,
              monitor_new_items TINYINT(1) NOT NULL,
              monitor_existing_items TINYINT(1) NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE library_statistics (
              id INT AUTO_INCREMENT NOT NULL,
              total_artists INT NOT NULL,
              total_albums INT NOT NULL,
              total_tracks INT NOT NULL,
              downloaded_albums INT NOT NULL,
              downloaded_tracks INT NOT NULL,
              total_singles INT NOT NULL,
              downloaded_singles INT NOT NULL,
              updated_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              library_id INT NOT NULL,
              UNIQUE INDEX UNIQ_6C7B85EFE2541D7 (library_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE medium (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(255) DEFAULT NULL,
              disc_id VARCHAR(255) DEFAULT NULL,
              mbid VARCHAR(255) DEFAULT NULL,
              position INT NOT NULL,
              format VARCHAR(100) DEFAULT NULL,
              track_count INT NOT NULL,
              album_id INT NOT NULL,
              INDEX IDX_C67345B71137ABCF (album_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE plugin (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              version VARCHAR(255) NOT NULL,
              author VARCHAR(255) DEFAULT NULL,
              description LONGTEXT DEFAULT NULL,
              installed TINYINT(1) DEFAULT 0 NOT NULL,
              enabled TINYINT(1) DEFAULT 0 NOT NULL,
              settings JSON DEFAULT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE task (
              id INT AUTO_INCREMENT NOT NULL,
              type VARCHAR(50) NOT NULL,
              status VARCHAR(50) NOT NULL,
              entity_mbid VARCHAR(255) DEFAULT NULL,
              entity_id INT DEFAULT NULL,
              entity_name VARCHAR(255) DEFAULT NULL,
              metadata JSON DEFAULT NULL,
              error_message LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              started_at DATETIME DEFAULT NULL,
              completed_at DATETIME DEFAULT NULL,
              priority INT DEFAULT NULL,
              unique_key VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE track (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(255) NOT NULL,
              mbid VARCHAR(255) DEFAULT NULL,
              disambiguation VARCHAR(255) DEFAULT NULL,
              overview LONGTEXT DEFAULT NULL,
              track_number VARCHAR(10) NOT NULL,
              medium_number INT NOT NULL,
              duration INT DEFAULT NULL,
              path VARCHAR(255) DEFAULT NULL,
              monitored TINYINT(1) NOT NULL,
              downloaded TINYINT(1) NOT NULL,
              has_file TINYINT(1) NOT NULL,
              last_info_sync DATETIME NOT NULL,
              last_search DATETIME DEFAULT NULL,
              artist_name VARCHAR(255) DEFAULT NULL,
              album_title VARCHAR(255) DEFAULT NULL,
              album_id INT NOT NULL,
              medium_id INT DEFAULT NULL,
              UNIQUE INDEX UNIQ_D6E3F8A65DBB9A23 (mbid),
              INDEX IDX_D6E3F8A61137ABCF (album_id),
              INDEX IDX_D6E3F8A6E252B6A5 (medium_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE track_file (
              id INT AUTO_INCREMENT NOT NULL,
              file_path VARCHAR(255) NOT NULL,
              file_size INT NOT NULL,
              quality VARCHAR(255) DEFAULT NULL,
              format VARCHAR(255) DEFAULT NULL,
              duration INT NOT NULL,
              added_at DATETIME NOT NULL,
              lyrics_path VARCHAR(255) DEFAULT NULL,
              need_rename TINYINT(1) NOT NULL,
              track_id INT NOT NULL,
              UNIQUE INDEX UNIQ_11C91E1582A8E361 (file_path),
              INDEX IDX_11C91E155ED23C43 (track_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE unmatched_track (
              id INT AUTO_INCREMENT NOT NULL,
              file_path VARCHAR(255) NOT NULL,
              file_name VARCHAR(255) DEFAULT NULL,
              title VARCHAR(255) DEFAULT NULL,
              artist VARCHAR(255) DEFAULT NULL,
              album VARCHAR(255) DEFAULT NULL,
              track_number VARCHAR(10) DEFAULT NULL,
              year INT DEFAULT NULL,
              extension VARCHAR(10) DEFAULT NULL,
              duration INT DEFAULT NULL,
              file_size INT DEFAULT NULL,
              discovered_at DATETIME NOT NULL,
              last_attempted_match DATETIME DEFAULT NULL,
              is_matched TINYINT(1) NOT NULL,
              lyrics_filepath VARCHAR(255) DEFAULT NULL,
              library_id INT NOT NULL,
              UNIQUE INDEX UNIQ_C61DE92082A8E361 (file_path),
              INDEX IDX_C61DE920FE2541D7 (library_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              album
            ADD
              CONSTRAINT FK_39986E43B7970CF8 FOREIGN KEY (artist_id) REFERENCES artist (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              album_statistics
            ADD
              CONSTRAINT FK_EAD398071137ABCF FOREIGN KEY (album_id) REFERENCES album (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              artist_statistics
            ADD
              CONSTRAINT FK_1AC545BB7970CF8 FOREIGN KEY (artist_id) REFERENCES artist (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              library_statistics
            ADD
              CONSTRAINT FK_6C7B85EFE2541D7 FOREIGN KEY (library_id) REFERENCES library (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              medium
            ADD
              CONSTRAINT FK_C67345B71137ABCF FOREIGN KEY (album_id) REFERENCES album (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              track
            ADD
              CONSTRAINT FK_D6E3F8A61137ABCF FOREIGN KEY (album_id) REFERENCES album (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              track
            ADD
              CONSTRAINT FK_D6E3F8A6E252B6A5 FOREIGN KEY (medium_id) REFERENCES medium (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              track_file
            ADD
              CONSTRAINT FK_11C91E155ED23C43 FOREIGN KEY (track_id) REFERENCES track (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              unmatched_track
            ADD
              CONSTRAINT FK_C61DE920FE2541D7 FOREIGN KEY (library_id) REFERENCES library (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE album DROP FOREIGN KEY FK_39986E43B7970CF8');
        $this->addSql('ALTER TABLE album_statistics DROP FOREIGN KEY FK_EAD398071137ABCF');
        $this->addSql('ALTER TABLE artist_statistics DROP FOREIGN KEY FK_1AC545BB7970CF8');
        $this->addSql('ALTER TABLE library_statistics DROP FOREIGN KEY FK_6C7B85EFE2541D7');
        $this->addSql('ALTER TABLE medium DROP FOREIGN KEY FK_C67345B71137ABCF');
        $this->addSql('ALTER TABLE track DROP FOREIGN KEY FK_D6E3F8A61137ABCF');
        $this->addSql('ALTER TABLE track DROP FOREIGN KEY FK_D6E3F8A6E252B6A5');
        $this->addSql('ALTER TABLE track_file DROP FOREIGN KEY FK_11C91E155ED23C43');
        $this->addSql('ALTER TABLE unmatched_track DROP FOREIGN KEY FK_C61DE920FE2541D7');
        $this->addSql('DROP TABLE album');
        $this->addSql('DROP TABLE album_statistics');
        $this->addSql('DROP TABLE artist');
        $this->addSql('DROP TABLE artist_statistics');
        $this->addSql('DROP TABLE configuration');
        $this->addSql('DROP TABLE library');
        $this->addSql('DROP TABLE library_statistics');
        $this->addSql('DROP TABLE medium');
        $this->addSql('DROP TABLE plugin');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE track');
        $this->addSql('DROP TABLE track_file');
        $this->addSql('DROP TABLE unmatched_track');
    }
}
