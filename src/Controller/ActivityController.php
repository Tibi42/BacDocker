<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Inscription;
use App\Form\ActivityType;
use App\Notification\NewActivityProposalNotification;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur public de création d'activités (propositions par les membres).
 *
 * Permet à tout utilisateur connecté de proposer une nouvelle activité depuis
 * le calendrier. Les activités créées par un simple membre passent en statut
 * "pending" et sont soumises à validation admin ; celles créées par un admin
 * peuvent être publiées immédiatement.
 */
class ActivityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotifierInterface $notifier,
        private readonly UserRepository $userRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Formulaire de création d'une activité (accessible depuis le calendrier).
     *
     * GET  : retourne un fragment HTML (Turbo Frame) contenant le formulaire
     *        pré-rempli avec la date passée en paramètre (?date=Y-m-d).
     * POST valide   : persiste l'activité, inscrit automatiquement le créateur,
     *                 notifie les admins si la proposition est en attente, redirige.
     * POST invalide : retourne le formulaire avec erreurs (422).
     */
    #[Route('/activite/nouvelle', name: 'app_activity_new_public', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        RateLimiterFactoryInterface $activityProposalLimiter,
    ): Response {
        $activity = new Activity();

        // Pré-remplir startAt depuis le paramètre GET ?date=Y-m-d
        $dateParam = $request->query->get('date', '');
        $startAt = \DateTimeImmutable::createFromFormat('Y-m-d', $dateParam);
        if (!$startAt) {
            $startAt = new \DateTimeImmutable('today');
        }
        $activity->setStartAt($startAt);

        $form = $this->createForm(ActivityType::class, $activity, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $limiter = $activityProposalLimiter->create('user_' . $user->getId());
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop de propositions envoyées. Veuillez réessayer plus tard.');
                return $this->redirectToRoute('app_home');
            }

            $publishNow = $this->isGranted('ROLE_ADMIN') && $request->request->get('publish_now');
            $activity->setStatus($publishNow ? Activity::STATUS_PUBLISHED : Activity::STATUS_PENDING);
            $activity->setProposedBy($this->getUser());
            $this->addFlash('success', $publishNow
                ? 'L\'événement « ' . $activity->getTitle() . ' » a été créé et publié.'
                : 'Votre proposition « ' . $activity->getTitle() . ' » a été envoyée et sera examinée par un administrateur.'
            );

            $this->entityManager->persist($activity);

            $user = $this->getUser();
            $inscription = new Inscription();
            $inscription->setActivity($activity);
            $inscription->setParticipantName($user->getUsername());
            $inscription->setParticipantEmail($user->getEmail());
            $this->entityManager->persist($inscription);

            $this->entityManager->flush();

            if ($activity->getStatus() === Activity::STATUS_PENDING) {
                $this->notifyAdminsOfNewProposal($activity);
            }

            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        }

        // GET : fragment nu pour Turbo Frame
        if ($request->isMethod('GET')) {
            return $this->render('activity/modal_form.html.twig', [
                'form' => $form,
                'date' => $dateParam,
            ]);
        }

        // POST invalide : page complète avec erreurs
        return $this->render('activity/modal_form_page.html.twig', [
            'form' => $form,
        ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    /**
     * Envoie une notification email à tous les admins pour une nouvelle proposition.
     *
     * Utilise le composant Notifier de Symfony avec NewActivityProposalNotification.
     * Les échecs d'envoi individuels sont silencieux pour ne pas bloquer le flux.
     */
    private function notifyAdminsOfNewProposal(Activity $activity): void
    {
        $admins = $this->userRepository->findAdmins();
        if (!$admins) {
            return;
        }

        $reviewUrl = $this->urlGenerator->generate(
            'app_activity_index',
            ['status' => Activity::STATUS_PENDING],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $notification = new NewActivityProposalNotification($activity, $reviewUrl);

        foreach ($admins as $admin) {
            try {
                $this->notifier->send($notification, new Recipient($admin->getEmail()));
            } catch (\Throwable) {}
        }
    }
}
