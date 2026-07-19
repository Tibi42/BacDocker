<?php

namespace App\Controller;

use App\Repository\BoardGameRepository;
use App\Repository\ReviewRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catalogue public de la ludothèque.
 *
 * Consultation ouverte à tous ; les membres connectés peuvent y demander
 * un emprunt. L'historique personnel et les notations restent dans /mon-espace.
 */
class LudothequeController extends AbstractController
{
    private const PER_PAGE = 15;

    #[Route('/ludotheque', name: 'app_ludotheque')]
    public function index(
        Request $request,
        BoardGameRepository $boardGameRepository,
        ReviewRepository $reviewRepository,
        PaginatorInterface $paginator,
    ): Response {
        $search = trim((string) $request->query->get('q', ''));
        $pagination = $paginator->paginate(
            $boardGameRepository->findAllOrderByTitleQb($search !== '' ? $search : null),
            $request->query->getInt('page', 1),
            self::PER_PAGE,
        );

        $averageRatings = [];
        foreach ($pagination as $boardGame) {
            $averageRatings[$boardGame->getId()] = $reviewRepository->averageFor($boardGame);
        }

        $user = $this->getUser();
        $activeBoardGame = $user !== null
            ? $boardGameRepository->findActiveForUser($user)
            : null;

        return $this->render('ludotheque/index.html.twig', [
            'pagination' => $pagination,
            'averageRatings' => $averageRatings,
            'activeBoardGame' => $activeBoardGame,
            'search' => $search,
        ]);
    }

    #[Route('/ludotheque/suggest', name: 'app_ludotheque_suggest', methods: ['GET'])]
    public function suggest(Request $request, BoardGameRepository $boardGameRepository): JsonResponse
    {
        $term = trim((string) $request->query->get('q', ''));

        return $this->json([
            'suggestions' => $boardGameRepository->findSuggestions($term),
        ]);
    }
}
