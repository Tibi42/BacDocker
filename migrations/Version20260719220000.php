<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RGPD : défaut newsletter opt-in = false ; unicité inscription activité+email.
 */
final class Version20260719220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Default newsletter_opt_in=false ; UNIQUE (activity_id, participant_email) on inscription';
    }

    public function up(Schema $schema): void
    {
        // Ne pas forcer les comptes existants à false — seulement le défaut pour les nouveaux.
        $this->addSql('ALTER TABLE `user` CHANGE newsletter_opt_in newsletter_opt_in TINYINT(1) DEFAULT 0 NOT NULL');

        // Nettoyer d’éventuels doublons avant la contrainte (garde la plus ancienne).
        $this->addSql(<<<'SQL'
            DELETE i1 FROM inscription i1
            INNER JOIN inscription i2
                ON i1.activity_id = i2.activity_id
                AND i1.participant_email = i2.participant_email
                AND i1.id > i2.id
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_inscription_activity_email ON inscription (activity_id, participant_email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_inscription_activity_email ON inscription');
        $this->addSql('ALTER TABLE `user` CHANGE newsletter_opt_in newsletter_opt_in TINYINT(1) DEFAULT 1 NOT NULL');
    }
}
