<?php

namespace App\Controller\Admin;

use App\Entity\BoardGame;
use App\Entity\LoanLog;
use App\Entity\Review;
use App\Form\BoardGameType;
use App\Repository\BoardGameRepository;
use App\Repository\ReviewRepository;
use App\Service\BoardGameCsvImporter;
use App\Service\ImageReencoder;
use App\Util\CsvCellSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD admin de la ludothèque + workflow d'approbation des emprunts.
 *
 * Un membre demande un jeu depuis /mon-espace (statut -> pending), puis un
 * admin valide (avec une date de retour) ou rejette la demande, et constate
 * le retour du jeu une fois emprunté. Seul un admin peut marquer un retour.
 */
#[Route('/admin/ludotheque', name: 'app_admin_ludotheque_')]
#[IsGranted('ROLE_ADMIN')]
class LudothequeController extends AbstractController
{
    private const PER_PAGE = 15;
    private const IMAGE_MAX_WIDTH = 600;
    private const IMAGE_MAX_HEIGHT = 600;
    private const IMAGE_JPEG_QUALITY = 78;

    public function __construct(
        private readonly BoardGameRepository $boardGameRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageReencoder $imageReencoder,
        private readonly BoardGameCsvImporter $boardGameCsvImporter,
    ) {
    }

    /**
     * Liste paginée des jeux, triée par titre, avec statut et emprunteur.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator, ReviewRepository $reviewRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $qb = $this->boardGameRepository->findAllOrderByTitleQb($search !== '' ? $search : null);

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            self::PER_PAGE
        );

        $averageRatings = [];
        $myRatings = [];
        $admin = $this->getUser();
        foreach ($pagination as $boardGame) {
            $averageRatings[$boardGame->getId()] = $reviewRepository->averageFor($boardGame);
            $myReview = $reviewRepository->findOneForUserAndGame($boardGame, $admin);
            $myRatings[$boardGame->getId()] = $myReview?->getRating();
        }

        return $this->render('admin/ludotheque/index.html.twig', [
            'pagination' => $pagination,
            'averageRatings' => $averageRatings,
            'myRatings' => $myRatings,
            'search' => $search,
        ]);
    }

    /**
     * @return array{page?: int, q?: string}
     */
    private function redirectParams(Request $request): array
    {
        $params = [];
        $page = $request->query->getInt('page', 1);
        if ($page > 1) {
            $params['page'] = $page;
        }
        $search = trim((string) $request->query->get('q', ''));
        if ($search !== '') {
            $params['q'] = $search;
        }

        return $params;
    }

