<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260717140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Token expiry columns and wider email_verification_token for email-change payloads';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` CHANGE email_verification_token email_verification_token VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD email_verification_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE newsletter_subscriber ADD token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE newsletter_subscriber DROP token_expires_at');
        $this->addSql('ALTER TABLE `user` DROP email_verification_expires_at');
        $this->addSql('ALTER TABLE `user` CHANGE email_verification_token email_verification_token VARCHAR(64) DEFAULT NULL');
    }
}
