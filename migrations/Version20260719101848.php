<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719101848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE loan_log (id INT AUTO_INCREMENT NOT NULL, loaned_at DATETIME NOT NULL, board_game_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_9922ED61AC91F10A (board_game_id), INDEX IDX_9922ED61A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE review (id INT AUTO_INCREMENT NOT NULL, rating SMALLINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, board_game_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_794381C6AC91F10A (board_game_id), INDEX IDX_794381C6A76ED395 (user_id), UNIQUE INDEX uniq_review_board_game_user (board_game_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE loan_log ADD CONSTRAINT FK_9922ED61AC91F10A FOREIGN KEY (board_game_id) REFERENCES board_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE loan_log ADD CONSTRAINT FK_9922ED61A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6AC91F10A FOREIGN KEY (board_game_id) REFERENCES board_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE board_game ADD archived TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan_log DROP FOREIGN KEY FK_9922ED61AC91F10A');
        $this->addSql('ALTER TABLE loan_log DROP FOREIGN KEY FK_9922ED61A76ED395');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C6AC91F10A');
        $this->addSql('ALTER TABLE review DROP FOREIGN KEY FK_794381C6A76ED395');
        $this->addSql('DROP TABLE loan_log');
        $this->addSql('DROP TABLE review');
        $this->addSql('ALTER TABLE board_game DROP archived');
    }
}
