<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719093531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE board_game (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, category VARCHAR(255) DEFAULT NULL, max_players INT DEFAULT NULL, duration_minutes INT DEFAULT NULL, `condition` VARCHAR(32) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, status VARCHAR(16) DEFAULT \'available\' NOT NULL, requested_at DATETIME DEFAULT NULL, loaned_at DATETIME DEFAULT NULL, return_due_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, borrower_id INT DEFAULT NULL, INDEX IDX_F9BD68AF11CE312B (borrower_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE board_game ADD CONSTRAINT FK_F9BD68AF11CE312B FOREIGN KEY (borrower_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE newsletter_subscriber CHANGE token_expires_at token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE email_verification_expires_at email_verification_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE board_game DROP FOREIGN KEY FK_F9BD68AF11CE312B');
        $this->addSql('DROP TABLE board_game');
        $this->addSql('ALTER TABLE newsletter_subscriber CHANGE token_expires_at token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE `user` CHANGE email_verification_expires_at email_verification_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
