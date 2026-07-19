<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renomme la colonne réservée MySQL `condition` en `item_condition`.
 */
final class Version20260719132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme board_game.condition en item_condition (mot réservé MySQL)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE board_game CHANGE `condition` item_condition VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE board_game CHANGE item_condition `condition` VARCHAR(32) DEFAULT NULL');
    }
}
