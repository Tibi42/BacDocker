<?php

namespace App\Controller\Admin;

use App\Entity\NewsletterSubscriber;
use App\Repository\ActivityRepository;
use App\Repository\InscriptionRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tableau de bord de l'administration.
 *
 * Agrège les statistiques clés affichées sur la page d'accueil du back-office :
 * total des inscriptions, activités en attente, top proposeurs du mois,
 * statistiques newsletter, membres actifs/inactifs et derniers utilisateurs inscrits.
 *
 * Utilise le pattern __invoke() (single action controller).
 */
#[Route('/admin', name: 'admin', methods: ['GET'])]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly InscriptionRepository $inscriptionRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly NewsletterSubscriberRepository $newsletterRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function __invoke(): Response
    {
        $inscriptionsTotal = $this->inscriptionRepository->countAll();
        $latestInscriptions = $this->inscriptionRepository->findLatestWithActivity(10);
        $pendingActivitiesCount = $this->activityRepository->countPendingApproval();

        $now = new \DateTimeImmutable();
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $nextMonthStart = $monthStart->modify('+1 month');
        $topProposers = $this->activityRepository->findTopProposersBetween($monthStart, $nextMonthStart, 5);

        $newsletterConfirmed = $this->newsletterRepository->countByStatus(NewsletterSubscriber::STATUS_CONFIRMED);
        $newsletterPending = $this->newsletterRepository->countByStatus(NewsletterSubscriber::STATUS_PENDING);

        $latestUsers = $this->userRepository->findLatest(5);
        $totalUsers = $this->userRepository->countActive();
        $inactiveUsers = $this->userRepository->countInactive();

        return $this->render('admin/dashboard.html.twig', [
            'inscriptionsTotal' => $inscriptionsTotal,
            'latestInscriptions' => $latestInscriptions,
            'latestUsers' => $latestUsers,
            'topProposers' => $topProposers,
            'pendingActivitiesCount' => $pendingActivitiesCount,
            'newsletterConfirmed' => $newsletterConfirmed,
            'newsletterPending' => $newsletterPending,
            'totalUsers' => $totalUsers,
            'inactiveUsers' => $inactiveUsers,
        ]);
    }
}
