<?php

namespace App\Controller\Admin;

use App\Entity\Activity;
use App\Entity\Inscription;
use App\Entity\User;
use App\Form\InscriptionType;
use App\Repository\ActivityRepository;
use App\Repository\InscriptionRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ActivityRegisterController extends AbstractController
{
    #[Route('/activite/{id}/inscrire', name: 'app_activity_register', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function register(
        int $id,
        Request $request,
        ActivityRepository $activityRepository,
        InscriptionRepository $inscriptionRepository,
        EntityManagerInterface $entityManager,
        RateLimiterFactoryInterface $activityRegisterLimiter,
    ): Response {
        $activity = $activityRepository->find($id);
        if (!$activity) {
            throw $this->createNotFoundException('Cet événement n\'existe pas.');
        }

        if ($activity->getStatus() !== Activity::STATUS_PUBLISHED && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('Cet événement n\'est pas disponible à l\'inscription.');
        }

        if ($activity->getStartAt() < new \DateTimeImmutable()) {
            $this->addFlash('warning', 'Les inscriptions pour cet événement sont fermées (date dépassée).');
            return $this->redirectToRoute('app_home');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $inscription = new Inscription();
        $inscription->setActivity($activity);
        $inscription->setParticipantName($currentUser->getUsername());
        $inscription->setParticipantEmail($currentUser->getEmail());

        $form = $this->createForm(InscriptionType::class, $inscription, [
            'is_logged_in' => true,
            'action' => $this->generateUrl('app_activity_register', ['id' => $id]),
        ]);
        $form->handleRequest($request);

        $inscription->setParticipantName($currentUser->getUsername());
        $inscription->setParticipantEmail($currentUser->getEmail());

        $alreadyRegistered = false;
        $isAjax = $request->isXmlHttpRequest() || $request->headers->has('Turbo-Frame');

        $maxParticipants = $activity->getMaxParticipants();
        $isFull = $maxParticipants !== null
            && $inscriptionRepository->countForActivity($activity->getId()) >= $maxParticipants;

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $activityRegisterLimiter->create('user_' . $currentUser->getId());
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop de tentatives d\'inscription. Veuillez réessayer dans quelques minutes.');
                return $this->redirectToRoute('app_home');
            }

            if ($inscriptionRepository->hasAlreadyRegistered($activity->getId(), $inscription->getParticipantEmail())) {
                $alreadyRegistered = true;
            } else {
                $registered = false;
                $becameFull = false;

                $entityManager->wrapInTransaction(function () use (
                    $entityManager,
                    $inscriptionRepository,
                    $id,
                    $inscription,
                    &$registered,
                    &$becameFull,
                ): void {
                    /** @var Activity|null $locked */
                    $locked = $entityManager->find(Activity::class, $id, LockMode::PESSIMISTIC_WRITE);
                    if (!$locked) {
                        return;
                    }

                    $max = $locked->getMaxParticipants();
                    if ($max !== null && $inscriptionRepository->countForActivity($locked->getId()) >= $max) {
                        $becameFull = true;

                        return;
                    }

                    $inscription->setActivity($locked);
                    $entityManager->persist($inscription);
                    $registered = true;
                });

                if ($becameFull) {
                    $this->addFlash('error', 'Cet événement est complet, les inscriptions sont fermées.');
                    return $this->redirectToRoute('app_home');
                }

                if ($registered) {
                    $template = $isAjax
                        ? 'admin/activity_register/_register_frame.html.twig'
                        : 'admin/activity_register/register.html.twig';

                    return $this->render($template, [
                        'activity' => $activity,
                        'form' => $form,
                        'alreadyRegistered' => false,
                        'success' => true,
                    ]);
                }
            }
        }

        $template = $isAjax
            ? 'admin/activity_register/_register_frame.html.twig'
            : 'admin/activity_register/register.html.twig';

        return $this->render($template, [
            'activity' => $activity,
            'form' => $form,
            'alreadyRegistered' => $alreadyRegistered,
            'isFull' => $isFull,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
