<?php

namespace App\Controller\Admin;

use App\Entity\Activity;
use App\Entity\Inscription;
use App\Form\InscriptionType;
use App\Repository\ActivityRepository;
use App\Repository\InscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class ActivityRegisterController extends AbstractController
{
    #[Route('/activite/{id}/inscrire', name: 'app_activity_register', requirements: ['id' => '\d+'])]
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

        if ($activity->getStartAt() < new \DateTimeImmutable()) {
            $this->addFlash('warning', 'Les inscriptions pour cet événement sont fermées (date dépassée).');
            return $this->redirectToRoute('app_home');
        }

        $inscription = new Inscription();
        $inscription->setActivity($activity);

        $currentUser = $this->getUser();
        if ($currentUser) {
            $inscription->setParticipantName($currentUser->getUsername());
            $inscription->setParticipantEmail($currentUser->getEmail());
        }

        $isLoggedIn = $currentUser !== null;
        $form = $this->createForm(InscriptionType::class, $inscription, [
            'is_logged_in' => $isLoggedIn,
            'action' => $this->generateUrl('app_activity_register', ['id' => $id]),
        ]);
        $form->handleRequest($request);

        if ($isLoggedIn) {
            $inscription->setParticipantName($currentUser->getUsername());
            $inscription->setParticipantEmail($currentUser->getEmail());
        }

        $alreadyRegistered = false;
        $isAjax = $request->isXmlHttpRequest() || $request->headers->has('Turbo-Frame');

        if ($form->isSubmitted() && $form->isValid()) {
            $limiterKey = $currentUser
                ? 'user_' . $currentUser->getId()
                : ($request->getClientIp() ?? 'unknown');
            $limiter = $activityRegisterLimiter->create($limiterKey);
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop de tentatives d\'inscription. Veuillez réessayer dans quelques minutes.');
                return $this->redirectToRoute('app_home');
            }

            if ($inscriptionRepository->hasAlreadyRegistered($activity->getId(), $inscription->getParticipantEmail())) {
                $alreadyRegistered = true;
            } else {
                $entityManager->persist($inscription);
                $entityManager->flush();

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

        $template = $isAjax
            ? 'admin/activity_register/_register_frame.html.twig'
            : 'admin/activity_register/register.html.twig';

        return $this->render($template, [
            'activity' => $activity,
            'form' => $form,
            'alreadyRegistered' => $alreadyRegistered,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
