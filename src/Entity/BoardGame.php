<?php

namespace App\Entity;

use App\Repository\BoardGameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité représentant un jeu du catalogue de la ludothèque de l'association.
 *
 * Un jeu a un statut :
 *   - STATUS_AVAILABLE : disponible à l'emprunt
 *   - STATUS_PENDING   : demande d'emprunt en attente de validation admin
 *   - STATUS_LOANED    : actuellement emprunté
 *
 * Cycle de vie du statut : available -> (demande membre) -> pending ->
 * (validation admin) -> loaned -> (retour constaté par un admin) -> available.
 * Un admin peut aussi rejeter une demande pending, qui repasse à available.
 *
 * Les champs createdAt et updatedAt sont automatiquement gérés via les
 * lifecycle callbacks PrePersist / PreUpdate.
 *
 * La relation borrower est nullable avec onDelete='SET NULL' pour conserver
 * le jeu si l'utilisateur emprunteur est supprimé ; elle n'a de sens que
 * pendant que le statut est pending/loaned.
 */
#[ORM\Entity(repositoryClass: BoardGameRepository::class)]
#[ORM\Table(name: 'board_game')]
#[ORM\HasLifecycleCallbacks]
class BoardGame
{
    /** Jeu disponible à l'emprunt. */
    public const STATUS_AVAILABLE = 'available';

    /** Demande d'emprunt en attente de validation par un administrateur. */
    public const STATUS_PENDING = 'pending';

    /** Jeu actuellement emprunté. */
    public const STATUS_LOANED = 'loaned';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $maxPlayers = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $durationMinutes = null;

    /** État physique du jeu (Neuf, Bon état, Usé, Abîmé). Colonne SQL : item_condition (condition est réservé en MySQL). */
    #[ORM\Column(name: 'item_condition', length: 32, nullable: true)]
    private ?string $condition = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** Nom du fichier image (PNG/JPEG) stocké dans public/images/board-games/. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 16, options: ['default' => 'available'])]
    private string $status = self::STATUS_AVAILABLE;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $borrower = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $loanedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $returnDueAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $archived = false;

    /**
     * Lifecycle callback : initialise createdAt et updatedAt à la création.
     */
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Lifecycle callback : met à jour updatedAt à chaque modification.
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getMaxPlayers(): ?int
    {
        return $this->maxPlayers;
    }

    public function setMaxPlayers(?int $maxPlayers): static
    {
        $this->maxPlayers = $maxPlayers;
        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;
        return $this;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function setCondition(?string $condition): static
    {
        $this->condition = $condition;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @throws \InvalidArgumentException si le statut n'est pas une valeur autorisée.
     */
    public function setStatus(string $status): static
    {
        if (!in_array($status, [self::STATUS_AVAILABLE, self::STATUS_PENDING, self::STATUS_LOANED], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid status "%s". Allowed values: "%s", "%s", "%s".',
                $status,
                self::STATUS_AVAILABLE,
                self::STATUS_PENDING,
                self::STATUS_LOANED
            ));
        }
        $this->status = $status;
        return $this;
    }

    public function getBorrower(): ?User
    {
        return $this->borrower;
    }

    public function setBorrower(?User $borrower): static
    {
        $this->borrower = $borrower;
        return $this;
    }

    public function getRequestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(?\DateTimeInterface $requestedAt): static
    {
        $this->requestedAt = $requestedAt === null
            ? null
            : ($requestedAt instanceof \DateTimeImmutable ? $requestedAt : \DateTimeImmutable::createFromInterface($requestedAt));
        return $this;
    }

    public function getLoanedAt(): ?\DateTimeImmutable
    {
        return $this->loanedAt;
    }

    public function setLoanedAt(?\DateTimeInterface $loanedAt): static
    {
        $this->loanedAt = $loanedAt === null
            ? null
            : ($loanedAt instanceof \DateTimeImmutable ? $loanedAt : \DateTimeImmutable::createFromInterface($loanedAt));
        return $this;
    }

    public function getReturnDueAt(): ?\DateTimeImmutable
    {
        return $this->returnDueAt;
    }

    public function setReturnDueAt(?\DateTimeInterface $returnDueAt): static
    {
        $this->returnDueAt = $returnDueAt === null
            ? null
            : ($returnDueAt instanceof \DateTimeImmutable ? $returnDueAt : \DateTimeImmutable::createFromInterface($returnDueAt));
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): static
    {
        $this->archived = $archived;
        return $this;
    }
}
