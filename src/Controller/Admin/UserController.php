<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/utilisateurs', name: 'app_admin_user_')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $role = $request->query->get('role');
        $search = $request->query->get('q');

        $fromDate = $from ? (\DateTimeImmutable::createFromFormat('Y-m-d', $from) ?: null) : null;
        $toDate = $to ? (\DateTimeImmutable::createFromFormat('Y-m-d', $to) ?: null) : null;

        return $this->render('admin/user/index.html.twig', [
            'users' => $this->userRepository->findAllFiltered($fromDate, $toDate, $role, $search),
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
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            $this->addFlash('error', 'Seul un super administrateur peut modifier un autre super administrateur.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        $form = $this->createForm(UserType::class, $user, ['is_edit' => true, 'is_super_admin' => $this->isGranted('ROLE_SUPER_ADMIN')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
    public function changePassword(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('change_password' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
        }

        $password = $request->request->get('new_password', '');
        $confirm  = $request->request->get('confirm_password', '');

        if (mb_strlen($password) < 12) {
            $this->addFlash('error', 'Le mot de passe doit faire au moins 12 caractères.');
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

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            $this->addFlash('error', 'Seul un super administrateur peut suspendre un autre super administrateur.');
            return $this->redirectToRoute('app_admin_user_index');
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
            fputcsv($handle, ['Nom d\'utilisateur', 'Email', 'Rôles', 'Suspendu', 'Inscrit le'], ';');

            foreach ($users as $user) {
                $roles = array_filter($user->getRoles(), fn(string $r) => $r !== 'ROLE_USER');
                fputcsv($handle, [
                    $user->getUsername(),
                    $user->getEmail(),
                    implode(', ', $roles) ?: 'Utilisateur',
                    $user->isSuspended() ? 'Oui' : 'Non',
                    $user->getCreatedAt()?->format('d/m/Y H:i') ?? '',
                ], ';');
            }

            fclose($handle);
        });

        $filename = 'utilisateurs_' . date('Y-m-d') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/import-csv', name: 'import_csv', methods: ['POST'])]
    public function importCsv(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('import_users', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        $file = $request->files->get('csv_file');
        if (!$file || $file->getClientOriginalExtension() !== 'csv') {
            $this->addFlash('error', 'Veuillez fournir un fichier CSV valide.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        $handle = fopen($file->getPathname(), 'r');
        // Skip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            fclose($handle);
            $this->addFlash('error', 'Fichier CSV vide ou mal formaté.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        $created = 0;
        $skipped = 0;
        $lineNumber = 1;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;
            if (count($row) < 2) {
                $skipped++;
                continue;
            }

            $username = trim($row[0] ?? '');
            $email = trim($row[1] ?? '');

            if (!$username || !$email) {
                $skipped++;
                continue;
            }

            // Skip if user already exists
            $existing = $this->userRepository->findOneBy(['email' => $email]);
            if ($existing) {
                $skipped++;
                continue;
            }

            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);

            // Parse roles if provided — seuls les SUPER_ADMIN peuvent attribuer des rôles admin
            $rolesStr = trim($row[2] ?? '');
            if ($rolesStr && $rolesStr !== 'Utilisateur' && $this->isGranted('ROLE_SUPER_ADMIN')) {
                $allowedRoles = ['ROLE_ADMIN', 'ROLE_USER', 'ROLE_SUPER_ADMIN'];
                $roles = array_values(array_filter(
                    array_map('trim', explode(',', $rolesStr)),
                    fn(string $r) => in_array($r, $allowedRoles, true)
                ));
                $user->setRoles($roles);
            }

            $user->setIsVerified(true);

            // Generate a random password (user will need to reset it)
            $randomPassword = bin2hex(random_bytes(16));
            $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

            $this->entityManager->persist($user);
            $created++;
        }

        fclose($handle);
        $this->entityManager->flush();

        $this->addFlash('success', $created . ' utilisateur(s) importé(s), ' . $skipped . ' ignoré(s).');

        return $this->redirectToRoute('app_admin_user_index');
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            $this->addFlash('error', 'Seul un super administrateur peut supprimer un autre super administrateur.');
            return $this->redirectToRoute('app_admin_user_index');
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
}
