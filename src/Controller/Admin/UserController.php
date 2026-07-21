<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Security\AdminUserAuthorization;
use App\Util\CsvCellSanitizer;
use App\Validator\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/utilisateurs', name: 'app_admin_user_')]
class UserController extends AbstractController
{
    use BulkSelectionTrait;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $role = $request->query->get('role');
        $search = $request->query->get('q');

        $fromDate = $from ? (\DateTimeImmutable::createFromFormat('Y-m-d', $from) ?: null) : null;
        $toDate = $to ? (\DateTimeImmutable::createFromFormat('Y-m-d', $to) ?: null) : null;

        $qb = $this->userRepository->findAllFilteredQb($fromDate, $toDate, $role, $search);

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            16
        );

        return $this->render('admin/user/index.html.twig', [
            'pagination' => $pagination,
            'from' => $from,
            'to' => $to,
            'role' => $role,
            'search' => $search,
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_super_admin' => $this->isGranted('ROLE_SUPER_ADMIN')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->sanitizeUserRoles($user);
            $user->setPassword($this->passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $user->setIsVerified(true);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->addFlash('success', 'Utilisateur « ' . $user->getEmail() . ' » créé.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        if ($response = $this->denyUnlessCanManagePrivilegedUser($user)) {
            return $response;
        }

        $form = $this->createForm(UserType::class, $user, ['is_edit' => true, 'is_super_admin' => $this->isGranted('ROLE_SUPER_ADMIN')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->sanitizeUserRoles($user);
            $this->entityManager->flush();
            $this->addFlash('success', 'Utilisateur mis à jour.');

            return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/changer-mot-de-passe', name: 'change_password', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changePassword(Request $request, User $user, ValidatorInterface $validator): Response
    {
        if ($response = $this->denyUnlessCanManagePrivilegedUser($user)) {
            return $response;
        }

        if (!$this->isCsrfTokenValid('change_password' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
        }

        $password = (string) $request->request->get('new_password', '');
        $confirm  = (string) $request->request->get('confirm_password', '');

        foreach ($validator->validate($password, PasswordPolicy::constraints()) as $violation) {
            $this->addFlash('error', $violation->getMessage());
            return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
        }

        if ($password !== $confirm) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->flush();

        $this->addFlash('success', 'Mot de passe modifié avec succès.');
        return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
    }

    #[Route('/{id}/suspendre', name: 'suspend', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function suspend(Request $request, User $user): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas suspendre votre propre compte.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        if ($response = $this->denyUnlessCanManagePrivilegedUser($user)) {
            return $response;
        }

        if (!$this->isCsrfTokenValid('suspend' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        $user->setSuspended(!$user->isSuspended());
        $this->entityManager->flush();

        $action = $user->isSuspended() ? 'suspendu' : 'réactivé';
        $this->addFlash('success', 'Utilisateur « ' . $user->getEmail() . ' » ' . $action . '.');

        return $this->redirectToRoute('app_admin_user_index');
    }

    #[Route('/export-csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $role = $request->query->get('role');
        $search = $request->query->get('q');

        $fromDate = $from ? (\DateTimeImmutable::createFromFormat('Y-m-d', $from) ?: null) : null;
        $toDate = $to ? (\DateTimeImmutable::createFromFormat('Y-m-d', $to) ?: null) : null;

        $users = $this->userRepository->findAllFiltered($fromDate, $toDate, $role, $search);

        $response = new StreamedResponse(function () use ($users) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nom d\'utilisateur', 'Email', 'Rôles', 'Suspendu', 'Inscrit le'], ';', '"', '\\');

            foreach ($users as $user) {
                $roles = array_filter($user->getRoles(), fn(string $r) => $r !== 'ROLE_USER');
                fputcsv($handle, [
                    CsvCellSanitizer::sanitize((string) $user->getUsername()),
                    CsvCellSanitizer::sanitize((string) $user->getEmail()),
                    CsvCellSanitizer::sanitize(implode(', ', $roles) ?: 'Utilisateur'),
                    $user->isSuspended() ? 'Oui' : 'Non',
                    $user->getCreatedAt()?->format('d/m/Y H:i') ?? '',
                ], ';', '"', '\\');
            }

            fclose($handle);
        });

        $filename = 'utilisateurs_' . date('Y-m-d') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/suspendre-selection', name: 'suspend_bulk', methods: ['POST'])]
    public function suspendBulk(Request $request): Response
    {
        $redirectParams = $this->indexQueryParams($request);

        if (!$this->isCsrfTokenValid('suspend_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_user_index', $redirectParams);
        }

        $ids = $this->parseBulkIds($request);
        if ($ids === []) {
            $this->addFlash('error', 'Aucun utilisateur sélectionné.');

            return $this->redirectToRoute('app_admin_user_index', $redirectParams);
        }

        $current = $this->getUser();
        $users = $this->userRepository->findBy(['id' => $ids]);
        $count = 0;
        $skipped = 0;

        foreach ($users as $user) {
            if ($user === $current) {
                ++$skipped;
                continue;
            }
            if (AdminUserAuthorization::isPrivilegedAccount($user) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
                ++$skipped;
                continue;
            }
            $user->setSuspended(!$user->isSuspended());
            ++$count;
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $this->addFlash('success', $count . ' utilisateur' . ($count > 1 ? 's' : '') . ' mis à jour.');
        }
        if ($skipped > 0) {
            $this->addFlash('warning', $skipped . ' utilisateur' . ($skipped > 1 ? 's' : '') . ' ignoré' . ($skipped > 1 ? 's' : '') . ' (droits insuffisants ou compte courant).');
        }
        if ($count === 0 && $skipped === 0) {
            $this->addFlash('error', 'Aucun utilisateur à suspendre.');
        }

        return $this->redirectToRoute('app_admin_user_index', $redirectParams);
    }

    #[Route('/supprimer-selection', name: 'delete_bulk', methods: ['POST'])]
    public function deleteBulk(Request $request): Response
    {
        $redirectParams = $this->indexQueryParams($request);

        if (!$this->isCsrfTokenValid('delete_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_user_index', $redirectParams);
        }

        $ids = $this->parseBulkIds($request);
        if ($ids === []) {
            $this->addFlash('error', 'Aucun utilisateur sélectionné.');

            return $this->redirectToRoute('app_admin_user_index', $redirectParams);
        }

        $current = $this->getUser();
        $users = $this->userRepository->findBy(['id' => $ids]);
        $count = 0;
        $skipped = 0;

        foreach ($users as $user) {
            if ($user === $current) {
                ++$skipped;
                continue;
            }
            if (AdminUserAuthorization::isPrivilegedAccount($user) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
                ++$skipped;
                continue;
            }
            $this->entityManager->remove($user);
            ++$count;
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $this->addFlash('success', $count . ' utilisateur' . ($count > 1 ? 's' : '') . ' supprimé' . ($count > 1 ? 's' : '') . '.');
        }
        if ($skipped > 0) {
            $this->addFlash('warning', $skipped . ' utilisateur' . ($skipped > 1 ? 's' : '') . ' ignoré' . ($skipped > 1 ? 's' : '') . ' (droits insuffisants ou compte courant).');
        }
        if ($count === 0 && $skipped === 0) {
            $this->addFlash('error', 'Aucun utilisateur à supprimer.');
        }

        return $this->redirectToRoute('app_admin_user_index', $redirectParams);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        if ($response = $this->denyUnlessCanManagePrivilegedUser($user)) {
            return $response;
        }

        if (!$this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        $email = $user->getEmail();
        $this->entityManager->remove($user);
        $this->entityManager->flush();
        $this->addFlash('success', 'Utilisateur « ' . $email . ' » supprimé.');

        return $this->redirectToRoute('app_admin_user_index');
    }

    /**
     * Empêche un admin simple d'attribuer des rôles privilégiés via manipulation du formulaire.
     */
    private function sanitizeUserRoles(User $user): void
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $user->setRoles(array_values(array_filter(
            $user->getRoles(),
            fn(string $role) => $role === 'ROLE_USER',
        )));
    }

    private function denyUnlessCanManagePrivilegedUser(User $user): ?Response
    {
        if (AdminUserAuthorization::isPrivilegedAccount($user) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            $this->addFlash('error', 'Seul un super administrateur peut gérer un compte administrateur.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function indexQueryParams(Request $request): array
    {
        $params = [];
        foreach (['from', 'to', 'role', 'q'] as $key) {
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
