<?php

namespace App\Tests\Unit;

use App\Entity\BoardGame;
use App\Service\BoardGameCsvImporter;
use PHPUnit\Framework\TestCase;

class BoardGameCsvImporterTest extends TestCase
{
    private BoardGameCsvImporter $importer;

    protected function setUp(): void
    {
        $this->importer = new BoardGameCsvImporter();
    }

    public function testImportCreatesBoardGamesFromCsv(): void
    {
        $csv = <<<'CSV'
Titre;Catégorie;Joueurs max;Durée (min);État;Notes
Catan;Stratégie;4;90;Bon état;Classique
Azul;Familial;4;45;Neuf;
CSV;

        $result = $this->importString($csv);

        $this->assertCount(2, $result['created']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(0, $result['skipped']);

        $catan = $result['created'][0];
        $this->assertSame('Catan', $catan->getTitle());
        $this->assertSame('Stratégie', $catan->getCategory());
        $this->assertSame(4, $catan->getMaxPlayers());
        $this->assertSame(90, $catan->getDurationMinutes());
        $this->assertSame('Bon état', $catan->getCondition());
        $this->assertSame('Classique', $catan->getNotes());
        $this->assertSame(BoardGame::STATUS_AVAILABLE, $catan->getStatus());

        $azul = $result['created'][1];
        $this->assertSame('Azul', $azul->getTitle());
        $this->assertNull($azul->getNotes());
    }

    public function testImportRejectsMissingTitleHeader(): void
    {
        $csv = "Catégorie;Notes\nStratégie;Test\n";
        $result = $this->importString($csv);

        $this->assertSame([], $result['created']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Titre', $result['errors'][0]);
    }

    public function testImportSkipsInvalidConditionAndEmptyTitle(): void
    {
        $csv = <<<'CSV'
Titre;État
;Neuf
BadGame;Cassé
GoodGame;Usé
CSV;

        $result = $this->importString($csv);

        $this->assertCount(1, $result['created']);
        $this->assertSame('GoodGame', $result['created'][0]->getTitle());
        $this->assertSame('Usé', $result['created'][0]->getCondition());
        $this->assertSame(2, $result['skipped']);
        $this->assertCount(2, $result['errors']);
    }

    public function testImportSupportsCommaDelimiterAndBom(): void
    {
        $csv = "\xEF\xBB\xBFTitle,Category,max_players\nDixit,Ambiance,6\n";
        $result = $this->importString($csv);

        $this->assertCount(1, $result['created']);
        $this->assertSame('Dixit', $result['created'][0]->getTitle());
        $this->assertSame('Ambiance', $result['created'][0]->getCategory());
        $this->assertSame(6, $result['created'][0]->getMaxPlayers());
    }

    public function testImportRejectsInvalidIntegers(): void
    {
        $csv = "Titre;Joueurs max\nCatan;abc\n";
        $result = $this->importString($csv);

        $this->assertSame([], $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString('entier positif', $result['errors'][0]);
    }

    /**
     * @return array{created: list<BoardGame>, errors: list<string>, skipped: int}
     */
    private function importString(string $csv): array
    {
        $handle = fopen('php://memory', 'r+b');
        self::assertNotFalse($handle);
        fwrite($handle, $csv);
        rewind($handle);

        $result = $this->importer->importFromHandle($handle);
        fclose($handle);

        return $result;
    }
}
