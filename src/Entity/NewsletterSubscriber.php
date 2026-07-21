<?php

namespace App\Entity;

use App\Repository\NewsletterSubscriberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité représentant un abonné à la newsletter.
 *
 * Le processus d'abonnement est en double opt-in :
 *  1. L'utilisateur soumet son email → statut STATUS_PENDING, token généré, email envoyé.
 *  2. L'utilisateur clique le lien de confirmation → statut STATUS_CONFIRMED.
 *  3. L'utilisateur peut se désabonner via le lien token → statut STATUS_UNSUBSCRIBED.
 *
 * Le token (64 caractères hex, généré via random_bytes) sert à la fois
 * pour la confirmation et la désinscription.
 */
#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\HasLifecycleCallbacks]
class NewsletterSubscriber
{
    /** Email soumis, en attente de confirmation par clic sur le lien reçu. */
    public const STATUS_PENDING = 'pending';

    /** Email confirmé, l'abonné reçoit la newsletter. */
    public const STATUS_CONFIRMED = 'confirmed';

    /** Abonné désabonné volontairement via le lien de désinscription. */
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 64)]
    private ?string $token = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    /**
     * Lifecycle callback : initialise createdAt et génère le token de confirmation
     * à la première persistance (token = 64 caractères hexadécimaux).
     */
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->regenerateToken();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * Génère un nouveau token (utile quand on renvoie un email de confirmation).
     */
    public function regenerateToken(int $ttlSeconds = 172800): static
    {
        $this->token = bin2hex(random_bytes(32));
        $this->tokenExpiresAt = new \DateTimeImmutable('+' . $ttlSeconds . ' seconds');

        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;
        return $this;
    }

    public function isTokenExpired(): bool
    {
        return $this->tokenExpiresAt !== null
            && $this->tokenExpiresAt < new \DateTimeImmutable();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;
        return $this;
    }

    public function getUnsubscribedAt(): ?\DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function setUnsubscribedAt(?\DateTimeImmutable $unsubscribedAt): static
    {
        $this->unsubscribedAt = $unsubscribedAt;
        return $this;
    }

    /**
     * Confirme l'abonnement : passe le statut à STATUS_CONFIRMED et enregistre la date.
     */
    public function confirm(): static
    {
        $this->status = self::STATUS_CONFIRMED;
        $this->confirmedAt = new \DateTimeImmutable();
        $this->unsubscribedAt = null;
        $this->tokenExpiresAt = null;

        return $this;
    }

    /**
     * Remet l'abonné en attente de confirmation (admin).
     */
    public function markAsPending(): static
    {
        $this->status = self::STATUS_PENDING;
        $this->confirmedAt = null;
        $this->unsubscribedAt = null;
        $this->regenerateToken();

        return $this;
    }

    /**
     * Désinscrit l'abonné : passe le statut à STATUS_UNSUBSCRIBED et enregistre la date.
     */
    public function unsubscribe(): static
    {
        $this->status = self::STATUS_UNSUBSCRIBED;
        $this->unsubscribedAt = new \DateTimeImmutable();
        return $this;
    }
}
