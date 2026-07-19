<?php

namespace App\Entity;

use App\Repository\LoanLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Historique minimal des emprunts, insert-only, jamais affiché dans une UI.
 *
 * Une ligne est créée à chaque validation d'emprunt par un admin
 * (LudothequeController::approve()). Son unique rôle est de répondre à la
 * question « ce membre a-t-il déjà emprunté ce jeu ? » pour l'éligibilité
 * à la notation (Review). Ni modifiée ni exposée après création.
 */
#[ORM\Entity(repositoryClass: LoanLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
class LoanLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BoardGame::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BoardGame $boardGame = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $loanedAt = null;

    /**
     * Lifecycle callback : initialise loanedAt à la première persistance.
     */
    #[ORM\PrePersist]
    public function setLoanedAtValue(): void
    {
        $this->loanedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBoardGame(): ?BoardGame
    {
        return $this->boardGame;
    }

    public function setBoardGame(?BoardGame $boardGame): static
    {
        $this->boardGame = $boardGame;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getLoanedAt(): ?\DateTimeImmutable
    {
        return $this->loanedAt;
    }
}
