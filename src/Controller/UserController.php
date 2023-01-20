<?php

namespace App\Controller;

use App\Entity\Rating;
use App\Entity\User;
use App\Form\UserChangePasswordType;
use App\Form\UserEditType;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/user')]
class UserController extends AbstractController
{
    private const DEFAULT_RESET_TMP_PASSWORD = "sonic_the_hedgehog";

    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $users = $entityManager
            ->getRepository(User::class)
            ->findAll();

        return $this->render('user/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRegisterDate(new \DateTime());
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                )
            );
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(EntityManagerInterface $entityManager, Request $request, PaginatorInterface $paginator): Response
    {
        // retrieve user
        $user = $entityManager->getRepository(User::class)->find($request->get('id', $this->getUser()->getId()));

        // ratings
        $ratings = $entityManager->getRepository(Rating::class)->findBy(array('user' => $user), array('date' => 'DESC'));

        // get series from id in request
        $pagination = $paginator->paginate(
            $user->getSeries(), /* query NOT result */
            $request->query->getInt('page', 1), /*page number*/
            10 /*limit per page*/
        );

        return $this->render('user/show.html.twig', [
            'series' => $pagination,
            'user' => $user,
            'ratings' => $ratings
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $actualUser = $this->getUser();
        if (!$actualUser->isAdmin() and $actualUser->getId() != $user->getId()) {
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(UserEditType::class, $user, [
            'admin_triggered' => $this->getUser()->isAdmin(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password; if it's not an admin editing an user
            if ($this->getUser()->isAdmin()) {
                $passResetRequest = $form->get('tmpPassword')->getData();
                if ($passResetRequest) {
                    // by default, user's password is changed to a const to force him/her to change it
                    $user->setPassword(
                        $userPasswordHasher->hashPassword(
                            $user,
                            UserController::DEFAULT_RESET_TMP_PASSWORD)
                    );
                }
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('user/edit.html.twig', [
            'user' => $user,
            'userTypeForm' => $form,
        ]);
    }

    #[Route('/{id}/edit/password', name: 'app_user_editpass', methods: ['GET', 'POST'])]
    public function editPassword(Request $request, User $user, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager)
    {
        $changePasswordForm = $this->createForm(UserChangePasswordType::class, $user, [
            'old_hashed_pass' => $this->getUser()->getPassword(),
        ]);
        $changePasswordForm->handleRequest($request);
        if ($changePasswordForm->isSubmitted() && $changePasswordForm->isValid()) {
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $changePasswordForm->get('password')->getData()
                )
            );
            $entityManager->flush();

            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('user/edit_password.html.twig', [
            'user' => $user,
            'passForm' => $changePasswordForm,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $actualUser = $this->getUser();

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $remove_rating = $entityManager->createQueryBuilder()
                ->delete(Rating::class . ' r')
                ->where('r.user = (:id)')
                ->setParameter('id', $user->getId());


            $remove_rating->getQuery()->execute();
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