    /**
     * Notation d'un jeu (1 à 5) par un administrateur, sans condition d'emprunt.
     *
     * Upsert de la Review de l'admin courant — la note entre dans la moyenne
     * affichée côté public et membre.
     */
    #[Route('/{id}/noter', name: 'rate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rate(Request $request, BoardGame $boardGame, ReviewRepository $reviewRepository): Response
    {
        $redirectParams = $this->redirectParams($request);

        if (!$this->isCsrfTokenValid('admin_rate_game' . $boardGame->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectAfterRate($request, $boardGame, $redirectParams);
        }

        $rating = $request->request->getInt('rating');
        if ($rating < 1 || $rating > 5) {
            $this->addFlash('error', 'La note doit être comprise entre 1 et 5.');

            return $this->redirectAfterRate($request, $boardGame, $redirectParams);
        }

        $admin = $this->getUser();
        $review = $reviewRepository->findOneForUserAndGame($boardGame, $admin);
        if ($review === null) {
            $review = new Review();
            $review->setBoardGame($boardGame);
            $review->setUser($admin);
            $this->entityManager->persist($review);
        }
        $review->setRating($rating);

        try {
            $this->entityManager->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            $this->addFlash('error', 'Votre note a déjà été enregistrée, veuillez réessayer.');

            return $this->redirectAfterRate($request, $boardGame, $redirectParams);
        }

        $this->addFlash('success', 'Votre note pour « ' . $boardGame->getTitle() . ' » a été enregistrée.');

        return $this->redirectAfterRate($request, $boardGame, $redirectParams);
    }

    /**
     * @param array{page?: int, q?: string} $redirectParams
     */
    private function redirectAfterRate(Request $request, BoardGame $boardGame, array $redirectParams): Response
    {
        $return = (string) ($request->request->get('return') ?: $request->query->get('return', ''));
        if ($return === 'edit') {
            return $this->redirectToRoute('app_admin_ludotheque_edit', ['id' => $boardGame->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_admin_ludotheque_index', $redirectParams, Response::HTTP_SEE_OTHER);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $boardGame = new BoardGame();
        $form = $this->createForm(BoardGameType::class, $boardGame);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form, $boardGame);
            $this->entityManager->persist($boardGame);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le jeu « ' . $boardGame->getTitle() . ' » a été ajouté à la ludothèque.');

            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/ludotheque/new.html.twig', [
            'boardGame' => $boardGame,
            'form' => $form,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Export CSV du catalogue (mêmes filtres de recherche que la liste).
     */
    #[Route('/export-csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        $search = trim((string) $request->query->get('q', ''));
        $boardGames = $this->boardGameRepository->findAllOrderByTitle($search !== '' ? $search : null);

        $statusLabels = [
            BoardGame::STATUS_AVAILABLE => 'Disponible',
            BoardGame::STATUS_PENDING => 'En attente',
            BoardGame::STATUS_LOANED => 'Emprunté',
        ];

        $response = new StreamedResponse(function () use ($boardGames, $statusLabels) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Titre', 'Catégorie', 'Joueurs max', 'Durée (min)', 'État', 'Notes', 'Statut', 'Emprunteur'], ';');

            foreach ($boardGames as $boardGame) {
                fputcsv($handle, [
                    CsvCellSanitizer::sanitize((string) $boardGame->getTitle()),
                    CsvCellSanitizer::sanitize((string) ($boardGame->getCategory() ?? '')),
                    $boardGame->getMaxPlayers() ?? '',
                    $boardGame->getDurationMinutes() ?? '',
                    CsvCellSanitizer::sanitize((string) ($boardGame->getCondition() ?? '')),
                    CsvCellSanitizer::sanitize((string) ($boardGame->getNotes() ?? '')),
                    CsvCellSanitizer::sanitize($statusLabels[$boardGame->getStatus()] ?? $boardGame->getStatus()),
                    CsvCellSanitizer::sanitize((string) ($boardGame->getBorrower()?->getUsername() ?? '')),
                ], ';');
            }

            fclose($handle);
        });

        $filename = 'ludotheque_' . date('Y-m-d') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Import CSV : crée des jeux disponibles à partir des colonnes catalogue.
     */
    #[Route('/import-csv', name: 'import_csv', methods: ['POST'])]
    public function importCsv(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('ludotheque_import_csv', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('csv_file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier CSV valide.');

            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            $this->addFlash('error', 'Le fichier CSV ne doit pas dépasser 2 Mo.');

            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        $mime = (string) $file->getMimeType();
        $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv'];
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'csv' && !\in_array($mime, $allowedMimes, true)) {
            $this->addFlash('error', 'Le fichier doit être au format CSV.');

            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        $result = $this->boardGameCsvImporter->importFromPath($file->getPathname());

        foreach ($result['created'] as $boardGame) {
            $this->entityManager->persist($boardGame);
        }

        if ($result['created'] !== []) {
            $this->entityManager->flush();
        }

        $createdCount = \count($result['created']);
        if ($createdCount > 0) {
            $this->addFlash('success', sprintf('%d jeu%s importé%s.', $createdCount, $createdCount > 1 ? 'x' : '', $createdCount > 1 ? 's' : ''));
        } elseif ($result['errors'] === []) {
            $this->addFlash('error', 'Aucun jeu à importer.');
        }

        foreach (\array_slice($result['errors'], 0, 10) as $error) {
            $this->addFlash('error', $error);
        }
        if (\count($result['errors']) > 10) {
            $this->addFlash('error', sprintf('… et %d autre%s erreur%s.', \count($result['errors']) - 10, \count($result['errors']) - 10 > 1 ? 's' : '', \count($result['errors']) - 10 > 1 ? 's' : ''));
        }

        return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, BoardGame $boardGame, ReviewRepository $reviewRepository): Response
    {
        $form = $this->createForm(BoardGameType::class, $boardGame);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form, $boardGame);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le jeu « ' . $boardGame->getTitle() . ' » a été mis à jour.');

            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        $admin = $this->getUser();
        $myReview = $reviewRepository->findOneForUserAndGame($boardGame, $admin);

        return $this->render('admin/ludotheque/edit.html.twig', [
            'boardGame' => $boardGame,
            'form' => $form,
            'averageRating' => $reviewRepository->averageFor($boardGame),
            'myRating' => $myReview?->getRating(),
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Valide une demande d'emprunt en attente : le jeu passe en emprunté
     * avec la date de retour saisie par l'admin.
     */
    #[Route('/{id}/valider', name: 'approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(Request $request, BoardGame $boardGame): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('approve' . $boardGame->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($boardGame->getStatus() !== BoardGame::STATUS_PENDING) {
            $this->addFlash('error', 'Ce jeu n\'est pas en attente de validation.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($boardGame->getBorrower() === null) {
            $this->addFlash('error', 'Emprunteur introuvable, la demande ne peut pas être validée.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        $returnDueAtRaw = $request->request->get('returnDueAt');
        if (!$returnDueAtRaw) {
            $this->addFlash('error', 'Veuillez indiquer une date de retour.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        try {
            $returnDueAt = new \DateTimeImmutable($returnDueAtRaw);
        } catch (\Exception) {
            $this->addFlash('error', 'La date de retour est invalide.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        $boardGame->setStatus(BoardGame::STATUS_LOANED);
        $boardGame->setLoanedAt(new \DateTimeImmutable());
        $boardGame->setReturnDueAt($returnDueAt);

        $loanLog = new LoanLog();
        $loanLog->setBoardGame($boardGame);
        $loanLog->setUser($boardGame->getBorrower());
        $this->entityManager->persist($loanLog);

        $this->entityManager->flush();

        $this->addFlash('success', 'L\'emprunt de « ' . $boardGame->getTitle() . ' » a été validé.');

        return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Rejette une demande d'emprunt en attente : le jeu redevient disponible.
     */
    #[Route('/{id}/rejeter', name: 'reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reject(Request $request, BoardGame $boardGame): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('reject' . $boardGame->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($boardGame->getStatus() !== BoardGame::STATUS_PENDING) {
            $this->addFlash('error', 'Ce jeu n\'est pas en attente de validation.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        $boardGame->setStatus(BoardGame::STATUS_AVAILABLE);
        $boardGame->setBorrower(null);
        $boardGame->setRequestedAt(null);
        $this->entityManager->flush();

        $this->addFlash('success', 'La demande d\'emprunt de « ' . $boardGame->getTitle() . ' » a été rejetée.');

        return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Constate le retour d'un jeu emprunté : le jeu redevient disponible.
     */
    #[Route('/{id}/retourner', name: 'return', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function return(Request $request, BoardGame $boardGame): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('return' . $boardGame->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($boardGame->getStatus() !== BoardGame::STATUS_LOANED) {
            $this->addFlash('error', 'Ce jeu n\'est pas actuellement emprunté.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        $boardGame->setStatus(BoardGame::STATUS_AVAILABLE);
        $boardGame->setBorrower(null);
        $boardGame->setRequestedAt(null);
        $boardGame->setLoanedAt(null);
        $boardGame->setReturnDueAt(null);
        $this->entityManager->flush();

        $this->addFlash('success', 'Le retour de « ' . $boardGame->getTitle() . ' » a été enregistré.');

        return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Supprime définitivement un jeu du catalogue (uniquement si disponible).
     *
     * Les notes (Review) et le journal d'emprunts (LoanLog) sont effacés
     * automatiquement via ON DELETE CASCADE.
     */
    #[Route('/{id}/supprimer', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, BoardGame $boardGame): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $boardGame->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($boardGame->getStatus() !== BoardGame::STATUS_AVAILABLE) {
            $this->addFlash('error', 'Ce jeu est en attente ou en cours d\'emprunt, il doit être disponible pour être supprimé.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        $title = $boardGame->getTitle();
        $this->deleteImageFile($boardGame->getImage());
        $this->entityManager->remove($boardGame);
        $this->entityManager->flush();
        $this->addFlash('success', 'Le jeu « ' . $title . ' » a été supprimé.');

        return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
    }

    private function handleImageUpload(FormInterface $form, BoardGame $boardGame): void
    {
        $imageFile = $form->get('imageFile')->getData();
        if (!$imageFile) {
            return;
        }

        $safeFilename = 'game-' . uniqid();
        $newFilename = $this->imageReencoder->reencode(
            $imageFile,
            $this->getParameter('board_games_images_directory'),
            $safeFilename,
            self::IMAGE_MAX_WIDTH,
            self::IMAGE_MAX_HEIGHT,
            self::IMAGE_JPEG_QUALITY,
        );

        if ($newFilename === null) {
            $this->addFlash('error', 'Erreur lors du traitement de l\'image.');

            return;
        }

        $this->deleteImageFile($boardGame->getImage());
        $boardGame->setImage($newFilename);
    }

    private function deleteImageFile(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        $safeName = basename($filename);
        $filePath = $this->getParameter('board_games_images_directory') . '/' . $safeName;
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
}
