<?php

namespace App\Controller\Admin;

use App\Entity\CarouselSlide;
use App\Form\CarouselSlideType;
use App\Repository\CarouselSlideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/carousel', name: 'app_admin_carousel_')]
class CarouselController extends AbstractController
{
    use BulkSelectionTrait;

    public function __construct(
        private readonly CarouselSlideRepository $carouselSlideRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $slides = $this->carouselSlideRepository->findAllOrderByPosition();

        return $this->render('admin/carousel/index.html.twig', [
            'slides' => $slides,
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $slide = new CarouselSlide();
        $maxPosition = $this->carouselSlideRepository->createQueryBuilder('c')
            ->select('MAX(c.position)')
            ->getQuery()
            ->getSingleScalarResult();
        $slide->setPosition((int) $maxPosition + 1);
        $form = $this->createForm(CarouselSlideType::class, $slide);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($slide);
            $this->entityManager->flush();
            $this->addFlash('success', 'La slide « ' . $slide->getTitle() . ' » a été ajoutée.');

            return $this->redirectToRoute('app_admin_carousel_index');
        }

        return $this->render('admin/carousel/new.html.twig', [
            'slide' => $slide,
            'form' => $form,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, CarouselSlide $slide): Response
    {
        $form = $this->createForm(CarouselSlideType::class, $slide);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'La slide « ' . $slide->getTitle() . ' » a été mise à jour.');

            return $this->redirectToRoute('app_admin_carousel_index');
        }

        return $this->render('admin/carousel/edit.html.twig', [
            'slide' => $slide,
            'form' => $form,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/toggle', name: 'toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, CarouselSlide $slide): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle' . $slide->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_carousel_index');
        }

        $slide->setActive(!$slide->isActive());
        $this->entityManager->flush();

        $status = $slide->isActive() ? 'activée' : 'suspendue';
        $this->addFlash('success', 'La slide « ' . $slide->getTitle() . ' » a été ' . $status . '.');

        return $this->redirectToRoute('app_admin_carousel_index');
    }

    #[Route('/suspendre-selection', name: 'suspend_bulk', methods: ['POST'])]
    public function suspendBulk(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('suspend_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_carousel_index');
        }

        $ids = $this->parseBulkIds($request);
        if ($ids === []) {
            $this->addFlash('error', 'Aucune slide sélectionnée.');

            return $this->redirectToRoute('app_admin_carousel_index');
        }

        $slides = $this->carouselSlideRepository->findBy(['id' => $ids]);
        if ($slides === []) {
            $this->addFlash('error', 'Aucune slide trouvée.');

            return $this->redirectToRoute('app_admin_carousel_index');
        }

        $anyActive = false;
        foreach ($slides as $slide) {
            if ($slide->isActive()) {
                $anyActive = true;
                break;
            }
        }
        $targetActive = !$anyActive;
        foreach ($slides as $slide) {
            $slide->setActive($targetActive);
        }
        $this->entityManager->flush();

        $status = $targetActive ? 'activée' : 'suspendue';
        $count = \count($slides);
        $this->addFlash('success', $count . ' slide' . ($count > 1 ? 's' : '') . ' ' . $status . ($count > 1 ? 's' : '') . '.');

        return $this->redirectToRoute('app_admin_carousel_index');
    }

    #[Route('/supprimer-selection', name: 'delete_bulk', methods: ['POST'])]
    public function deleteBulk(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_carousel_index');
        }

        $ids = $this->parseBulkIds($request);
        if ($ids === []) {
            $this->addFlash('error', 'Aucune slide sélectionnée.');

            return $this->redirectToRoute('app_admin_carousel_index');
        }

        $slides = $this->carouselSlideRepository->findBy(['id' => $ids]);
        foreach ($slides as $slide) {
            $this->entityManager->remove($slide);
        }

        if ($slides !== []) {
            $this->entityManager->flush();
        }

        $count = \count($slides);
        if ($count === 0) {
            $this->addFlash('error', 'Aucune slide à supprimer.');
        } else {
            $this->addFlash('success', $count . ' slide' . ($count > 1 ? 's' : '') . ' supprimée' . ($count > 1 ? 's' : '') . '.');
        }

        return $this->redirectToRoute('app_admin_carousel_index');
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, CarouselSlide $slide): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $slide->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_carousel_index');
        }

        $title = $slide->getTitle();
        $this->entityManager->remove($slide);
        $this->entityManager->flush();
        $this->addFlash('success', 'La slide « ' . $title . ' » a été supprimée.');

        return $this->redirectToRoute('app_admin_carousel_index');
    }
}
