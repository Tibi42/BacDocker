<?php

namespace App\Controller\Admin;

use App\Entity\Activity;
use App\Enum\ActivityKind;
use App\Entity\User;
use App\Form\ActivityType;
use App\Repository\ActivityRepository;
use App\Repository\InscriptionRepository;
use App\Util\CsvCellSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * CRUD admin des activités.
 *
 * Gère la liste paginée et filtrée, la création, l'édition, la suppression,
 * la validation/rejet des propositions en attente, la liste des inscrits
 * et l'export CSV. Lors de toute suppression ou rejet, un email de
 * notification d'annulation est envoyé à tous les participants inscrits.
 */
#[Route('/admin/activites', name: 'app_activity_')]
class ActivityController extends AbstractController
{
    use BulkSelectionTrait;

    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly InscriptionRepository $inscriptionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Liste paginée des activités avec filtres (statut, type, lieu, période).
     * Affiche également le nombre d'inscrits par activité.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $status = $request->query->get('status');
        if ($status !== null && !in_array($status, [Activity::STATUS_PUBLISHED, Activity::STATUS_PENDING], true)) {
            $status = null;
        }

        $allowedTypes = ActivityKind::values();
        $filterType = $request->query->get('type');
        if ($filterType !== null && !in_array($filterType, $allowedTypes, true)) {
            $filterType = null;
        }

        $allowedLocations = ['L\'auberge de jeunesse Yves Robert', 'Le Natema'];
        $filterLocation = $request->query->get('location');
        if ($filterLocation !== null && !in_array($filterLocation, $allowedLocations, true)) {
            $filterLocation = null;
        }

        $filterPeriod = $request->query->get('period');
        if ($filterPeriod !== null && !in_array($filterPeriod, ['past', 'future'], true)) {
            $filterPeriod = null;
        }

