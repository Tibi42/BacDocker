<?php

namespace App\DataFixtures;

use App\Entity\BoardGame;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Catalogue de jeux de la ludothèque avec images PNG générées.
 *
 * Les fichiers image sont créés dans public/images/board-games/ au chargement
 * des fixtures (placeholders colorés avec le titre du jeu).
 */
class BoardGameFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        #[Autowire('%board_games_images_directory%')]
        private readonly string $imagesDirectory,
    ) {}

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        if (!is_dir($this->imagesDirectory)) {
            mkdir($this->imagesDirectory, 0775, true);
        }

        /** @var User|null $member */
        $member = $manager->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);
        /** @var User|null $memberB */
        $memberB = $manager->getRepository(User::class)->findOneBy(['email' => 'usera@example.com']);

        $now = new \DateTimeImmutable('now');

        $games = [
            [
                'title' => 'Catan',
                'category' => 'Stratégie',
                'maxPlayers' => 4,
                'durationMinutes' => 90,
                'condition' => 'Bon état',
                'notes' => 'Extension Seafarers disponible au local.',
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [210, 140, 50],
            ],
            [
                'title' => 'Ticket to Ride',
                'category' => 'Familial',
                'maxPlayers' => 5,
                'durationMinutes' => 60,
                'condition' => 'Neuf',
                'notes' => 'Version Europe.',
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [40, 110, 180],
            ],
            [
                'title' => '7 Wonders',
                'category' => 'Stratégie',
                'maxPlayers' => 7,
                'durationMinutes' => 45,
                'condition' => 'Bon état',
                'notes' => null,
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [180, 140, 40],
            ],
            [
                'title' => 'Azul',
                'category' => 'Familial',
                'maxPlayers' => 4,
                'durationMinutes' => 45,
                'condition' => 'Neuf',
                'notes' => 'Très demandé en soirée découverte.',
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [60, 140, 200],
            ],
            [
                'title' => 'Wingspan',
                'category' => 'Stratégie',
                'maxPlayers' => 5,
                'durationMinutes' => 75,
                'condition' => 'Bon état',
                'notes' => 'Extension Européenne incluse.',
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [70, 130, 90],
            ],
            [
                'title' => 'Dixit',
                'category' => 'Ambiance',
                'maxPlayers' => 8,
                'durationMinutes' => 30,
                'condition' => 'Usé',
                'notes' => 'Quelques cartes cornées.',
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [160, 90, 160],
            ],
            [
                'title' => 'Carcassonne',
                'category' => 'Familial',
                'maxPlayers' => 5,
                'durationMinutes' => 45,
                'condition' => 'Bon état',
                'notes' => null,
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [90, 150, 70],
            ],
            [
                'title' => 'Pandemic',
                'category' => 'Coopératif',
                'maxPlayers' => 4,
                'durationMinutes' => 60,
                'condition' => 'Bon état',
                'notes' => 'Idéal pour initier au coopératif.',
                'status' => BoardGame::STATUS_PENDING,
                'borrower' => $member,
                'requestedAt' => $now->modify('-1 day'),
                'color' => [180, 50, 50],
            ],
            [
                'title' => 'Codenames',
                'category' => 'Ambiance',
                'maxPlayers' => 8,
                'durationMinutes' => 30,
                'condition' => 'Neuf',
                'notes' => null,
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [40, 40, 50],
            ],
            [
                'title' => 'Terraforming Mars',
                'category' => 'Expert',
                'maxPlayers' => 5,
                'durationMinutes' => 150,
                'condition' => 'Bon état',
                'notes' => 'Durée longue — prévoir une soirée entière.',
                'status' => BoardGame::STATUS_LOANED,
                'borrower' => $member,
                'requestedAt' => $now->modify('-10 days'),
                'loanedAt' => $now->modify('-7 days'),
                'returnDueAt' => $now->modify('+7 days'),
                'color' => [200, 100, 40],
            ],
            [
                'title' => 'Splendor',
                'category' => 'Familial',
                'maxPlayers' => 4,
                'durationMinutes' => 30,
                'condition' => 'Bon état',
                'notes' => null,
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [120, 60, 160],
            ],
            [
                'title' => 'The Crew',
                'category' => 'Coopératif',
                'maxPlayers' => 5,
                'durationMinutes' => 20,
                'condition' => 'Neuf',
                'notes' => 'Jeu de cartes coopératif à missions.',
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [30, 50, 100],
            ],
            [
                'title' => 'King of Tokyo',
                'category' => 'Ambiance',
                'maxPlayers' => 6,
                'durationMinutes' => 45,
                'condition' => 'Usé',
                'notes' => 'Dés un peu usés.',
                'status' => BoardGame::STATUS_LOANED,
                'borrower' => $memberB,
                'requestedAt' => $now->modify('-5 days'),
                'loanedAt' => $now->modify('-3 days'),
                'returnDueAt' => $now->modify('+11 days'),
                'color' => [220, 80, 40],
            ],
            [
                'title' => 'Gloomhaven: Jaws of the Lion',
                'category' => 'Expert',
                'maxPlayers' => 4,
                'durationMinutes' => 120,
                'condition' => 'Bon état',
                'notes' => 'Campagne en cours au local — ne pas mélanger les composants.',
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [50, 70, 60],
            ],
            [
                'title' => 'Love Letter',
                'category' => 'Ambiance',
                'maxPlayers' => 4,
                'durationMinutes' => 20,
                'condition' => 'Abîmé',
                'notes' => 'Boîte abîmée, cartes OK.',
                'status' => BoardGame::STATUS_AVAILABLE,
                'color' => [180, 60, 100],
            ],
        ];

        foreach ($games as $data) {
            $filename = $this->createCoverPng($data['title'], $data['color']);

            $game = new BoardGame();
            $game->setTitle($data['title']);
            $game->setCategory($data['category']);
            $game->setMaxPlayers($data['maxPlayers']);
            $game->setDurationMinutes($data['durationMinutes']);
            $game->setCondition($data['condition']);
            $game->setNotes($data['notes']);
            $game->setImage($filename);
            $game->setStatus($data['status']);

            if (isset($data['borrower']) && $data['borrower'] instanceof User) {
                $game->setBorrower($data['borrower']);
            }
            if (isset($data['requestedAt'])) {
                $game->setRequestedAt($data['requestedAt']);
            }
            if (isset($data['loanedAt'])) {
                $game->setLoanedAt($data['loanedAt']);
            }
            if (isset($data['returnDueAt'])) {
                $game->setReturnDueAt($data['returnDueAt']);
            }

            $manager->persist($game);
        }

        $manager->flush();
    }

    /**
     * Génère une jaquette PNG placeholder (400×400) et retourne le nom de fichier.
     *
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function createCoverPng(string $title, array $rgb): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title) ?? 'game', '-'));
        $filename = 'fixture-' . $slug . '.png';
        $path = $this->imagesDirectory . '/' . $filename;

        $size = 400;
        $img = imagecreatetruecolor($size, $size);
        if ($img === false) {
            throw new \RuntimeException('Impossible de créer une image GD pour ' . $title);
        }

        // Fond principal
        $bg = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $bg);

        // Bandeau sombre en bas
        $band = imagecolorallocate($img, 20, 20, 28);
        imagefilledrectangle($img, 0, 300, $size - 1, $size - 1, $band);

        // Motif géométrique simple (losanges)
        $accent = imagecolorallocate(
            $img,
            min(255, $rgb[0] + 40),
            min(255, $rgb[1] + 40),
            min(255, $rgb[2] + 40)
        );
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $cx = 50 + $i * 100;
                $cy = 60 + $j * 90;
                $points = [
                    $cx, $cy - 28,
                    $cx + 28, $cy,
                    $cx, $cy + 28,
                    $cx - 28, $cy,
                ];
                imagefilledpolygon($img, $points, $accent);
            }
        }

        // Initiales centrées
        $white = imagecolorallocate($img, 255, 255, 255);
        $initials = $this->initials($title);
        $font = 5;
        $charW = imagefontwidth($font);
        $charH = imagefontheight($font);
        $textW = $charW * strlen($initials);
        imagestring($img, $font, (int) (($size - $textW) / 2), 150, $initials, $white);

        // Titre dans le bandeau (tronqué si trop long)
        $label = mb_strlen($title) > 28 ? mb_substr($title, 0, 25) . '...' : $title;
        $labelW = $charW * strlen($label);
        imagestring($img, $font, (int) (($size - $labelW) / 2), 340, $label, $white);

        if (!imagepng($img, $path, 6)) {
            imagedestroy($img);
            throw new \RuntimeException('Impossible d\'écrire le PNG : ' . $path);
        }

        imagedestroy($img);

        return $filename;
    }

    private function initials(string $title): string
    {
        $words = preg_split('/\s+/', $title) ?: [];
        $letters = '';
        foreach ($words as $word) {
            $clean = preg_replace('/[^a-zA-Z0-9]/', '', $word) ?? '';
            if ($clean !== '') {
                $letters .= strtoupper($clean[0]);
            }
            if (strlen($letters) >= 3) {
                break;
            }
        }

        return $letters !== '' ? $letters : 'JDS';
    }
}
