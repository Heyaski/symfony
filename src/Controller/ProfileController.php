<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\StockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Portfolio;
use Doctrine\ORM\EntityManagerInterface;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(UserRepository $userRepository, StockRepository $stockRepository): Response
    {
        $user = $this->getUser();
        $users = $userRepository->findAll();
        $stocks = $stockRepository->findAll();

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'users' => $users,
            'stocks' => $stocks,
            'stock' => $stocks[0] // Добавьте переменную stock
        ]);
    }

    #[Route('/grant_admin_role', name: 'grant_admin_role', methods: ['POST'])]
    public function grantAdminRole(Request $request, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $userId = $request->request->get('user_id');
        $user = $userRepository->find($userId);

        if ($user) {
            $userRepository->grantAdminRole($user);
            $this->addFlash('success', 'Role ROLE_ADMIN granted to user.');
        } else {
            $this->addFlash('error', 'User not found.');
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/create_portfolio', name: 'create_portfolio', methods: ['POST'])]
    public function createPortfolio(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (count($user->getPortfolios()) >= 5) {
            $this->addFlash('error', 'Вы не можете создать больше 5 портфелей.');
            return $this->redirectToRoute('app_profile');
        }

        $portfolio = new Portfolio();
        $portfolio->setUserId($user);
        $portfolio->setBalance(0);

        $entityManager->persist($portfolio);
        $entityManager->flush();

        $this->addFlash('success', 'Новый портфель создан.');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/delete_portfolio', name: 'delete_portfolio', methods: ['POST'])]
    public function deletePortfolio(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $portfolioId = $request->request->get('portfolio_id');
        $portfolio = $entityManager->getRepository(Portfolio::class)->find($portfolioId);

        if ($portfolio && $portfolio->getUserId() === $user) {
            $entityManager->remove($portfolio);
            $entityManager->flush();
            $this->addFlash('success', 'Портфель удален.');
        } else {
            $this->addFlash('error', 'Портфель не найден или не принадлежит текущему пользователю.');
        }

        return $this->redirectToRoute('app_profile');
    }
}