        $qb = $this->activityRepository->findAllOrderByStartDescQb($status, $filterType, $filterLocation, $filterPeriod);

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            15
        );

        $inscriptionCounts = $this->inscriptionRepository->countByActivity();

        return $this->render('admin/activity/index.html.twig', [
            'pagination' => $pagination,
            'inscriptionCounts' => $inscriptionCounts,
            'currentStatus' => $status,
            'currentType' => $filterType,
            'currentLocation' => $filterLocation,
            'currentPeriod' => $filterPeriod,
        ]);
    }

    /**
     * Export CSV de la liste des activités (avec les mêmes filtres que la liste).
     *
     * Ajoute un BOM UTF-8 pour la compatibilité avec Excel et utilise ';'
     * comme séparateur (standard français).
     */
    #[Route('/export-csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        $status = $request->query->get('status');
        if ($status !== null && !in_array($status, [Activity::STATUS_PUBLISHED, Activity::STATUS_PENDING], true)) {
            $status = null;
        }

        $allowedTypes = ActivityKind::values();
        $filterType = $request->query->get('type');
        if ($filterType !== null && !in_array($filterType, $allowedTypes, true)) {
            $filterType = null;
        }

        $allowedLocations = ['L\'auberge de jeunesse Yves Robert', 'Le Natema'];
        $filterLocation = $request->query->get('location');
        if ($filterLocation !== null && !in_array($filterLocation, $allowedLocations, true)) {
            $filterLocation = null;
        }

        $activities = $this->activityRepository->findAllOrderByStartDesc($status, $filterType, $filterLocation);

        $response = new StreamedResponse(function () use ($activities) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Date', 'Titre', 'Type', 'Lieu', 'Statut', 'Proposé par', 'Max participants'], ';', '"', '\\');

            foreach ($activities as $activity) {
                fputcsv($handle, [
                    $activity->getStartAt()->format('d/m/Y'),
                    CsvCellSanitizer::sanitize((string) $activity->getTitle()),
                    CsvCellSanitizer::sanitize((string) ($activity->getType() ?? '')),
                    CsvCellSanitizer::sanitize((string) ($activity->getLocation() ?? '')),
                    $activity->getStatus() === Activity::STATUS_PUBLISHED ? 'Publiée' : 'En attente',
                    CsvCellSanitizer::sanitize((string) ($activity->getProposedBy()?->getEmail() ?? '')),
                    $activity->getMaxParticipants() ?? '',
                ], ';', '"', '\\');
            }

            fclose($handle);
        });

        $filename = 'activites_' . date('Y-m-d') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/nouvelle', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $activity = new Activity();
        $form = $this->createForm(ActivityType::class, $activity, ['is_admin' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if ($user instanceof User) {
                $activity->setCreatedBy($user);
            }
            $this->entityManager->persist($activity);
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'activité « ' . $activity->getTitle() . ' » a été créée.');

            return $this->redirectToRoute('app_activity_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/activity/new.html.twig', [
            'activity' => $activity,
            'form' => $form,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Activity $activity): Response
    {
        $rawReturn = $request->request->get('return') ?: $request->query->get('return', '');
        $returnUrl = (is_string($rawReturn) && str_starts_with($rawReturn, '/') && !str_starts_with($rawReturn, '//'))
            ? $rawReturn
            : null;

        $form = $this->createForm(ActivityType::class, $activity, ['is_admin' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'activité « ' . $activity->getTitle() . ' » a été mise à jour.');

            if ($returnUrl) {
                return $this->redirect($returnUrl, Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_activity_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/activity/edit.html.twig', [
            'activity' => $activity,
            'form' => $form,
            'returnUrl' => $returnUrl,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Affiche la liste des participants inscrits à une activité.
     */
    #[Route('/{id}/inscrits', name: 'inscrits', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function inscrits(Activity $activity): Response
    {
        $inscriptions = $this->inscriptionRepository->findByActivity($activity);

        return $this->render('admin/activity/inscrits.html.twig', [
            'activity' => $activity,
            'inscriptions' => $inscriptions,
        ]);
    }

    /**
     * Valide en masse les activités en attente (pending → published).
     */
    #[Route('/valider-selection', name: 'validate_bulk', methods: ['POST'])]
    public function validateBulk(Request $request): Response
    {
        $redirectParams = $this->indexQueryParams($request);

        if (!$this->isCsrfTokenValid('validate_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_activity_index', $redirectParams, Response::HTTP_SEE_OTHER);
        }

        $ids = $this->parseBulkIds($request);
        if ($ids === []) {
            $this->addFlash('error', 'Aucune activité sélectionnée.');

            return $this->redirectToRoute('app_activity_index', $redirectParams, Response::HTTP_SEE_OTHER);
        }

        $activities = $this->activityRepository->findBy(['id' => $ids, 'status' => Activity::STATUS_PENDING]);
        foreach ($activities as $activity) {
            $activity->setStatus(Activity::STATUS_PUBLISHED);
        }

        if ($activities !== []) {
            $this->entityManager->flush();
        }

        $count = \count($activities);
        if ($count === 0) {
            $this->addFlash('error', 'Aucune activité en attente dans la sélection.');
        } else {
            $this->addFlash('success', $count . ' activité' . ($count > 1 ? 's' : '') . ' validée' . ($count > 1 ? 's' : '') . '.');
        }

        return $this->redirectToRoute('app_activity_index', $redirectParams, Response::HTTP_SEE_OTHER);
    }

    /**
     * Supprime en masse les activités à venir (emails d'annulation inclus).
     */
    #[Route('/supprimer-selection', name: 'delete_bulk', methods: ['POST'])]
    public function deleteBulk(Request $request): Response
    {
        $redirectParams = $this->indexQueryParams($request);

        if (!$this->isCsrfTokenValid('delete_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_activity_index', $redirectParams, Response::HTTP_SEE_OTHER);
        }

        $ids = $this->parseBulkIds($request);
        if ($ids === []) {
            $this->addFlash('error', 'Aucune activité sélectionnée.');

            return $this->redirectToRoute('app_activity_index', $redirectParams, Response::HTTP_SEE_OTHER);
        }

        $now = new \DateTimeImmutable();
        $activities = $this->activityRepository->findBy(['id' => $ids]);
        $deleted = 0;
        $skipped = 0;

        foreach ($activities as $activity) {
            if ($activity->getStartAt() <= $now) {
                ++$skipped;
                continue;
            }

            $title = (string) $activity->getTitle();
            $this->sendCancellationEmails($activity, $title);
            $this->entityManager->remove($activity);
            ++$deleted;
        }

        if ($deleted > 0) {
            $this->entityManager->flush();
            $this->addFlash('success', $deleted . ' activité' . ($deleted > 1 ? 's' : '') . ' supprimée' . ($deleted > 1 ? 's' : '') . '.');
        }
        if ($skipped > 0) {
            $this->addFlash('warning', $skipped . ' activité' . ($skipped > 1 ? 's' : '') . ' passée' . ($skipped > 1 ? 's' : '') . ' ignorée' . ($skipped > 1 ? 's' : '') . '.');
        }
        if ($deleted === 0 && $skipped === 0) {
            $this->addFlash('error', 'Aucune activité à supprimer dans la sélection.');
        }

        return $this->redirectToRoute('app_activity_index', $redirectParams, Response::HTTP_SEE_OTHER);
    }

    /**
     * Approuve une proposition d'activité en la passant au statut STATUS_PUBLISHED.
     */
    #[Route('/{id}/approuver', name: 'approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(Request $request, Activity $activity): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('approve' . $activity->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_activity_index', [], Response::HTTP_SEE_OTHER);
        }

        $activity->setStatus(Activity::STATUS_PUBLISHED);
        $this->entityManager->flush();
        $this->addFlash('success', 'L\'activité « ' . $activity->getTitle() . ' » a été approuvée et est maintenant visible.');

        return $this->redirectToRoute('app_activity_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Rejette et supprime une proposition d'activité en attente.
     * Envoie des emails d'annulation aux participants déjà inscrits.
     */
    #[Route('/{id}/rejeter', name: 'reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reject(Request $request, Activity $activity): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('reject' . $activity->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_activity_index', [], Response::HTTP_SEE_OTHER);
        }

        $title = $activity->getTitle();

        $this->sendCancellationEmails($activity, $title);

        $this->entityManager->remove($activity);
        $this->entityManager->flush();
        $this->addFlash('success', 'La proposition « ' . $title . ' » a été rejetée et supprimée.');

        return $this->redirectToRoute('app_activity_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Supprime une activité publiée (interdite si l'activité est déjà passée).
     * Envoie des emails d'annulation aux participants inscrits avant suppression.
     */
    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Activity $activity): Response
    {
        if ($activity->getStartAt() <= new \DateTimeImmutable()) {
            $this->addFlash('error', 'Impossible de supprimer une activité passée.');
            return $this->redirectToRoute('app_activity_index', [], Response::HTTP_SEE_OTHER);
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $activity->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_activity_index', [], Response::HTTP_SEE_OTHER);
        }

        $title = $activity->getTitle();

        $this->sendCancellationEmails($activity, $title);

        $this->entityManager->remove($activity);
        $this->entityManager->flush();
        $this->addFlash('success', 'L\'activité « ' . $title . ' » a été supprimée.');

        return $this->redirectToRoute('app_activity_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Envoie un email d'annulation à chaque participant inscrit à l'activité.
     * Les erreurs d'envoi individuelles sont silencieuses pour ne pas bloquer la suppression.
     */
    private function sendCancellationEmails(Activity $activity, string $title): void
    {
        $inscriptions = $this->inscriptionRepository->findBy(['activity' => $activity]);
        $siteUrl = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        foreach ($inscriptions as $i) {
            try {
                $email = (new TemplatedEmail())
                    ->from('boiteachimere@guillaumepecquet.ovh')
                    ->to($i->getParticipantEmail())
                    ->subject('Événement annulé : ' . $title)
                    ->htmlTemplate('emails/activity_cancelled.html.twig')
                    ->context([
                        'participantName'  => $i->getParticipantName(),
                        'activityTitle'    => $title,
                        'activityType'     => $activity->getType() ?? 'Événement',
                        'activityDate'     => $activity->getStartAt()?->format('d/m/Y') ?? '',
                        'activityLocation' => $activity->getLocation(),
                        'siteUrl'          => $siteUrl,
                    ]);
                $this->mailer->send($email);
            } catch (\Throwable) {}
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function indexQueryParams(Request $request): array
    {
        $params = [];
        foreach (['status', 'type', 'location', 'period'] as $key) {
            $value = $request->query->get($key);
            if (\is_string($value) && $value !== '') {
                $params[$key] = $value;
            }
        }
        $page = $request->query->getInt('page', 1);
        if ($page > 1) {
            $params['page'] = $page;
        }

        return $params;
    }
}
