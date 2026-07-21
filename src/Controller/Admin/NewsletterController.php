<?php

namespace App\Controller\Admin;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use App\Util\CsvCellSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/newsletter', name: 'app_admin_newsletter_')]
class NewsletterController extends AbstractController
{
    use BulkSelectionTrait;

    public function __construct(
        private readonly NewsletterSubscriberRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/newsletter/index.html.twig', [
            'subscribers' => $this->repository->findAllOrderedByDate(),
            'countConfirmed' => $this->repository->countByStatus(NewsletterSubscriber::STATUS_CONFIRMED),
            'countPending' => $this->repository->countByStatus(NewsletterSubscriber::STATUS_PENDING),
            'countUnsubscribed' => $this->repository->countByStatus(NewsletterSubscriber::STATUS_UNSUBSCRIBED),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(): StreamedResponse
    {
        $subscribers = $this->repository->findConfirmed();

        $response = new StreamedResponse(function () use ($subscribers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Email', 'Date inscription', 'Date confirmation'], ',', '"', '\\');

            foreach ($subscribers as $subscriber) {
                fputcsv($handle, [
                    CsvCellSanitizer::sanitize((string) $subscriber->getEmail()),
                    $subscriber->getCreatedAt()?->format('d/m/Y H:i'),
                    $subscriber->getConfirmedAt()?->format('d/m/Y H:i'),
                ], ',', '"', '\\');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="newsletter_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    #[Route('/confirmer-selection', name: 'confirm_bulk', methods: ['POST'])]
    public function confirmBulk(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('validate_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_newsletter_index');
        }

        $ids = $this->parseBulkIds($request);
        if ($ids === []) {
            $this->addFlash('error', 'Aucun abonné sélectionné.');

            return $this->redirectToRoute('app_admin_newsletter_index');
        }

        $subscribers = $this->repository->findBy(['id' => $ids]);
        $count = 0;
        foreach ($subscribers as $subscriber) {
            if ($subscriber->getStatus() === NewsletterSubscriber::STATUS_CONFIRMED) {
                continue;
            }
            $subscriber->confirm();
            ++$count;
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $this->addFlash('success', $count . ' abonné' . ($count > 1 ? 's' : '') . ' confirmé' . ($count > 1 ? 's' : '') . '.');
        } else {
            $this->addFlash('error', 'Aucun abonné à confirmer dans la sélection.');
        }

        return $this->redirectToRoute('app_admin_newsletter_index');
    }

    #[Route('/en-attente-selection', name: 'pending_bulk', methods: ['POST'])]
    public function pendingBulk(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('suspend_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_newsletter_index');
        }

        $ids = $this->parseBulkIds($request);
        if ($ids === []) {
            $this->addFlash('error', 'Aucun abonné sélectionné.');

            return $this->redirectToRoute('app_admin_newsletter_index');
        }

        $subscribers = $this->repository->findBy(['id' => $ids]);
        $count = 0;
        foreach ($subscribers as $subscriber) {
            if ($subscriber->getStatus() === NewsletterSubscriber::STATUS_PENDING) {
                continue;
            }
            $subscriber->markAsPending();
            ++$count;
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $this->addFlash('success', $count . ' abonné' . ($count > 1 ? 's' : '') . ' remis en attente.');
        } else {
            $this->addFlash('error', 'Aucun abonné à remettre en attente dans la sélection.');
        }

        return $this->redirectToRoute('app_admin_newsletter_index');
    }

    #[Route('/supprimer-selection', name: 'delete_bulk', methods: ['POST'])]
    public function deleteBulk(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_newsletter_index');
        }

        $ids = $this->parseBulkIds($request);
        if ($ids === []) {
            $this->addFlash('error', 'Aucun abonné sélectionné.');

            return $this->redirectToRoute('app_admin_newsletter_index');
        }

        $subscribers = $this->repository->findBy(['id' => $ids]);
        foreach ($subscribers as $subscriber) {
            $this->entityManager->remove($subscriber);
        }

        if ($subscribers !== []) {
            $this->entityManager->flush();
        }

        $count = \count($subscribers);
        if ($count === 0) {
            $this->addFlash('error', 'Aucun abonné à supprimer.');
        } else {
            $this->addFlash('success', $count . ' abonné' . ($count > 1 ? 's' : '') . ' supprimé' . ($count > 1 ? 's' : '') . '.');
        }

        return $this->redirectToRoute('app_admin_newsletter_index');
    }

    #[Route('/{id}/confirmer', name: 'confirm', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirm(Request $request, NewsletterSubscriber $subscriber): Response
    {
        if (!$this->isCsrfTokenValid('confirm' . $subscriber->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_newsletter_index');
        }

        if ($subscriber->getStatus() === NewsletterSubscriber::STATUS_CONFIRMED) {
            $this->addFlash('error', 'Cet abonné est déjà confirmé.');

            return $this->redirectToRoute('app_admin_newsletter_index');
        }

        $subscriber->confirm();
        $this->entityManager->flush();
        $this->addFlash('success', 'L\'abonné « ' . $subscriber->getEmail() . ' » a été confirmé.');

        return $this->redirectToRoute('app_admin_newsletter_index');
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, NewsletterSubscriber $subscriber): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $subscriber->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_newsletter_index');
        }

        $email = $subscriber->getEmail();
        $this->entityManager->remove($subscriber);
        $this->entityManager->flush();
        $this->addFlash('success', 'L\'abonné « ' . $email . ' » a été supprimé.');

        return $this->redirectToRoute('app_admin_newsletter_index');
    }
}
