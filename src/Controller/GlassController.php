<?php

namespace App\Controller;

use App\Entity\Application;
use App\Entity\Stock;
use App\Enums\ActionEnum;
use App\Form\ApplicationType;
use App\Form\DTO\CreateApplicationRequest;
use App\Repository\StockRepository;
use App\Repository\ApplicationRepository;
use App\Repository\UserRepository;
use App\Service\DealService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

class GlassController extends AbstractController
{
    public function __construct(
        private readonly StockRepository $stockRepository,
        private readonly UserRepository $userRepository,
        private readonly ApplicationRepository $applicationRepository,
        private readonly DealService $dealService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/glass/stock/{stockId}', name: 'app_stock_glass', methods: ['GET'])]
    public function getStockGlass(int $stockId): Response
    {
        $stock = $this->stockRepository->findById($stockId);
        if ($stock === null) {
            throw $this->createNotFoundException("Stock not found");
        }
        return $this->render('glass/stock_glass_index.html.twig', [
            'stock' => $stock,
            'BUY' => ActionEnum::BUY,
            'SELL' => ActionEnum::SELL,
        ]);
    }

    #[Route('/glass/stock/{stockId}/application', name: 'app_stock_glass_create_application', methods: ['POST'])]
    public function createApplication(int $stockId, Request $request): Response
    {
        // Используем сервис логирования
        $this->logger->debug('Received create application request', [
            'stockId' => $stockId,
            'request_data' => $request->request->all()
        ]);

        $stock = $this->stockRepository->find($stockId);
        if (!$stock) {
            return new Response("Stock not found", Response::HTTP_NOT_FOUND);
        }

        // Проверяем все необходимые параметры
        $requiredParams = ['user_id', 'quantity', 'price', 'action', 'portfolio_id'];
        foreach ($requiredParams as $param) {
            if (!$request->request->has($param)) {
                return new Response("Missing required parameter: $param", Response::HTTP_BAD_REQUEST);
            }
        }

        $userId = $request->request->get('user_id');
        if (!$userId) {
            return new Response("User ID is missing", Response::HTTP_BAD_REQUEST);
        }

        $quantity = $request->request->get('quantity');
        $price = $request->request->get('price');
        $action = ActionEnum::from(strtolower($request->request->get('action')));
        $user = $this->userRepository->find($userId);

        if (!$user) {
            return new Response("User not found", Response::HTTP_NOT_FOUND);
        }

        // Изменяем получение портфеля
        $portfolioId = $request->request->get('portfolio_id');
        $portfolio = null;
        foreach ($user->getPortfolios() as $p) {
            if ($p->getId() == $portfolioId) {
                $portfolio = $p;
                break;
            }
        }

        if (!$portfolio) {
            $this->addFlash('error', 'Portfolio not found');
            return $this->redirectToRoute('app_profile');
        }

        if ($action === ActionEnum::BUY) {
            $totalCost = $quantity * $price;
            if ($portfolio->getBalance() < $totalCost) {
                $this->addFlash('error', sprintf(
                    'Недостаточно средств для покупки. Требуется: %s₽, доступно: %s₽',
                    $totalCost,
                    $portfolio->getBalance()
                ));
                return $this->redirectToRoute('app_profile');
            }
        } elseif ($action === ActionEnum::SELL) {
            $hasEnoughStocks = false;
            foreach ($portfolio->getDepositaries() as $depositary) {
                if ($depositary->getStock() === $stock && $depositary->getQuantity() >= $quantity) {
                    $hasEnoughStocks = true;
                    break;
                }
            }
            if (!$hasEnoughStocks) {
                $available = 0;
                foreach ($portfolio->getDepositaries() as $depositary) {
                    if ($depositary->getStock() === $stock) {
                        $available = $depositary->getQuantity();
                        break;
                    }
                }
                $this->addFlash('error', sprintf(
                    'Недостаточно акций для продажи. Требуется: %d шт., доступно: %d шт.',
                    $quantity,
                    $available
                ));
                return $this->redirectToRoute('app_profile');
            }
        }

        $application = new Application();
        $application->setStock($stock);
        $application->setQuantity($quantity);
        $application->setAction($action);
        $application->setPrice($price);
        $application->setUser($user);

        try {
            $appropriateApplication = $this->dealService->findAppropriateApplication($application);

            if ($appropriateApplication) {
                try {
                    $this->dealService->executeDeal($application, $appropriateApplication);
                    $this->addFlash('success', 'Сделка успешно выполнена');
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            } else {
                $this->applicationRepository->saveApplication($application);
                $this->addFlash('success', 'Заявка успешно создана');
            }

            return $this->redirectToRoute('app_profile');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_profile');
        }
    }

    #[Route('/glass/stock/{stockId}/application/{applicationId}', name: 'app_stock_glass_update_application', methods: ['POST', 'PATCH'])]
    public function updateApplication(int $stockId, int $applicationId, Request $request): Response
    {
        $application = $this->applicationRepository->find($applicationId);
        if (!$application) {
            return new Response("Application not found", Response::HTTP_NOT_FOUND);
        }

        // Получаем данные и проверяем их наличие
        $quantity = $request->request->get('quantity');
        $price = $request->request->get('price');

        // Добавим отладочную информацию
        if ($quantity === null || $price === null) {
            return new Response(
                sprintf(
                    "Quantity and price are required. Received: quantity=%s, price=%s",
                    var_export($quantity, true),
                    var_export($price, true)
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $application->setQuantity((int) $quantity);
        $application->setPrice((float) $price);

        try {
            $appropriateApplication = $this->dealService->findAppropriateApplication($application);

            if ($appropriateApplication) {
                try {
                    $this->dealService->executeDeal($application, $appropriateApplication);
                    $this->addFlash('success', 'Сделка успешно выполнена');
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            } else {
                $this->applicationRepository->saveApplication($application);
                $this->addFlash('success', 'Заявка успешно создана');
            }

            return $this->redirectToRoute('app_profile');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_profile');
        }
    }

    #[Route('/glass/stock/{stockId}/application/{applicationId}', name: 'app_stock_glass_delete_application', methods: ['DELETE'])]
    public function deleteApplication(int $stockId, int $applicationId): Response
    {
        $application = $this->applicationRepository->find($applicationId);
        if (!$application) {
            return new Response("Application not found", Response::HTTP_NOT_FOUND);
        }

        try {
            $this->applicationRepository->remove($application, true);
            return $this->redirectToRoute('app_profile');
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
