<?php

declare(strict_types=1);

namespace App\Common\Infrastructure\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251215114510 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__bonus_config AS SELECT id, department_id, bonus_type, bonus_value, max_years FROM bonus_config');
        $this->addSql('DROP TABLE bonus_config');
        $this->addSql('CREATE TABLE bonus_config (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, department_id INTEGER NOT NULL, bonus_type VARCHAR(255) NOT NULL, bonus_value NUMERIC(10, 2) NOT NULL, max_years INTEGER DEFAULT NULL, CONSTRAINT FK_9B8065ADAE80F5DF FOREIGN KEY (department_id) REFERENCES department (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO bonus_config (id, department_id, bonus_type, bonus_value, max_years) SELECT id, department_id, bonus_type, bonus_value, max_years FROM __temp__bonus_config');
        $this->addSql('DROP TABLE __temp__bonus_config');
        $this->addSql('CREATE INDEX IDX_9B8065ADAE80F5DF ON bonus_config (department_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9B8065AD541C100B ON bonus_config (bonus_type)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__bonus_config AS SELECT id, department_id, bonus_type, bonus_value, max_years FROM bonus_config');
        $this->addSql('DROP TABLE bonus_config');
        $this->addSql('CREATE TABLE bonus_config (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, department_id INTEGER NOT NULL, bonus_type VARCHAR(255) NOT NULL, bonus_value NUMERIC(10, 2) NOT NULL, max_years INTEGER DEFAULT NULL, CONSTRAINT FK_9B8065ADAE80F5DF FOREIGN KEY (department_id) REFERENCES department (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO bonus_config (id, department_id, bonus_type, bonus_value, max_years) SELECT id, department_id, bonus_type, bonus_value, max_years FROM __temp__bonus_config');
        $this->addSql('DROP TABLE __temp__bonus_config');
        $this->addSql('CREATE INDEX IDX_9B8065ADAE80F5DF ON bonus_config (department_id)');
    }
}
