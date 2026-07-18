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
            fputcsv($handle, ['Email', 'Date inscription', 'Date confirmation']);

            foreach ($subscribers as $subscriber) {
                fputcsv($handle, [
                    CsvCellSanitizer::sanitize((string) $subscriber->getEmail()),
                    $subscriber->getCreatedAt()?->format('d/m/Y H:i'),
                    $subscriber->getConfirmedAt()?->format('d/m/Y H:i'),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="newsletter_' . date('Y-m-d') . '.csv"');

        return $response;
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
