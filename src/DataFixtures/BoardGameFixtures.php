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

        // title, category, maxPlayers, durationMinutes, condition, notes
        $catalog = [
            ['Catan', 'Stratégie', 4, 90, 'Bon état', 'Extension Seafarers disponible au local.'],
            ['Ticket to Ride', 'Familial', 5, 60, 'Neuf', 'Version Europe.'],
            ['7 Wonders', 'Stratégie', 7, 45, 'Bon état', null],
            ['Azul', 'Familial', 4, 45, 'Neuf', 'Très demandé en soirée découverte.'],
            ['Wingspan', 'Stratégie', 5, 75, 'Bon état', 'Extension Européenne incluse.'],
            ['Dixit', 'Ambiance', 8, 30, 'Usé', 'Quelques cartes cornées.'],
            ['Carcassonne', 'Familial', 5, 45, 'Bon état', null],
            ['Pandemic', 'Coopératif', 4, 60, 'Bon état', 'Idéal pour initier au coopératif.'],
            ['Codenames', 'Ambiance', 8, 30, 'Neuf', null],
            ['Terraforming Mars', 'Expert', 5, 150, 'Bon état', 'Durée longue — prévoir une soirée entière.'],
            ['Splendor', 'Familial', 4, 30, 'Bon état', null],
            ['The Crew', 'Coopératif', 5, 20, 'Neuf', 'Jeu de cartes coopératif à missions.'],
            ['King of Tokyo', 'Ambiance', 6, 45, 'Usé', 'Dés un peu usés.'],
            ['Gloomhaven: Jaws of the Lion', 'Expert', 4, 120, 'Bon état', 'Campagne en cours au local.'],
            ['Love Letter', 'Ambiance', 4, 20, 'Abîmé', 'Boîte abîmée, cartes OK.'],
            ['Evergreen', 'Stratégie', 4, 45, 'Neuf', null],
            ['Quacks of Quedlinburg', 'Familial', 4, 45, 'Bon état', null],
            ['Cascadia', 'Familial', 4, 40, 'Neuf', 'Jeu calme, très apprécié.'],
            ['Root', 'Expert', 4, 90, 'Bon état', 'Asymétrique — lire les règles avant.'],
            ['Everdell', 'Stratégie', 4, 80, 'Bon état', null],
            ['Just One', 'Ambiance', 7, 20, 'Neuf', 'Parfait en grand groupe.'],
            ['Sky Team', 'Coopératif', 2, 20, 'Neuf', 'Duo uniquement.'],
            ['Forest Shuffle', 'Stratégie', 4, 45, 'Bon état', null],
            ['Ark Nova', 'Expert', 4, 150, 'Bon état', 'Très demandé — réservation conseillée.'],
            ['Heat: Pedal to the Metal', 'Familial', 6, 60, 'Neuf', null],
            ['Brass: Birmingham', 'Expert', 4, 120, 'Bon état', null],
            ['The Mind', 'Coopératif', 4, 20, 'Usé', null],
            ['Exploding Kittens', 'Ambiance', 5, 15, 'Usé', 'Cartes un peu cornées.'],
            ['Unlock! Escape Adventures', 'Coopératif', 6, 60, 'Bon état', 'Scénarios à usage unique — noter lesquels sont faits.'],
            ['Dobble', 'Ambiance', 8, 15, 'Bon état', 'Idéal enfants / apéro.'],
            ['Patchwork', 'Familial', 2, 30, 'Neuf', 'Duo uniquement.'],
            ['Concordia', 'Stratégie', 5, 100, 'Bon état', null],
            ['Spirit Island', 'Coopératif', 4, 120, 'Bon état', 'Complexité élevée.'],
            ['Blood Rage', 'Expert', 4, 90, 'Bon état', 'Figurines à manipuler avec soin.'],
            ['Inis', 'Stratégie', 4, 75, 'Bon état', null],
            ['Jaipur', 'Familial', 2, 30, 'Neuf', 'Duo uniquement.'],
            ['Hanabi', 'Coopératif', 5, 30, 'Usé', null],
            ['Time Stories', 'Coopératif', 4, 90, 'Bon état', 'Scénarios réutilisables avec soin.'],
            ['Scythe', 'Expert', 5, 115, 'Bon état', null],
            ['Twilight Struggle', 'Expert', 2, 150, 'Bon état', 'Duo — partie longue.'],
            ['Concept', 'Ambiance', 12, 40, 'Bon état', null],
            ['Welcome To', 'Familial', 100, 25, 'Neuf', 'Roll & write — illimité en joueurs.'],
            ['Cartographers', 'Familial', 100, 45, 'Bon état', 'Roll & write.'],
            ['Dune: Imperium', 'Expert', 4, 120, 'Neuf', null],
            ['Nemesis', 'Expert', 5, 180, 'Bon état', 'Semi-coop — soirée entière.'],
            ['Lost Ruins of Arnak', 'Stratégie', 4, 90, 'Bon état', null],
            ['Marvel Champions', 'Coopératif', 4, 60, 'Neuf', 'LCG — extensions au local.'],
            ['Race for the Galaxy', 'Stratégie', 4, 45, 'Usé', null],
            ['Dominion', 'Stratégie', 4, 30, 'Bon état', 'Deck-building classique.'],
            ['Kingdomino', 'Familial', 4, 20, 'Neuf', 'Idéal pour débuter.'],
        ];

        $palette = [
            [210, 140, 50], [40, 110, 180], [180, 140, 40], [60, 140, 200], [70, 130, 90],
            [160, 90, 160], [90, 150, 70], [180, 50, 50], [40, 40, 50], [200, 100, 40],
            [120, 60, 160], [30, 50, 100], [220, 80, 40], [50, 70, 60], [180, 60, 100],
            [50, 140, 110], [190, 120, 60], [80, 160, 120], [140, 90, 50], [100, 140, 80],
            [200, 160, 40], [70, 100, 160], [60, 120, 70], [160, 100, 50], [200, 60, 60],
            [90, 80, 70], [100, 60, 120], [220, 100, 80], [50, 80, 120], [220, 180, 40],
            [140, 80, 140], [180, 130, 70], [40, 100, 80], [160, 40, 50], [70, 110, 150],
            [200, 140, 60], [120, 70, 40], [80, 60, 100], [160, 120, 50], [50, 50, 80],
            [180, 100, 140], [60, 140, 160], [100, 150, 90], [140, 100, 60], [80, 40, 50],
            [170, 110, 50], [60, 60, 120], [150, 90, 40], [100, 80, 140], [90, 130, 100],
        ];

        // Index => overrides de statut / emprunt pour varier les données de test
        $statusOverrides = [
            7 => [ // Pandemic
                'status' => BoardGame::STATUS_PENDING,
                'borrower' => $member,
                'requestedAt' => $now->modify('-1 day'),
            ],
            9 => [ // Terraforming Mars
                'status' => BoardGame::STATUS_LOANED,
                'borrower' => $member,
                'requestedAt' => $now->modify('-10 days'),
                'loanedAt' => $now->modify('-7 days'),
                'returnDueAt' => $now->modify('+7 days'),
            ],
            12 => [ // King of Tokyo
                'status' => BoardGame::STATUS_LOANED,
                'borrower' => $memberB,
                'requestedAt' => $now->modify('-5 days'),
                'loanedAt' => $now->modify('-3 days'),
                'returnDueAt' => $now->modify('+11 days'),
            ],
            23 => [ // Ark Nova
                'status' => BoardGame::STATUS_PENDING,
                'borrower' => $memberB,
                'requestedAt' => $now->modify('-2 days'),
            ],
            44 => [ // Nemesis
                'status' => BoardGame::STATUS_LOANED,
                'borrower' => $member,
                'requestedAt' => $now->modify('-14 days'),
                'loanedAt' => $now->modify('-12 days'),
                'returnDueAt' => $now->modify('+2 days'),
            ],
        ];

        foreach ($catalog as $index => [$title, $category, $maxPlayers, $duration, $condition, $notes]) {
            $filename = $this->createCoverPng($title, $palette[$index % count($palette)]);

            $game = new BoardGame();
            $game->setTitle($title);
            $game->setCategory($category);
            $game->setMaxPlayers($maxPlayers);
            $game->setDurationMinutes($duration);
            $game->setCondition($condition);
            $game->setNotes($notes);
            $game->setImage($filename);
            $game->setStatus(BoardGame::STATUS_AVAILABLE);

            if (isset($statusOverrides[$index])) {
                $override = $statusOverrides[$index];
                $game->setStatus($override['status']);
                if (isset($override['borrower']) && $override['borrower'] instanceof User) {
                    $game->setBorrower($override['borrower']);
                }
                if (isset($override['requestedAt'])) {
                    $game->setRequestedAt($override['requestedAt']);
                }
                if (isset($override['loanedAt'])) {
                    $game->setLoanedAt($override['loanedAt']);
                }
                if (isset($override['returnDueAt'])) {
                    $game->setReturnDueAt($override['returnDueAt']);
                }
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

        $bg = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $bg);

        $band = imagecolorallocate($img, 20, 20, 28);
        imagefilledrectangle($img, 0, 300, $size - 1, $size - 1, $band);

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

        $white = imagecolorallocate($img, 255, 255, 255);
        $initials = $this->initials($title);
        $font = 5;
        $charW = imagefontwidth($font);
        $textW = $charW * strlen($initials);
        imagestring($img, $font, (int) (($size - $textW) / 2), 150, $initials, $white);

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
