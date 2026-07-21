<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use App\Security\HtmlSanitizer;
use App\Service\ImageReencoder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/articles', name: 'app_admin_article_')]
class ArticleController extends AbstractController
{
    use BulkSelectionTrait;

    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly ImageReencoder $imageReencoder,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $articles = $this->articleRepository->findAllOrderByPosition();

        return $this->render('admin/article/index.html.twig', [
            'articles' => $articles,
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $article = new Article();
        
        // Trouver la position max par défaut
        $maxPosition = $this->articleRepository->createQueryBuilder('a')
            ->select('MAX(a.position)')
            ->getQuery()
            ->getSingleScalarResult();
        $article->setPosition((int) $maxPosition + 1);

        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setContent($this->htmlSanitizer->sanitize($article->getContent()));
            $this->handleImageUpload($form, $article);
            $this->handleGalleryUpload($form, $article);
            $this->entityManager->persist($article);
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'article « ' . $article->getTitle() . ' » a été ajouté.');

            return $this->redirectToRoute('app_admin_article_index');
        }

        return $this->render('admin/article/new.html.twig', [
            'article' => $article,
            'form' => $form,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article): Response
    {
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer les suppressions d'images de la galerie
            $deleteImages = $request->request->all('delete_gallery_images');
            $currentGallery = $article->getGallery() ?? [];
            foreach ($deleteImages as $delImg) {
                $safeName = basename((string) $delImg);
                if (!preg_match('/^[a-zA-Z0-9._-]+$/', $safeName)) {
                    continue;
                }
                if (($key = array_search($safeName, $currentGallery, true)) !== false) {
                    unset($currentGallery[$key]);
                    $filePath = $this->getParameter('articles_images_directory') . '/' . $safeName;
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
            $article->setGallery(array_values($currentGallery));

            $article->setContent($this->htmlSanitizer->sanitize($article->getContent()));
            $this->handleImageUpload($form, $article);
            $this->handleGalleryUpload($form, $article);
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'article « ' . $article->getTitle() . ' » a été mis à jour.');

            return $this->redirectToRoute('app_admin_article_index');
        }

        return $this->render('admin/article/edit.html.twig', [
            'article' => $article,
            'form' => $form,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/toggle', name: 'toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, Article $article): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle' . $article->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_article_index');
        }

        $article->setActive(!$article->isActive());
        $this->entityManager->flush();

        $status = $article->isActive() ? 'activé' : 'suspendu';
        $this->addFlash('success', 'L\'article « ' . $article->getTitle() . ' » a été ' . $status . '.');

        return $this->redirectToRoute('app_admin_article_index');
    }

    #[Route('/suspendre-selection', name: 'suspend_bulk', methods: ['POST'])]
    public function suspendBulk(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('suspend_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_article_index');
        }

        $ids = $this->parseBulkIds($request);
        if ($ids === []) {
            $this->addFlash('error', 'Aucun article sélectionné.');

            return $this->redirectToRoute('app_admin_article_index');
        }

        $articles = $this->articleRepository->findBy(['id' => $ids]);
        if ($articles === []) {
            $this->addFlash('error', 'Aucun article trouvé.');

            return $this->redirectToRoute('app_admin_article_index');
        }

        $anyActive = false;
        foreach ($articles as $article) {
            if ($article->isActive()) {
                $anyActive = true;
                break;
            }
        }
        $targetActive = !$anyActive;
        foreach ($articles as $article) {
            $article->setActive($targetActive);
        }
        $this->entityManager->flush();

        $status = $targetActive ? 'activé' : 'suspendu';
        $count = \count($articles);
        $this->addFlash('success', $count . ' article' . ($count > 1 ? 's' : '') . ' ' . $status . ($count > 1 ? 's' : '') . '.');

        return $this->redirectToRoute('app_admin_article_index');
    }

    #[Route('/supprimer-selection', name: 'delete_bulk', methods: ['POST'])]
    public function deleteBulk(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_article_index');
        }

        $ids = $this->parseBulkIds($request);
        if ($ids === []) {
            $this->addFlash('error', 'Aucun article sélectionné.');

            return $this->redirectToRoute('app_admin_article_index');
        }

        $articles = $this->articleRepository->findBy(['id' => $ids]);
        foreach ($articles as $article) {
            $this->entityManager->remove($article);
        }

        if ($articles !== []) {
            $this->entityManager->flush();
        }

        $count = \count($articles);
        if ($count === 0) {
            $this->addFlash('error', 'Aucun article à supprimer.');
        } else {
            $this->addFlash('success', $count . ' article' . ($count > 1 ? 's' : '') . ' supprimé' . ($count > 1 ? 's' : '') . '.');
        }

        return $this->redirectToRoute('app_admin_article_index');
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Article $article): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $article->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_article_index');
        }

        $title = $article->getTitle();
        $this->entityManager->remove($article);
        $this->entityManager->flush();
        $this->addFlash('success', 'L\'article « ' . $title . ' » a été supprimé.');

        return $this->redirectToRoute('app_admin_article_index');
    }

    private function handleImageUpload(FormInterface $form, Article $article): void
    {
        $imageFile = $form->get('imageFile')->getData();
        if ($imageFile) {
            $safeFilename = 'article-' . uniqid();
            $newFilename = $this->imageReencoder->reencode(
                $imageFile,
                $this->getParameter('articles_images_directory'),
                $safeFilename,
            );

            if ($newFilename === null) {
                $this->addFlash('error', 'Erreur lors du traitement de l\'image.');

                return;
            }

            $article->setImage($newFilename);
        }
    }

    private function handleGalleryUpload(FormInterface $form, Article $article): void
    {
        $galleryFiles = $form->get('galleryFiles')->getData();
        if ($galleryFiles) {
            $currentGallery = $article->getGallery() ?? [];
            foreach ($galleryFiles as $file) {
                $safeFilename = 'gallery-' . uniqid();
                $newFilename = $this->imageReencoder->reencode(
                    $file,
                    $this->getParameter('articles_images_directory'),
                    $safeFilename,
                );

                if ($newFilename === null) {
                    $this->addFlash('error', 'Erreur lors du traitement d\'une image de la galerie.');
                    continue;
                }

                $currentGallery[] = $newFilename;
            }
            $article->setGallery($currentGallery);
        }
    }
}
