<?php

namespace App\Service;

use App\Entity\BoardGame;

/**
 * Parse un CSV de catalogue ludothèque et produit des entités BoardGame prêtes à persister.
 *
 * Colonnes attendues (séparateur ; ou ,) :
 * Titre*; Catégorie; Joueurs max; Durée (min); État; Notes
 *
 * Le titre est obligatoire. L'état doit être l'une des valeurs du formulaire admin.
 * Statut / emprunteur / image ne sont pas importés (jeux créés comme disponibles).
 */
final class BoardGameCsvImporter
{
    public const ALLOWED_CONDITIONS = ['Neuf', 'Bon état', 'Usé', 'Abîmé'];

    private const MAX_ROWS = 500;

    /** @var array<string, string> */
    private const HEADER_ALIASES = [
        'titre' => 'title',
        'title' => 'title',
        'categorie' => 'category',
        'catégorie' => 'category',
        'category' => 'category',
        'joueurs max' => 'maxPlayers',
        'joueurs_max' => 'maxPlayers',
        'max players' => 'maxPlayers',
        'max_players' => 'maxPlayers',
        'duree (min)' => 'durationMinutes',
        'durée (min)' => 'durationMinutes',
        'duree' => 'durationMinutes',
        'durée' => 'durationMinutes',
        'duration' => 'durationMinutes',
        'duration_minutes' => 'durationMinutes',
        'etat' => 'condition',
        'état' => 'condition',
        'condition' => 'condition',
        'notes' => 'notes',
        'note' => 'notes',
    ];

    /**
     * @return array{created: list<BoardGame>, errors: list<string>, skipped: int}
     */
    public function importFromPath(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return ['created' => [], 'errors' => ['Impossible de lire le fichier CSV.'], 'skipped' => 0];
        }

        try {
            return $this->importFromHandle($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     *
     * @return array{created: list<BoardGame>, errors: list<string>, skipped: int}
     */
    public function importFromHandle($handle): array
    {
        $created = [];
        $errors = [];
        $skipped = 0;

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            return ['created' => [], 'errors' => ['Le fichier CSV est vide.'], 'skipped' => 0];
        }

        $firstLine = $this->stripBom($firstLine);
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $headerCells = str_getcsv($firstLine, $delimiter, '"', '');
        $columnMap = $this->mapHeaders($headerCells);
        if (!isset($columnMap['title'])) {
            return [
                'created' => [],
                'errors' => ['Colonne « Titre » introuvable. En-têtes attendus : Titre;Catégorie;Joueurs max;Durée (min);État;Notes'],
                'skipped' => 0,
            ];
        }

        $rowNumber = 1;
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            ++$rowNumber;

            if ($rowNumber - 1 > self::MAX_ROWS) {
                $errors[] = sprintf('Import limité à %d lignes. Les lignes suivantes ont été ignorées.', self::MAX_ROWS);
                break;
            }

            if ($this->isEmptyRow($row)) {
                ++$skipped;
                continue;
            }

            $data = $this->extractRow($row, $columnMap);
            $title = trim((string) ($data['title'] ?? ''));
            if ($title === '') {
                $errors[] = sprintf('Ligne %d : titre manquant.', $rowNumber);
                ++$skipped;
                continue;
            }

            if (mb_strlen($title) > 255) {
                $errors[] = sprintf('Ligne %d : titre trop long (max 255).', $rowNumber);
                ++$skipped;
                continue;
            }

            $condition = trim((string) ($data['condition'] ?? ''));
            if ($condition !== '' && !\in_array($condition, self::ALLOWED_CONDITIONS, true)) {
                $errors[] = sprintf(
                    'Ligne %d : état « %s » invalide (valeurs : %s).',
                    $rowNumber,
                    $condition,
                    implode(', ', self::ALLOWED_CONDITIONS)
                );
                ++$skipped;
                continue;
            }

            $maxPlayers = $this->parsePositiveInt($data['maxPlayers'] ?? null, $rowNumber, 'Joueurs max', $errors);
            $duration = $this->parsePositiveInt($data['durationMinutes'] ?? null, $rowNumber, 'Durée', $errors);
            if ($maxPlayers === false || $duration === false) {
                ++$skipped;
                continue;
            }

            $category = trim((string) ($data['category'] ?? ''));
            if (mb_strlen($category) > 255) {
                $errors[] = sprintf('Ligne %d : catégorie trop longue (max 255).', $rowNumber);
                ++$skipped;
                continue;
            }

            $notes = trim((string) ($data['notes'] ?? ''));
            $notes = $notes === '' ? null : $notes;

            $boardGame = new BoardGame();
            $boardGame->setTitle($title);
            $boardGame->setCategory($category === '' ? null : $category);
            $boardGame->setMaxPlayers($maxPlayers);
            $boardGame->setDurationMinutes($duration);
            $boardGame->setCondition($condition === '' ? null : $condition);
            $boardGame->setNotes($notes);
            $boardGame->setStatus(BoardGame::STATUS_AVAILABLE);

            $created[] = $boardGame;
        }

        return ['created' => $created, 'errors' => $errors, 'skipped' => $skipped];
    }

    /**
     * @param list<string|null> $headerCells
     *
     * @return array<string, int>
     */
    private function mapHeaders(array $headerCells): array
    {
        $map = [];
        foreach ($headerCells as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            if ($normalized === '' || !isset(self::HEADER_ALIASES[$normalized])) {
                continue;
            }
            $map[self::HEADER_ALIASES[$normalized]] = $index;
        }

        return $map;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = mb_strtolower($header);
        $replacements = ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ù' => 'u', 'ô' => 'o', 'î' => 'i', 'ç' => 'c'];
        $header = strtr($header, $replacements);

        return preg_replace('/\s+/', ' ', $header) ?? $header;
    }

    /**
     * @param list<string|null>     $row
     * @param array<string, int>    $columnMap
     *
     * @return array<string, string|null>
     */
    private function extractRow(array $row, array $columnMap): array
    {
        $data = [];
        foreach ($columnMap as $field => $index) {
            $data[$field] = $row[$index] ?? null;
        }

        return $data;
    }

    /**
     * @param list<string|null> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stripBom(string $line): string
    {
        if (str_starts_with($line, "\xEF\xBB\xBF")) {
            return substr($line, 3);
        }

        return $line;
    }

    /**
     * @param list<string> $errors
     *
     * @return int|null|false null si vide, false si invalide
     */
    private function parsePositiveInt(mixed $raw, int $rowNumber, string $label, array &$errors): int|null|false
    {
        $value = trim((string) ($raw ?? ''));
        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value) || (int) $value < 1) {
            $errors[] = sprintf('Ligne %d : « %s » doit être un entier positif.', $rowNumber, $label);

            return false;
        }

        return (int) $value;
    }
}
