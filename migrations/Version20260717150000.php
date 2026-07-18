<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260717150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pending_email column for secure email change flow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD pending_email VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP pending_email');
    }
}
