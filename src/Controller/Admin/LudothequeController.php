<?php

namespace App\Controller\Admin;

use App\Entity\BoardGame;
use App\Entity\LoanLog;
use App\Form\BoardGameType;
use App\Repository\BoardGameRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CRUD admin de la ludothèque + workflow d'approbation des emprunts.
 *
 * Un membre demande un jeu depuis /mon-espace (statut -> pending), puis un
 * admin valide (avec une date de retour) ou rejette la demande, et constate
 * le retour du jeu une fois emprunté. Seul un admin peut marquer un retour.
 */
#[Route('/admin/ludotheque', name: 'app_admin_ludotheque_')]
class LudothequeController extends AbstractController
{
    public function __construct(
        private readonly BoardGameRepository $boardGameRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Liste paginée des jeux, triée par titre, avec statut et emprunteur.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator, ReviewRepository $reviewRepository): Response
    {
        $qb = $this->boardGameRepository->findAllOrderByTitleQb();

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            15
        );

        $averageRatings = [];
        foreach ($pagination as $boardGame) {
            $averageRatings[$boardGame->getId()] = $reviewRepository->averageFor($boardGame);
        }

        return $this->render('admin/ludotheque/index.html.twig', [
            'pagination' => $pagination,
            'averageRatings' => $averageRatings,
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $boardGame = new BoardGame();
        $form = $this->createForm(BoardGameType::class, $boardGame);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, BoardGame $boardGame): Response
    {
        $form = $this->createForm(BoardGameType::class, $boardGame);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Le jeu « ' . $boardGame->getTitle() . ' » a été mis à jour.');

            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/ludotheque/edit.html.twig', [
            'boardGame' => $boardGame,
            'form' => $form,
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
     * Archive un jeu du catalogue (uniquement si disponible).
     *
     * Remplace l'ancienne suppression définitive : le jeu et son historique
     * (notes, journal d'emprunts) sont conservés en base mais n'apparaissent
     * plus dans les listes admin/membre.
     */
    #[Route('/{id}/archiver', name: 'archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(Request $request, BoardGame $boardGame): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('archive' . $boardGame->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($boardGame->getStatus() !== BoardGame::STATUS_AVAILABLE) {
            $this->addFlash('error', 'Ce jeu est en attente ou en cours d\'emprunt, il doit être disponible pour être supprimé.');
            return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
        }

        $title = $boardGame->getTitle();
        $boardGame->setArchived(true);
        $this->entityManager->flush();
        $this->addFlash('success', 'Le jeu « ' . $title . ' » a été archivé.');

        return $this->redirectToRoute('app_admin_ludotheque_index', [], Response::HTTP_SEE_OTHER);
    }
}
